<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActorMatchesRoute
{
    public function handle(Request $request, Closure $next, string $actorType, string $routeParam = 'id'): Response
    {
        if (! $request->attributes->has('keycloak_actor_ids')) {
            return $next($request);
        }

        $actorIds = $request->attributes->get('keycloak_actor_ids', []);
        $expectedActorId = $actorIds[$actorType] ?? null;
        $routeValue = $request->route($routeParam);

        if ($expectedActorId === null || (string) $expectedActorId !== (string) $routeValue) {
            return $this->forbidden();
        }

        return $next($request);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'message' => 'Forbidden',
            'error' => 'You cannot access another user scope.',
        ], 403);
    }
}
