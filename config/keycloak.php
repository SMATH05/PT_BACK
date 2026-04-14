<?php

$baseUrl = rtrim((string) env('KEYCLOAK_BASE_URL', ''), '/');
$realm = (string) env('KEYCLOAK_REALM', '');
$realmPath = $baseUrl !== '' && $realm !== ''
    ? sprintf('%s/realms/%s', $baseUrl, $realm)
    : null;

return [
    'enabled' => env('KEYCLOAK_ENABLED', true),
    'issuer' => env('KEYCLOAK_ISSUER', $realmPath),
    'jwks_url' => env(
        'KEYCLOAK_JWKS_URL',
        $realmPath ? sprintf('%s/protocol/openid-connect/certs', $realmPath) : null
    ),
    'client_id' => env('KEYCLOAK_CLIENT_ID'),
    'audiences' => array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env('KEYCLOAK_AUDIENCES', env('KEYCLOAK_AUDIENCE', '')))
    ))),
    'cache_ttl' => (int) env('KEYCLOAK_CACHE_TTL', 3600),
    'leeway' => (int) env('KEYCLOAK_LEEWAY', 60),
    'timeout' => (int) env('KEYCLOAK_HTTP_TIMEOUT', 5),
];
