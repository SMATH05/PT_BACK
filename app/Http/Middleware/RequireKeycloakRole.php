<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireKeycloakRole
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        if (! $request->attributes->has('keycloak_roles')) {
            return $next($request);
        }

        $roles = $request->attributes->get('keycloak_roles', []);
        $normalizedAllowedRoles = array_map(
            fn (string $role): string => $this->normalizeRole($role),
            $allowedRoles,
        );

        $hasAllowedRole = count(array_intersect($roles, $normalizedAllowedRoles)) > 0;

        if (! $hasAllowedRole) {
            return $this->forbidden();
        }

        return $next($request);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'message' => 'Forbidden',
            'error' => 'You do not have permission to access this resource.',
        ], 403);
    }

    private function normalizeRole(string $role): string
    {
        $normalizedRole = (string) preg_replace('/[\s-]+/', '_', mb_strtolower(trim($role)));

        return match ($normalizedRole) {
            'chef', 'chef_de_projets', 'chef_projet', 'chefprojet' => 'chef_de_projet',
            'dev', 'developper', 'developpeur', 'developer_role', 'devloper' => 'developer',
            'project_manager' => 'manager',
            default => $normalizedRole,
        };
    }
}
