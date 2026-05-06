<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ChefDeProjet;
use App\Models\Developer;
use App\Models\Manager;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait InteractsWithActorScope
{
    protected function userHasRole(Request $request, string ...$roles): bool
    {
        if (! $this->hasActorContext($request)) {
            return false;
        }

        $currentRoles = $request->attributes->get('keycloak_roles', []);
        $normalizedRoles = array_map([$this, 'normalizeRole'], $roles);

        return count(array_intersect($currentRoles, $normalizedRoles)) > 0;
    }

    protected function currentManagerId(Request $request): ?int
    {
        return $this->currentActorId($request, 'manager');
    }

    protected function currentDeveloperId(Request $request): ?int
    {
        return $this->currentActorId($request, 'developer');
    }

    protected function currentChefDeProjetId(Request $request): ?int
    {
        return $this->currentActorId($request, 'chef_de_projet');
    }

    protected function currentManager(Request $request): ?Manager
    {
        $managerId = $this->currentManagerId($request);

        return $managerId ? Manager::find($managerId) : null;
    }

    protected function currentDeveloper(Request $request): ?Developer
    {
        $developerId = $this->currentDeveloperId($request);

        return $developerId ? Developer::find($developerId) : null;
    }

    protected function currentChefDeProjet(Request $request): ?ChefDeProjet
    {
        $chefId = $this->currentChefDeProjetId($request);

        return $chefId ? ChefDeProjet::find($chefId) : null;
    }

    protected function scopedProjectsQuery(Request $request): Builder
    {
        $query = Project::query();

        if (! $this->hasActorContext($request)) {
            return $query;
        }

        if ($this->userHasRole($request, 'manager')) {
            $managerId = $this->currentManagerId($request);
            return $managerId ? $query->where('manager_id', $managerId) : $query->whereRaw('1 = 0');
        }

        if ($this->userHasRole($request, 'chef_de_projet')) {
            $chefId = $this->currentChefDeProjetId($request);
            return $chefId ? $query->where('chef_de_projet_id', $chefId) : $query->whereRaw('1 = 0');
        }

        if ($this->userHasRole($request, 'developer')) {
            $developerId = $this->currentDeveloperId($request);

            return $developerId
                ? $query->whereHas('developers', fn (Builder $developerQuery) => $developerQuery->where('developers.id', $developerId))
                : $query->whereRaw('1 = 0');
        }

        return $query->whereRaw('1 = 0');
    }

    protected function scopedTasksQuery(Request $request): Builder
    {
        $query = Task::query();

        if (! $this->hasActorContext($request)) {
            return $query;
        }

        if ($this->userHasRole($request, 'manager')) {
            $managerId = $this->currentManagerId($request);

            return $managerId
                ? $query->whereHas('project', fn (Builder $projectQuery) => $projectQuery->where('manager_id', $managerId))
                : $query->whereRaw('1 = 0');
        }

        if ($this->userHasRole($request, 'chef_de_projet')) {
            $chefId = $this->currentChefDeProjetId($request);

            return $chefId
                ? $query->where(function (Builder $taskQuery) use ($chefId): void {
                    $taskQuery
                        ->where('chef_de_projet_id', $chefId)
                        ->orWhereHas('project', fn (Builder $projectQuery) => $projectQuery->where('chef_de_projet_id', $chefId));
                })
                : $query->whereRaw('1 = 0');
        }

        if ($this->userHasRole($request, 'developer')) {
            $developerId = $this->currentDeveloperId($request);

            return $developerId
                ? $query->whereHas('developers', fn (Builder $developerQuery) => $developerQuery->where('developers.id', $developerId))
                : $query->whereRaw('1 = 0');
        }

        return $query->whereRaw('1 = 0');
    }

    protected function scopedDevelopersQuery(Request $request): Builder
    {
        $query = Developer::query();

        if (! $this->hasActorContext($request)) {
            return $query;
        }

        if ($this->userHasRole($request, 'manager')) {
            $managerId = $this->currentManagerId($request);
            return $managerId ? $query->where('manager_id', $managerId) : $query->whereRaw('1 = 0');
        }

        if ($this->userHasRole($request, 'chef_de_projet')) {
            $chefId = $this->currentChefDeProjetId($request);

            return $chefId
                ? $query->whereHas('projects', fn (Builder $projectQuery) => $projectQuery->where('projects.chef_de_projet_id', $chefId))
                : $query->whereRaw('1 = 0');
        }

        if ($this->userHasRole($request, 'developer')) {
            $developerId = $this->currentDeveloperId($request);
            return $developerId ? $query->whereKey($developerId) : $query->whereRaw('1 = 0');
        }

        return $query->whereRaw('1 = 0');
    }

    protected function scopedChefsQuery(Request $request): Builder
    {
        $query = ChefDeProjet::query();

        if (! $this->hasActorContext($request)) {
            return $query;
        }

        if ($this->userHasRole($request, 'manager')) {
            $managerId = $this->currentManagerId($request);
            return $managerId ? $query->where('manager_id', $managerId) : $query->whereRaw('1 = 0');
        }

        if ($this->userHasRole($request, 'chef_de_projet')) {
            $chefId = $this->currentChefDeProjetId($request);
            return $chefId ? $query->whereKey($chefId) : $query->whereRaw('1 = 0');
        }

        return $query->whereRaw('1 = 0');
    }

    protected function canAccessProject(Request $request, Project $project): bool
    {
        if (! $this->hasActorContext($request)) {
            return true;
        }

        if ($this->userHasRole($request, 'manager')) {
            return $project->manager_id === $this->currentManagerId($request);
        }

        if ($this->userHasRole($request, 'chef_de_projet')) {
            return $project->chef_de_projet_id === $this->currentChefDeProjetId($request);
        }

        if ($this->userHasRole($request, 'developer')) {
            $developerId = $this->currentDeveloperId($request);

            return $developerId !== null
                && $project->developers()->where('developers.id', $developerId)->exists();
        }

        return false;
    }

    protected function canManageProject(Request $request, Project $project): bool
    {
        if (! $this->hasActorContext($request)) {
            return true;
        }

        return $this->userHasRole($request, 'manager')
            && $project->manager_id === $this->currentManagerId($request);
    }

    protected function canAccessTask(Request $request, Task $task): bool
    {
        if (! $this->hasActorContext($request)) {
            return true;
        }

        if ($this->userHasRole($request, 'manager')) {
            return $task->project()->where('manager_id', $this->currentManagerId($request))->exists();
        }

        if ($this->userHasRole($request, 'chef_de_projet')) {
            $chefId = $this->currentChefDeProjetId($request);

            return $chefId !== null && (
                $task->chef_de_projet_id === $chefId
                || $task->project()->where('chef_de_projet_id', $chefId)->exists()
            );
        }

        if ($this->userHasRole($request, 'developer')) {
            $developerId = $this->currentDeveloperId($request);

            return $developerId !== null
                && $task->developers()->where('developers.id', $developerId)->exists();
        }

        return false;
    }

    protected function canManageTask(Request $request, Task $task): bool
    {
        if (! $this->hasActorContext($request)) {
            return true;
        }

        return $this->userHasRole($request, 'manager')
            && $task->project()->where('manager_id', $this->currentManagerId($request))->exists();
    }

    private function currentActorId(Request $request, string $actorType): ?int
    {
        $actorIds = $request->attributes->get('keycloak_actor_ids', []);
        $value = $actorIds[$actorType] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function hasActorContext(Request $request): bool
    {
        return $request->attributes->has('keycloak_roles')
            && $request->attributes->has('keycloak_actor_ids');
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
