<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KeycloakTokenVerifier
{
    /**
     * Validate a Keycloak access token and return its claims.
     *
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        if (! config('keycloak.enabled', true)) {
            throw new RuntimeException('Keycloak authentication is disabled.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $this->splitToken($token);

        $header = $this->decodeJson($encodedHeader, 'token header');
        $claims = $this->decodeJson($encodedPayload, 'token payload');
        $signature = $this->base64UrlDecode($encodedSignature);

        $kid = $header['kid'] ?? null;
        $alg = $header['alg'] ?? null;

        if (! is_string($kid) || $kid === '') {
            throw new RuntimeException('The token does not contain a valid key identifier.');
        }

        if (! is_string($alg) || ! array_key_exists($alg, $this->supportedAlgorithms())) {
            throw new RuntimeException('The token signing algorithm is not supported.');
        }

        $jwk = $this->resolveJwk($kid);
        $publicKey = openssl_pkey_get_public($this->convertJwkToPem($jwk));

        if ($publicKey === false) {
            throw new RuntimeException('Unable to build the Keycloak public key.');
        }

        $verified = openssl_verify(
            $encodedHeader.'.'.$encodedPayload,
            $signature,
            $publicKey,
            $this->supportedAlgorithms()[$alg]
        );

        if ($verified !== 1) {
            throw new RuntimeException('The token signature is invalid.');
        }

        $this->assertStandardClaims($claims);

        return $claims;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function splitToken(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new RuntimeException('The bearer token is not a valid JWT.');
        }

        return $segments;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $segment, string $context): array
    {
        $decoded = json_decode($this->base64UrlDecode($segment), true);

        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('Unable to decode the %s.', $context));
        }

        return $decoded;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Unable to decode a base64url value.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveJwk(string $kid): array
    {
        $jwksUrl = config('keycloak.jwks_url');

        if (! is_string($jwksUrl) || $jwksUrl === '') {
            throw new RuntimeException('The Keycloak JWKS URL is not configured.');
        }

        $cacheKey = 'keycloak.jwks.'.md5($jwksUrl);
        $ttl = max((int) config('keycloak.cache_ttl', 3600), 1);

        $keys = Cache::remember($cacheKey, $ttl, function () use ($jwksUrl): array {
            $response = Http::timeout((int) config('keycloak.timeout', 5))->get($jwksUrl);

            if (! $response->successful()) {
                throw new RuntimeException('Unable to fetch Keycloak public keys.');
            }

            $payload = $response->json();
            $keys = $payload['keys'] ?? null;

            if (! is_array($keys)) {
                throw new RuntimeException('The Keycloak JWKS payload is invalid.');
            }

            return $keys;
        });

        foreach ($keys as $key) {
            if (is_array($key) && ($key['kid'] ?? null) === $kid) {
                return $key;
            }
        }

        throw new RuntimeException('No matching Keycloak public key was found for this token.');
    }

    /**
     * @param  array<string, mixed>  $jwk
     */
    private function convertJwkToPem(array $jwk): string
    {
        if (isset($jwk['x5c'][0]) && is_string($jwk['x5c'][0])) {
            return "-----BEGIN CERTIFICATE-----\n"
                .chunk_split($jwk['x5c'][0], 64, "\n")
                ."-----END CERTIFICATE-----\n";
        }

        $modulus = $jwk['n'] ?? null;
        $exponent = $jwk['e'] ?? null;

        if (! is_string($modulus) || ! is_string($exponent)) {
            throw new RuntimeException('The Keycloak JWK does not expose a usable RSA key.');
        }

        $modulus = $this->encodeAsn1Integer($this->base64UrlDecode($modulus));
        $exponent = $this->encodeAsn1Integer($this->base64UrlDecode($exponent));

        $rsaPublicKey = $this->encodeAsn1Sequence($modulus.$exponent);
        $algorithmIdentifier = hex2bin('300d06092a864886f70d0101010500');

        if ($algorithmIdentifier === false) {
            throw new RuntimeException('Unable to build the RSA algorithm identifier.');
        }

        $subjectPublicKeyInfo = $this->encodeAsn1Sequence(
            $algorithmIdentifier.$this->encodeAsn1BitString($rsaPublicKey)
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private function encodeAsn1Integer(string $value): string
    {
        $value = ltrim($value, "\x00");

        if ($value === '' || (ord($value[0]) & 0x80) === 0x80) {
            $value = "\x00".$value;
        }

        return "\x02".$this->encodeAsn1Length(strlen($value)).$value;
    }

    private function encodeAsn1Sequence(string $value): string
    {
        return "\x30".$this->encodeAsn1Length(strlen($value)).$value;
    }

    private function encodeAsn1BitString(string $value): string
    {
        return "\x03".$this->encodeAsn1Length(strlen($value) + 1)."\x00".$value;
    }

    private function encodeAsn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $encoded = '';

        while ($length > 0) {
            $encoded = chr($length & 0xff).$encoded;
            $length >>= 8;
        }

        return chr(0x80 | strlen($encoded)).$encoded;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertStandardClaims(array $claims): void
    {
        $leeway = max((int) config('keycloak.leeway', 60), 0);
        $now = time();

        $expiresAt = $claims['exp'] ?? null;
        if (! is_numeric($expiresAt) || ((int) $expiresAt + $leeway) < $now) {
            throw new RuntimeException('The token has expired.');
        }

        $notBefore = $claims['nbf'] ?? null;
        if ($notBefore !== null && (! is_numeric($notBefore) || ((int) $notBefore - $leeway) > $now)) {
            throw new RuntimeException('The token is not active yet.');
        }

        $issuedAt = $claims['iat'] ?? null;
        if ($issuedAt !== null && (! is_numeric($issuedAt) || ((int) $issuedAt - $leeway) > $now)) {
            throw new RuntimeException('The token issue time is invalid.');
        }

        $issuer = config('keycloak.issuer');
        if (is_string($issuer) && $issuer !== '' && ($claims['iss'] ?? null) !== $issuer) {
            throw new RuntimeException('The token issuer is invalid.');
        }

        $expectedAudiences = array_values(array_filter([
            config('keycloak.client_id'),
            ...config('keycloak.audiences', []),
        ]));

        if ($expectedAudiences !== [] && ! $this->claimsContainAudience($claims, $expectedAudiences)) {
            throw new RuntimeException('The token audience is invalid.');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  array<int, string>  $expectedAudiences
     */
    private function claimsContainAudience(array $claims, array $expectedAudiences): bool
    {
        $audience = $claims['aud'] ?? [];
        $audiences = is_array($audience) ? $audience : [$audience];

        foreach ($audiences as $candidate) {
            if (is_string($candidate) && in_array($candidate, $expectedAudiences, true)) {
                return true;
            }
        }

        $authorizedParty = $claims['azp'] ?? null;

        return is_string($authorizedParty) && in_array($authorizedParty, $expectedAudiences, true);
    }

    /**
     * @return array<string, int>
     */
    private function supportedAlgorithms(): array
    {
        return [
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
        ];
    }
}
