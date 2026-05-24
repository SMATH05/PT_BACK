<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithActorScope;
use Illuminate\Http\Request;
use App\Models\ChefDeProjet;
use App\Models\Project;
use App\Models\Task;

class ChefDeProjetController extends Controller
{
    use InteractsWithActorScope;

    /**
     * List all chefs de projet.
     */
    public function index(Request $request)
    {
        $chefs = $this->scopedChefsQuery($request)
            ->with('manager')
            ->get();

        return response()->json($chefs);
    }

    /**
     * Get details of a specific chef de projet.
     */
    public function show(Request $request, $id)
    {
        $chef = ChefDeProjet::with('manager', 'projects', 'tasks')->findOrFail($id);

        if ($this->userHasRole($request, 'chef_de_projet') && $this->currentChefDeProjetId($request) !== (int) $id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'id' => $chef->id,
            'name' => $chef->name,
            'email' => $chef->email,
            'manager' => $chef->manager,
            'projects_count' => $chef->projects()->count(),
            'tasks_count' => $chef->tasks()->count(),
            'projects' => $chef->projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'deadline' => $project->deadline,
                    'progress' => $project->getProgress(),
                ];
            }),
            'recent_tasks' => $chef->tasks()->latest()->take(5)->get()->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'created_at' => $task->created_at,
                ];
            }),
        ]);
    }

    /**
     * Create a new chef de projet.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:chef_de_projets,email',
            'manager_id' => 'nullable|exists:managers,id',
        ]);

        $chef = ChefDeProjet::create($validated);

        return response()->json([
            'message' => 'Chef de projet created successfully',
            'data' => $chef,
        ], 201);
    }

    /**
     * Update chef de projet information.
     */
    public function update(Request $request, $id)
    {
        $chef = ChefDeProjet::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:chef_de_projets,email,' . $id,
            'manager_id' => 'nullable|exists:managers,id',
        ]);

        $chef->update($validated);

        return response()->json([
            'message' => 'Chef de projet updated successfully',
            'data' => $chef,
        ]);
    }

    /**
     * Delete a chef de projet.
     */
    public function destroy($id)
    {
        $chef = ChefDeProjet::findOrFail($id);

        $chef->delete();

        return response()->json(['message' => 'Chef de projet deleted successfully']);
    }

    /**
     * Get projects supervised by this chef de projet.
     */
    public function getSupervisedProjects(Request $request, $id)
    {
        $chef = ChefDeProjet::findOrFail($id);

        if ($this->userHasRole($request, 'chef_de_projet') && $this->currentChefDeProjetId($request) !== (int) $id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $projects = $chef->projects()->with('manager')->get();

        return response()->json([
            'chef_id' => $id,
            'projects' => $chef->projects()->with(['manager', 'chefDeProjet', 'slaProject'])->get(),
        ]);
    }

    /**
     * Get tasks validated by this chef de projet.
     */
    public function getValidatedTasks(Request $request, $id)
    {
        $chef = ChefDeProjet::findOrFail($id);

        if ($this->userHasRole($request, 'chef_de_projet') && $this->currentChefDeProjetId($request) !== (int) $id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $tasks = $chef->tasks()->with('project')->get();

        return response()->json([
            'chef_id' => $id,
            'tasks' => $tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'goal' => $task->goal ?? $task->description,
                    'description' => $task->description ?? $task->goal,
                    'status' => $task->status,
                    'project' => $task->project ? [
                        'id' => $task->project->id,
                        'name' => $task->project->name,
                    ] : null,
                    'validated_at' => $task->updated_at,
                ];
            }),
        ]);
    }

    /**
     * Assign chef de projet to supervise a project.
     */
    public function assignToProject(Request $request, $id)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
        ]);

        $chef = ChefDeProjet::findOrFail($id);
        $project = Project::findOrFail($request->project_id);

        // Check if project already has a chef de projet
        if ($project->chef_de_projet_id) {
            return response()->json(['message' => 'Project already has a chef de projet assigned'], 400);
        }

        $chef->superviseProject($project);

        return response()->json(['message' => 'Chef de projet assigned to project successfully']);
    }

    /**
     * Validate a task deliverable.
     */
    public function validateTask(Request $request, $id)
    {
        $request->validate([
            'task_id' => 'required|exists:tasks,id',
        ]);

        $chef = ChefDeProjet::findOrFail($id);
        $task = Task::findOrFail($request->task_id);

        if ($this->currentChefDeProjetId($request) !== (int) $id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Check if task is in a validatable state
        if (!in_array($task->status, ['done', 'completed', 'in_progress'], true)) {
            return response()->json(['message' => 'Task must be done or in progress to be validated'], 400);
        }

        $chef->validateDeliverable($task);

        return response()->json(['message' => 'Task validated successfully']);
    }

    /**
     * Get statistics for a chef de projet.
     */
    public function getChefStats(Request $request, $id)
    {
        $chef = ChefDeProjet::findOrFail($id);

        if ($this->userHasRole($request, 'chef_de_projet') && $this->currentChefDeProjetId($request) !== (int) $id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $totalProjects = $chef->projects()->count();
        $activeProjects = $chef->projects()->where('deadline', '>', now())->count();
        $overdueProjects = $chef->projects()->where('deadline', '<', now())->count();

        $totalTasks = $chef->tasks()->count();
        $validatedTasks = $chef->tasks()->where('status', 'validated')->count();
        $pendingValidations = Task::where('chef_de_projet_id', $id)
                                  ->whereIn('status', ['done', 'completed'])
                                  ->count();

        return response()->json([
            'chef_id' => $id,
            'projects' => [
                'total' => $totalProjects,
                'active' => $activeProjects,
                'overdue' => $overdueProjects,
            ],
            'tasks' => [
                'total_validated' => $totalTasks,
                'validated' => $validatedTasks,
                'pending_validation' => $pendingValidations,
            ],
        ]);
    }

    /**
     * Get chefs de projet currently supervising active projects.
     */
    public function getActiveChefs(Request $request)
    {
        $activeChefs = $this->scopedChefsQuery($request)
            ->whereHas('projects', function ($query) {
                $query->where('deadline', '>', now());
            })
            ->with('manager')
            ->get();

        return response()->json($activeChefs);
    }

    /**
     * Search chefs de projet by name.
     */
    public function searchByName(Request $request, $name)
    {
        $chefs = $this->scopedChefsQuery($request)
            ->where('name', 'like', '%' . $name . '%')
            ->with('manager')
            ->get();

        return response()->json($chefs);
    }
}
