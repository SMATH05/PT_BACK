<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithActorScope;
use App\Models\Task;
use App\Notifications\TaskCompleted;
use App\Notifications\ProjectCompleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    use InteractsWithActorScope;

    public function index(Request $request)
    {
        return response()->json(
            $this->scopedTasksQuery($request)
                ->with(['project', 'chefDeProjet', 'developers', 'slaTask'])
                ->get()
        );
    }

    public function show(Request $request, $id)
    {
        $task = Task::with(['project', 'chefDeProjet', 'developers', 'slaTask'])->find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        if (! $this->canAccessTask($request, $task)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($task);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->taskRules());
        $payload = $this->normalizeTaskPayload($validated);

        if (isset($payload['status'])) {
            $payload['status'] = $this->ensureValidStatusForRole($request, $payload['status']);
        }

        $task = Task::create($payload);

        return response()->json($task->load(['project', 'chefDeProjet', 'developers', 'slaTask']), 201);
    }

    public function update(Request $request, $id)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        if (! $this->canManageTask($request, $task)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate($this->taskRules(true));
        $payload = $this->normalizeTaskPayload($validated);

        if (isset($payload['status'])) {
            $payload['status'] = $this->ensureValidStatusForRole($request, $payload['status']);
        }

        $task->update($payload);

        return response()->json($task->load(['project', 'chefDeProjet', 'developers', 'slaTask']));
    }

    public function destroy(Request $request, $id)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        if (! $this->canManageTask($request, $task)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }

    public function tasksByChefDeProjet(Request $request, $chefDeProjetId)
    {
        if ($this->userHasRole($request, 'chef_de_projet') && $this->currentChefDeProjetId($request) !== (int) $chefDeProjetId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(
            Task::with(['project', 'developers', 'slaTask'])
                ->where('chef_de_projet_id', $chefDeProjetId)
                ->get()
        );
    }

    public function tasksByStatus(Request $request, $status)
    {
        return response()->json(
            $this->scopedTasksQuery($request)
                ->with(['project', 'chefDeProjet', 'developers', 'slaTask'])
                ->where('status', $this->normalizeStatus($status))
                ->get()
        );
    }

    public function tasksByChefDeProjetAndStatus(Request $request, $chefDeProjetId, $status)
    {
        if ($this->userHasRole($request, 'chef_de_projet') && $this->currentChefDeProjetId($request) !== (int) $chefDeProjetId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(
            Task::with(['project', 'developers', 'slaTask'])
                ->where('chef_de_projet_id', $chefDeProjetId)
                ->where('status', $this->normalizeStatus($status))
                ->get()
        );
    }

    public function updateStatus(Request $request, $id)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        if (! $this->canAccessTask($request, $task)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'in_progress', 'done', 'completed', 'validated', 'waiting_validation'])],
        ]);

        $oldStatus = $task->status;
        $newStatus = $this->ensureValidStatusForRole($request, $this->normalizeStatus($validated['status']));

        $task->update(['status' => $newStatus]);

        // Trigger notifications
        if ($newStatus === 'waiting_validation' && $oldStatus !== 'waiting_validation') {
            $chef = $task->chefDeProjet ?? $task->project->chefDeProjet;
            if ($chef) {
                // We'll use the TaskCompleted notification but with a "requesting validation" context if possible, 
                // or just reuse it as a "Status Change" notification.
                $chef->notify(new TaskCompleted($task)); 
            }
        }

        if ($newStatus === 'done' && $oldStatus !== 'done') {
            // Already handled by existing logic or will be handled by Chef validation
            $chef = $task->chefDeProjet ?? $task->project->chefDeProjet;
            if ($chef) {
                $chef->notify(new TaskCompleted($task));
            }

            // Check if all project tasks are completed
            $project = $task->project;
            if ($project) {
                $totalTasks = $project->tasks()->count();
                $completedTasks = $project->tasks()->whereIn('status', ['done', 'validated'])->count();

                if ($totalTasks > 0 && $completedTasks === $totalTasks) {
                    $manager = $project->manager;
                    if ($manager) {
                        $manager->notify(new ProjectCompleted($project));
                    }
                }
            }
        }

        return response()->json($task->load(['project', 'chefDeProjet', 'developers', 'slaTask']));
    }

    public function getSla(Request $request, $taskId)
    {
        $task = Task::find($taskId);

        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        if (! $this->canAccessTask($request, $task)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $sla = $task->slaTask;

        if (! $sla) {
            return response()->json(['message' => 'No SLA found for this task'], 404);
        }

        return response()->json($sla);
    }

    public function updateSla(Request $request, $taskId)
    {
        $task = Task::find($taskId);

        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        if (! $this->canManageTask($request, $task)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'max_response_time' => 'required|integer|min:1',
            'max_resolution_time' => 'required|integer|min:1',
            'priority' => 'required|string|in:low,medium,high,critical',
        ]);

        $validated['name'] = $validated['name'] ?? sprintf('%s SLA', $task->title);

        $sla = $task->slaTask()->updateOrCreate(
            ['task_id' => $taskId],
            $validated
        );

        return response()->json([
            'message' => 'Task SLA updated successfully',
            'data' => $sla,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function taskRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return [
            'title' => "{$required}|string|max:255",
            'goal' => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
            'status' => [$required, 'string', Rule::in(['pending', 'in_progress', 'done', 'completed', 'validated', 'waiting_validation'])],
            'project_id' => "{$required}|exists:projects,id",
            'chef_de_projet_id' => 'sometimes|nullable|exists:chef_de_projets,id',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeTaskPayload(array $payload): array
    {
        if (array_key_exists('status', $payload)) {
            $payload['status'] = $this->normalizeStatus((string) $payload['status']);
        }

        $hasGoalColumn = Schema::hasColumn('tasks', 'goal');
        $hasDescriptionColumn = Schema::hasColumn('tasks', 'description');

        // Sync goal and description if one is missing but the other is present
        if (!empty($payload['description']) && empty($payload['goal'])) {
            $payload['goal'] = $payload['description'];
        } elseif (!empty($payload['goal']) && empty($payload['description'])) {
            $payload['description'] = $payload['goal'];
        }

        // Clean up fields that don't exist in the table
        if (! $hasGoalColumn) {
            unset($payload['goal']);
        }

        if (! $hasDescriptionColumn) {
            unset($payload['description']);
        }

        return $payload;
    }

    private function normalizeStatus(string $status): string
    {
        return $status === 'completed' ? 'done' : $status;
    }

    private function ensureValidStatusForRole(Request $request, string $status): string
    {
        $status = $this->normalizeStatus($status);

        if ($this->userHasRole($request, 'developer') && ! $this->userHasRole($request, 'manager', 'chef_de_projet')) {
            if (in_array($status, ['done', 'validated'])) {
                return 'waiting_validation';
            }
        }

        return $status;
    }
}
