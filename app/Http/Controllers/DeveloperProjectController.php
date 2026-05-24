<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithActorScope;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Developer;
use App\Models\DeveloperProject;

class DeveloperProjectController extends Controller
{
    use InteractsWithActorScope;

    /**
     * Assign a developer to a project with a specific role and record the join date.
     */
    public function assignDeveloperToProject(Request $request, $projectId, $developerId)
    {
        $request->validate([
            'position' => 'required|string|max:255',
        ]);

        $position = $request->input('position');

        $project = Project::findOrFail($projectId);
        $developer = Developer::findOrFail($developerId);

        // Check if already assigned
        if ($project->developers()->where('developer_id', $developerId)->exists()) {
            return response()->json(['message' => 'Developer is already assigned to this project.'], 400);
        }

        $project->developers()->attach($developerId, [
            'position' => $position,
            'joined_at' => now(),
        ]);

        if ($developer->manager_id !== $project->manager_id) {
            $developer->update(['manager_id' => $project->manager_id]);
        }

        return response()->json(['message' => 'Developer assigned to project successfully.']);
    }

    /**
     * Retrieve all developers linked to a given project with their details.
     */
    public function listProjectDevelopers($projectId)
    {
        $project = Project::findOrFail($projectId);

        $developers = $project->developers()->with('manager')->get()->map(function ($developer) {
            return [
                'id' => $developer->id,
                'name' => $developer->name,
                'email' => $developer->email,
                'position' => $developer->pivot->position,
                'joined_at' => $developer->pivot->joined_at,
                'manager' => $developer->manager ? $developer->manager->name : null,
            ];
        });

        return response()->json($developers);
    }

    /**
     * Update the role of a developer within a project.
     */
    public function updateDeveloperRole(Request $request, $projectId, $developerId)
    {
        $request->validate([
            'position' => 'required|string|max:255',
        ]);

        $newPosition = $request->input('position');

        $project = Project::findOrFail($projectId);
        $developer = Developer::findOrFail($developerId);

        if (! $project->developers()->where('developers.id', $developerId)->exists()) {
            return response()->json(['message' => 'Developer is not assigned to this project.'], 404);
        }

        $project->developers()->updateExistingPivot($developerId, [
            'position' => $newPosition,
        ]);

        return response()->json(['message' => 'Developer role updated successfully.']);
    }

    /**
     * Remove a developer from a project.
     */
    public function removeDeveloperFromProject($projectId, $developerId)
    {
        $project = Project::findOrFail($projectId);
        $developer = Developer::findOrFail($developerId);

        $taskIds = $project->tasks()->pluck('tasks.id');
        $developer->tasks()->detach($taskIds);
        $project->developers()->detach($developerId);

        return response()->json(['message' => 'Developer removed from project successfully.']);
    }

    /**
     * Retrieve all projects a given developer is working on.
     */
    public function getDeveloperProjects(Request $request, $developerId)
    {
        $developer = Developer::findOrFail($developerId);

        if ($this->userHasRole($request, 'developer') && $this->currentDeveloperId($request) !== (int) $developerId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($this->userHasRole($request, 'manager') && $developer->manager_id !== $this->currentManagerId($request)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($this->userHasRole($request, 'chef_de_projet')) {
            $canSeeDeveloper = $developer->projects()
                ->where('projects.chef_de_projet_id', $this->currentChefDeProjetId($request))
                ->exists();

            if (! $canSeeDeveloper) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        return response()->json($developer->projects()->with(['manager', 'chefDeProjet', 'slaProject'])->get());
    }

    /**
     * Show the history of a developer's involvement in a project.
     */
    public function projectDeveloperHistory($projectId, $developerId)
    {
        $pivot = DeveloperProject::where('project_id', $projectId)
                                 ->where('developer_id', $developerId)
                                 ->firstOrFail();

        $history = [
            'joined_at' => $pivot->joined_at,
            'current_position' => $pivot->position,
            // Since we don't have a history table, we can only show current data
            'notes' => 'History tracking not implemented. Only current assignment shown.',
        ];

        return response()->json($history);
    }

    /**
     * Assign multiple developers to a project in one operation.
     */
    public function bulkAssignDevelopers(Request $request, $projectId)
    {
        $request->validate([
            'developers' => 'required|array',
            'developers.*.id' => 'required|integer|exists:developers,id',
            'developers.*.position' => 'required|string|max:255',
        ]);

        $developers = $request->input('developers');

        $project = Project::findOrFail($projectId);

        $data = [];
        foreach ($developers as $dev) {
            // Check if already assigned
            if ($project->developers()->where('developer_id', $dev['id'])->exists()) {
                continue; // Skip if already assigned
            }

            $data[$dev['id']] = [
                'position' => $dev['position'],
                'joined_at' => now(),
            ];
        }

        $project->developers()->attach($data);
        Developer::whereIn('id', array_keys($data))->update(['manager_id' => $project->manager_id]);

        return response()->json(['message' => 'Developers assigned to project successfully.']);
    }
}
