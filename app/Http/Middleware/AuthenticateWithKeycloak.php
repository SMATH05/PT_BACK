<?php

namespace App\Http\Middleware;

use App\Services\KeycloakTokenVerifier;
use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticateWithKeycloak
{
    public function __construct(
        private readonly KeycloakTokenVerifier $verifier
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return $this->unauthorized('Missing bearer token.');
        }

        try {
            $claims = $this->verifier->verify($token);
        } catch (Throwable $exception) {
            return $this->unauthorized($exception->getMessage());
        }

        $roles = $this->extractRoles($claims);
        $user = new GenericUser([
            'id' => $claims['sub'] ?? null,
            'sub' => $claims['sub'] ?? null,
            'preferred_username' => $claims['preferred_username'] ?? null,
            'name' => $claims['name'] ?? null,
            'email' => $claims['email'] ?? null,
            'given_name' => $claims['given_name'] ?? null,
            'family_name' => $claims['family_name'] ?? null,
            'roles' => $roles,
            'claims' => $claims,
        ]);

        Auth::setUser($user);
        $request->setUserResolver(static fn (): GenericUser => $user);
        $request->attributes->set('keycloak_claims', $claims);
        $request->attributes->set('keycloak_roles', $roles);

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array<int, string>
     */
    private function extractRoles(array $claims): array
    {
        $roles = [];

        $realmRoles = $claims['realm_access']['roles'] ?? [];
        if (is_array($realmRoles)) {
            $roles = [...$roles, ...array_filter($realmRoles, 'is_string')];
        }

        $resourceAccess = $claims['resource_access'] ?? [];
        if (is_array($resourceAccess)) {
            foreach ($resourceAccess as $resource) {
                $resourceRoles = is_array($resource) ? ($resource['roles'] ?? []) : [];

                if (is_array($resourceRoles)) {
                    $roles = [...$roles, ...array_filter($resourceRoles, 'is_string')];
                }
            }
        }

        return array_values(array_unique($roles));
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => 'Unauthorized',
            'error' => $message,
        ], 401);
    }
}
