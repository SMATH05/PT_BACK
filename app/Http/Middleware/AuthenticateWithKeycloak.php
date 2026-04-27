<?php

namespace App\Http\Middleware;

use App\Models\ChefDeProjet;
use App\Models\Developer;
use App\Models\Manager;
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
        $actorIds = $this->resolveActorIds($claims);
        $user = new GenericUser([
            'id' => $claims['sub'] ?? null,
            'sub' => $claims['sub'] ?? null,
            'preferred_username' => $claims['preferred_username'] ?? null,
            'name' => $claims['name'] ?? null,
            'email' => $claims['email'] ?? null,
            'given_name' => $claims['given_name'] ?? null,
            'family_name' => $claims['family_name'] ?? null,
            'roles' => $roles,
            'actor_ids' => $actorIds,
            'claims' => $claims,
        ]);

        Auth::setUser($user);
        $request->setUserResolver(static fn (): GenericUser => $user);
        $request->attributes->set('keycloak_claims', $claims);
        $request->attributes->set('keycloak_roles', $roles);
        $request->attributes->set('keycloak_actor_ids', $actorIds);

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

        return array_values(array_unique(array_map(
            fn (string $role): string => $this->normalizeRole($role),
            $roles,
        )));
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array<string, int|null>
     */
    private function resolveActorIds(array $claims): array
    {
        $email = $claims['email'] ?? null;
        $roles = $this->extractRoles($claims);

        if (! is_string($email) || $email === '') {
            return [
                'manager' => null,
                'developer' => null,
                'chef_de_projet' => null,
            ];
        }

        $name = $this->resolveDisplayName($claims);

        if (in_array('manager', $roles, true)) {
            Manager::firstOrCreate(
                ['email' => $email],
                ['name' => $name]
            );
        }

        $managerId = Manager::where('email', $email)->value('id');

        if (in_array('developer', $roles, true)) {
            Developer::firstOrCreate(
                ['email' => $email],
                [
                    'manager_id' => $managerId,
                    'name' => $name,
                ]
            );
        }

        if (in_array('chef_de_projet', $roles, true)) {
            ChefDeProjet::firstOrCreate(
                ['email' => $email],
                [
                    'manager_id' => $managerId,
                    'name' => $name,
                ]
            );
        }

        return [
            'manager' => $managerId,
            'developer' => Developer::where('email', $email)->value('id'),
            'chef_de_projet' => ChefDeProjet::where('email', $email)->value('id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function resolveDisplayName(array $claims): string
    {
        $name = $claims['name']
            ?? $claims['preferred_username']
            ?? $claims['given_name']
            ?? $claims['email']
            ?? 'Unknown User';

        return is_string($name) && trim($name) !== ''
            ? trim($name)
            : 'Unknown User';
    }

    private function normalizeRole(string $role): string
    {
        return (string) preg_replace('/[\s-]+/', '_', mb_strtolower(trim($role)));
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => 'Unauthorized',
            'error' => $message,
        ], 401);
    }
}
