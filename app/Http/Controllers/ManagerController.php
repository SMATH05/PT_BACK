<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithActorScope;
use App\Models\Manager;
use App\Models\Project;
use App\Models\Developer;
use App\Models\ChefDeProjet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ManagerController extends Controller
{
    use InteractsWithActorScope;

    /**
     * Get all managers
     */
    public function index(Request $request)
    {
        $managerId = $this->currentManagerId($request);
        $managers = $managerId
            ? Manager::with('projects', 'developers', 'chefDeProjets')->whereKey($managerId)->get()
            : collect();

        return response()->json($managers);
    }

    /**
     * Get a specific manager
     */
    public function show(Request $request, $id)
    {
        if ($this->currentManagerId($request) !== (int) $id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $manager = Manager::with('projects', 'developers', 'chefDeProjets')->find($id);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        return response()->json($manager);
    }

    /**
     * Create a new manager
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:managers,name',
            'email' => 'required|email|max:255|unique:managers,email',
        ]);

        $manager = Manager::create($validated);

        return response()->json([
            'message' => 'Manager created successfully',
            'data' => $manager,
        ], 201);
    }

    /**
     * Update a manager
     */
    public function update(Request $request, $id)
    {
        if ($this->currentManagerId($request) !== (int) $id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $manager = Manager::find($id);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:managers,name,' . $id,
            'email' => 'sometimes|required|email|max:255|unique:managers,email,' . $id,
        ]);

        $manager->update($validated);

        return response()->json([
            'message' => 'Manager updated successfully',
            'data' => $manager,
        ]);
    }

    /**
     * Delete a manager
     */
    public function destroy(Request $request, $id)
    {
        if ($this->currentManagerId($request) !== (int) $id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $manager = Manager::find($id);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $manager->delete();

        return response()->json(['message' => 'Manager deleted successfully']);
    }

    /**
     * Create a new project for a manager
     */
    public function createProject(Request $request, $managerId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        // Validate the request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'deadline' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string|in:pending,in_progress,done',
            'chef_de_projet_id' => 'nullable|exists:chef_de_projets,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->toDateString();
        $endDate = $validated['end_date'] ?? ($validated['deadline'] ?? $startDate);
        $deadline = $validated['deadline'] ?? $endDate;
        $status = $validated['status'] ?? 'pending';

        if (!empty($validated['chef_de_projet_id'])) {
            $chefBelongsToManager = ChefDeProjet::where('id', $validated['chef_de_projet_id'])
                ->where('manager_id', $managerId)
                ->exists();

            if (!$chefBelongsToManager) {
                return response()->json([
                    'message' => 'Selected chef de projet is not managed by this manager',
                ], 422);
            }
        }

        // Create a safe folder name (replace spaces and special characters with underscores)
        $safeFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $validated['name']);
        $safeFolderName = preg_replace('/_+/', '_', $safeFolderName); // Replace multiple underscores with single
        $safeFolderName = trim($safeFolderName, '_'); // Remove leading/trailing underscores

        // Create the folder path
        $folderPath = 'projects/' . $safeFolderName;
        $this->createProjectWorkspace($folderPath, [
            'name' => $validated['name'],
            'client' => $validated['client'],
            'manager_id' => $managerId,
            'chef_de_projet_id' => $validated['chef_de_projet_id'] ?? null,
            'status' => $status,
            'start_date' => $startDate,
            'deadline' => $deadline,
        ]);

        // Create the project record
        $project = Project::create([
            'name' => $validated['name'],
            'client' => $validated['client'],
            'description' => $validated['description'] ?? null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'deadline' => $deadline,
            'status' => $status,
            'manager_id' => $managerId,
            'chef_de_projet_id' => $validated['chef_de_projet_id'] ?? null,
            'folder_path' => $folderPath,
        ]);

        // Return JSON response with the created project
        return response()->json([
            'message' => 'Project created successfully',
            'data' => $project->load('manager', 'chefDeProjet'),
        ], 201);
    }

    /**
     * Create a VS Code style project workspace scaffold.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function createProjectWorkspace(string $folderPath, array $metadata): void
    {
        $disk = Storage::disk(config('filesystems.default', 'local'));

        $disk->makeDirectory($folderPath);

        foreach (['docs', 'files', 'src', 'tasks', 'deliverables', 'notes'] as $directory) {
            $disk->makeDirectory($folderPath . '/' . $directory);
            $disk->put($folderPath . '/' . $directory . '/.gitkeep', '');
        }

        $readme = implode(PHP_EOL, [
            '# ' . $metadata['name'],
            '',
            'Client: ' . $metadata['client'],
            'Status: ' . $metadata['status'],
            'Manager ID: ' . $metadata['manager_id'],
            'Chef de projet ID: ' . ($metadata['chef_de_projet_id'] ?? 'none'),
            'Start date: ' . ($metadata['start_date'] ?? 'not set'),
            'Deadline: ' . ($metadata['deadline'] ?? 'not set'),
            '',
            'Workspace folders:',
            '- docs',
            '- files',
            '- src',
            '- tasks',
            '- deliverables',
            '- notes',
            '',
            'Use `files/` for uploaded documents and `src/` for working files.',
            '',
        ]);

        $disk->put($folderPath . '/README.md', $readme);
        $disk->put($folderPath . '/project.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get all projects for a manager
     */
    public function getProjects($managerId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $projects = $manager->projects()->with('chefDeProjet')->get();

        return response()->json($projects);
    }

    /**
     * Get all developers for a manager
     */
    public function getDevelopers($managerId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $developers = $manager->developers()->get();

        return response()->json($developers);
    }

    /**
     * Get all chefs de projet for a manager
     */
    public function getChefsDeProjets($managerId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $chefsDeProjets = $manager->chefDeProjets()->get();

        return response()->json($chefsDeProjets);
    }

    /**
     * Assign a developer to a manager
     */
    public function assignDeveloper(Request $request, $managerId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $validated = $request->validate([
            'developer_id' => 'required|exists:developers,id',
        ]);

        $developer = Developer::find($validated['developer_id']);

        if ($developer->manager_id === $managerId) {
            return response()->json(['message' => 'Developer is already assigned to this manager'], 409);
        }

        $developer->update(['manager_id' => $managerId]);

        return response()->json([
            'message' => 'Developer assigned to manager successfully',
            'data' => $developer,
        ]);
    }

    /**
     * Assign a chef de projet to a manager
     */
    public function assignChefDeProjet(Request $request, $managerId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $validated = $request->validate([
            'chef_de_projet_id' => 'required|exists:chef_de_projets,id',
        ]);

        $chefDeProjet = ChefDeProjet::find($validated['chef_de_projet_id']);

        if ($chefDeProjet->manager_id === $managerId) {
            return response()->json(['message' => 'Chef de projet is already assigned to this manager'], 409);
        }

        $chefDeProjet->update(['manager_id' => $managerId]);

        return response()->json([
            'message' => 'Chef de projet assigned to manager successfully',
            'data' => $chefDeProjet,
        ]);
    }

    /**
     * Get project count for a manager
     */
    public function projectCount($managerId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $count = $manager->projects()->count();

        return response()->json(['manager_id' => $managerId, 'project_count' => $count]);
    }

    /**
     * Get developer count for a manager
     */
    public function developerCount($managerId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $count = $manager->developers()->count();

        return response()->json(['manager_id' => $managerId, 'developer_count' => $count]);
    }

    /**
     * Get chef de projet count for a manager
     */
    public function chefDeProjetCount($managerId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $count = $manager->chefDeProjets()->count();

        return response()->json(['manager_id' => $managerId, 'chef_de_projet_count' => $count]);
    }

    /**
     * Get manager statistics
     */
    public function statistics($managerId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $stats = [
            'manager_id' => $managerId,
            'manager_name' => $manager->name,
            'total_projects' => $manager->projects()->count(),
            'total_developers' => $manager->developers()->count(),
            'total_chefs_de_projet' => $manager->chefDeProjets()->count(),
            'total_tasks' => $manager->projects()->with('tasks')->get()->sum(function ($project) {
                return $project->tasks()->count();
            }),
        ];

        return response()->json($stats);
    }

    /**
     * Get managers by search query
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|min:1',
        ]);

        $managers = Manager::where('name', 'like', '%' . $validated['query'] . '%')
            ->with('projects', 'developers', 'chefDeProjets')
            ->get();

        return response()->json($managers);
    }

    /**
     * Get project details for a manager
     */
    public function projectDetails($managerId, $projectId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $project = $manager->projects()->find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found for this manager'], 404);
        }

        $project->load('chefDeProjet', 'tasks', 'developers');

        return response()->json($project);
    }

    /**
     * Data source for "Assign" screen on a manager project.
     */
    public function projectAssignmentData($managerId, $projectId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $project = $manager->projects()
            ->with([
                'chefDeProjet',
                'developers',
                'tasks.developers',
            ])
            ->find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found for this manager'], 404);
        }

        $availableChefs = ChefDeProjet::query()
            ->select('id', 'name', 'email')
            ->where(function ($query) use ($managerId): void {
                $query->where('manager_id', $managerId)
                    ->orWhereNull('manager_id');
            })
            ->orderBy('name')
            ->get();

        $availableDevelopers = Developer::query()
            ->select('id', 'name', 'email')
            ->where(function ($query) use ($managerId): void {
                $query->where('manager_id', $managerId)
                    ->orWhereNull('manager_id');
            })
            ->orderBy('name')
            ->get();

        $currentProjectDevelopers = $project->developers->map(function ($developer): array {
            return [
                'developer_id' => $developer->id,
                'position' => $developer->pivot->position,
                'joined_at' => $developer->pivot->joined_at,
            ];
        })->values();

        $currentTaskAssignments = $project->tasks->map(function ($task): array {
            return [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'developers' => $task->developers->map(function ($developer): array {
                    return [
                        'developer_id' => $developer->id,
                        'role' => $developer->pivot->role,
                        'assigned_at' => $developer->pivot->assigned_at,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'project' => $project,
            'available_chefs_de_projet' => $availableChefs,
            'available_developers' => $availableDevelopers,
            'current_assignments' => [
                'chef_de_projet_id' => $project->chef_de_projet_id,
                'project_developers' => $currentProjectDevelopers,
                'task_assignments' => $currentTaskAssignments,
            ],
        ]);
    }

    /**
     * Save project-level and task-level assignment in one operation.
     */
    public function saveProjectAssignments(Request $request, $managerId, $projectId)
    {
        $payload = $request->all();

        if (($payload['chef_de_projet_id'] ?? null) === '') {
            $payload['chef_de_projet_id'] = null;
        }

        if (isset($payload['project_developers']) && is_array($payload['project_developers'])) {
            $payload['project_developers'] = array_map(function ($developer): array {
                if (is_array($developer)) {
                    $developerId = $developer['developer_id'] ?? $developer['id'] ?? null;
                    $position = $developer['position'] ?? $developer['role'] ?? 'developer';

                    return [
                        'developer_id' => $developerId,
                        'position' => $position,
                    ];
                }

                return [
                    'developer_id' => $developer,
                    'position' => 'developer',
                ];
            }, $payload['project_developers']);
        }

        if (isset($payload['task_assignments']) && is_array($payload['task_assignments'])) {
            $payload['task_assignments'] = array_map(function ($assignment): array {
                if (!is_array($assignment)) {
                    return [
                        'task_id' => $assignment,
                        'developers' => [],
                    ];
                }

                $developers = $assignment['developers'] ?? [];
                if (is_array($developers)) {
                    $developers = array_map(function ($developer): array {
                        if (is_array($developer)) {
                            return [
                                'developer_id' => $developer['developer_id'] ?? $developer['id'] ?? null,
                                'role' => $developer['role'] ?? 'developer',
                            ];
                        }

                        return [
                            'developer_id' => $developer,
                            'role' => 'developer',
                        ];
                    }, $developers);
                } else {
                    $developers = [];
                }

                return [
                    'task_id' => $assignment['task_id'] ?? $assignment['id'] ?? null,
                    'developers' => $developers,
                ];
            }, $payload['task_assignments']);
        }

        $request->replace($payload);

        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $project = $manager->projects()->with('tasks')->find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found for this manager'], 404);
        }

        $validated = $request->validate([
            'chef_de_projet_id' => 'sometimes|nullable|exists:chef_de_projets,id',
            'replace_project_developers' => 'sometimes|boolean',
            'project_developers' => 'sometimes|array',
            'project_developers.*.developer_id' => 'required|distinct|exists:developers,id',
            'project_developers.*.position' => 'required|string|max:255',
            'task_assignments' => 'sometimes|array',
            'task_assignments.*.task_id' => 'required|exists:tasks,id',
            'task_assignments.*.developers' => 'sometimes|array',
            'task_assignments.*.developers.*.developer_id' => 'required_with:task_assignments.*.developers|exists:developers,id',
            'task_assignments.*.developers.*.role' => 'required_with:task_assignments.*.developers|string|max:255',
        ]);

        if (array_key_exists('chef_de_projet_id', $validated) && $validated['chef_de_projet_id'] !== null) {
            $chefExists = ChefDeProjet::where('id', $validated['chef_de_projet_id'])->exists();

            if (!$chefExists) {
                return response()->json([
                    'message' => 'Selected chef de projet does not exist',
                ], 422);
            }
        }

        $projectTaskIds = $project->tasks->pluck('id')->all();

        if (!empty($validated['task_assignments'])) {
            foreach ($validated['task_assignments'] as $assignment) {
                if (!in_array($assignment['task_id'], $projectTaskIds, true)) {
                    return response()->json([
                        'message' => "Task {$assignment['task_id']} does not belong to this project",
                    ], 422);
                }
            }
        }

        $existingProjectDeveloperIds = $project->developers()->pluck('developers.id')->all();

        if (!empty($validated['project_developers'])) {
            $incomingProjectDeveloperIds = collect($validated['project_developers'])
                ->pluck('developer_id')
                ->unique()
                ->values()
                ->all();

            $existingCount = Developer::whereIn('id', $incomingProjectDeveloperIds)
                ->count();

            if ($existingCount !== count($incomingProjectDeveloperIds)) {
                return response()->json([
                    'message' => 'One or more project developers do not exist',
                ], 422);
            }

            // Allow payloads that send only deltas by accepting both current + incoming assignments.
            $projectDeveloperIds = array_values(array_unique([
                ...$existingProjectDeveloperIds,
                ...$incomingProjectDeveloperIds,
            ]));
        } else {
            $projectDeveloperIds = $existingProjectDeveloperIds;
        }

        if (!empty($validated['task_assignments'])) {
            foreach ($validated['task_assignments'] as $assignment) {
                foreach (($assignment['developers'] ?? []) as $taskDeveloper) {
                    if (!in_array($taskDeveloper['developer_id'], $projectDeveloperIds, true)) {
                        return response()->json([
                            'message' => "Developer {$taskDeveloper['developer_id']} must be assigned to the project first",
                        ], 422);
                    }
                }
            }
        }

        DB::transaction(function () use ($validated, $project): void {
            if (array_key_exists('chef_de_projet_id', $validated)) {
                if ($validated['chef_de_projet_id'] !== null) {
                    ChefDeProjet::where('id', $validated['chef_de_projet_id'])->update([
                        'manager_id' => $project->manager_id,
                    ]);
                }

                $project->update([
                    'chef_de_projet_id' => $validated['chef_de_projet_id'],
                ]);

                $project->tasks()->update([
                    'chef_de_projet_id' => $validated['chef_de_projet_id'],
                ]);
            }

            if (!empty($validated['project_developers'])) {
                $syncProjectDevelopers = [];

                foreach ($validated['project_developers'] as $developer) {
                    $syncProjectDevelopers[$developer['developer_id']] = [
                        'position' => $developer['position'],
                        'joined_at' => now(),
                    ];
                }

                Developer::whereIn('id', array_keys($syncProjectDevelopers))->update([
                    'manager_id' => $project->manager_id,
                ]);

                if (($validated['replace_project_developers'] ?? false) === true) {
                    $incomingDeveloperIds = array_keys($syncProjectDevelopers);
                    $taskIds = $project->tasks()->pluck('tasks.id');
                    $project->developers()
                        ->whereNotIn('developers.id', $incomingDeveloperIds)
                        ->get()
                        ->each(function ($developer) use ($taskIds): void {
                            $developer->tasks()->detach($taskIds);
                        });

                    $project->developers()->sync($syncProjectDevelopers);
                } else {
                    $project->developers()->syncWithoutDetaching($syncProjectDevelopers);
                }
            }

            if (!empty($validated['task_assignments'])) {
                foreach ($validated['task_assignments'] as $assignment) {
                    $task = $project->tasks->firstWhere('id', $assignment['task_id']);

                    if (!$task) {
                        continue;
                    }

                    $syncTaskDevelopers = [];

                    foreach (($assignment['developers'] ?? []) as $developer) {
                        $syncTaskDevelopers[$developer['developer_id']] = [
                            'role' => $developer['role'],
                            'assigned_at' => now(),
                        ];
                    }

                    $task->developers()->sync($syncTaskDevelopers);
                }
            }
        });

        $freshProject = $manager->projects()
            ->with([
                'chefDeProjet',
                'developers',
                'tasks.developers',
            ])
            ->find($projectId);

        return response()->json([
            'message' => 'Project assignments saved successfully',
            'data' => $freshProject,
        ]);
    }

    /**
     * Update manager project
     */
    public function updateProject(Request $request, $managerId, $projectId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $project = $manager->projects()->find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found for this manager'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'client' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'deadline' => 'sometimes|nullable|date|after_or_equal:start_date',
            'status' => 'sometimes|required|string|in:pending,in_progress,done',
            'chef_de_projet_id' => 'sometimes|nullable|exists:chef_de_projets,id',
        ]);

        if (!array_key_exists('end_date', $validated) && array_key_exists('deadline', $validated)) {
            $validated['end_date'] = $validated['deadline'];
        }

        if (!array_key_exists('deadline', $validated) && array_key_exists('end_date', $validated)) {
            $validated['deadline'] = $validated['end_date'];
        }

        $project->update($validated);

        return response()->json([
            'message' => 'Project updated successfully',
            'data' => $project,
        ]);
    }

    /**
     * Delete manager project
     */
    public function deleteProject($managerId, $projectId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $project = $manager->projects()->find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found for this manager'], 404);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }

    /**
     * Remove developer from manager
     */
    public function removeDeveloper($managerId, $developerId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $developer = $manager->developers()->find($developerId);

        if (!$developer) {
            return response()->json(['message' => 'Developer not found for this manager'], 404);
        }

        $developer->update(['manager_id' => null]);

        return response()->json(['message' => 'Developer removed from manager successfully']);
    }

    /**
     * Remove chef de projet from manager
     */
    public function removeChefDeProjet($managerId, $chefDeProjetId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $chefDeProjet = $manager->chefDeProjets()->find($chefDeProjetId);

        if (!$chefDeProjet) {
            return response()->json(['message' => 'Chef de projet not found for this manager'], 404);
        }

        $chefDeProjet->update(['manager_id' => null]);

        return response()->json(['message' => 'Chef de projet removed from manager successfully']);
    }

    /**
     * Bulk assign developers to a manager
     */
    public function bulkAssignDevelopers(Request $request, $managerId)
    {
        $manager = Manager::find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $validated = $request->validate([
            'developer_ids' => 'required|array',
            'developer_ids.*' => 'exists:developers,id',
        ]);

        Developer::whereIn('id', $validated['developer_ids'])->update(['manager_id' => $managerId]);

        return response()->json([
            'message' => 'Developers assigned to manager successfully',
            'count' => count($validated['developer_ids']),
        ]);
    }

    /**
     * Export manager data
     */
    public function exportData($managerId)
    {
        $manager = Manager::with('projects', 'developers', 'chefDeProjets')->find($managerId);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $data = [
            'manager' => $manager,
            'projects' => $manager->projects()->with('tasks')->get(),
            'developers' => $manager->developers,
            'chefs_de_projet' => $manager->chefDeProjets,
        ];

        return response()->json($data);
    }
}
