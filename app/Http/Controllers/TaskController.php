<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index()
    {
        return response()->json(
            Task::with(['project', 'chefDeProjet', 'developers', 'slaTask'])->get()
        );
    }

    public function show($id)
    {
        $task = Task::with(['project', 'chefDeProjet', 'developers', 'slaTask'])->find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        return response()->json($task);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->taskRules());
        $task = Task::create($this->normalizeTaskPayload($validated));

        return response()->json($task->load(['project', 'chefDeProjet', 'developers', 'slaTask']), 201);
    }

    public function update(Request $request, $id)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $validated = $request->validate($this->taskRules(true));
        $task->update($this->normalizeTaskPayload($validated));

        return response()->json($task->load(['project', 'chefDeProjet', 'developers', 'slaTask']));
    }

    public function destroy($id)
    {
        $task = Task::find($id);

        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }

    public function tasksByChefDeProjet($chefDeProjetId)
    {
        return response()->json(
            Task::with(['project', 'developers', 'slaTask'])
                ->where('chef_de_projet_id', $chefDeProjetId)
                ->get()
        );
    }

    public function tasksByStatus($status)
    {
        return response()->json(
            Task::with(['project', 'chefDeProjet', 'developers', 'slaTask'])
                ->where('status', $this->normalizeStatus($status))
                ->get()
        );
    }

    public function tasksByChefDeProjetAndStatus($chefDeProjetId, $status)
    {
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

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'in_progress', 'done', 'completed', 'validated'])],
        ]);

        $task->update(['status' => $this->normalizeStatus($validated['status'])]);

        return response()->json($task->load(['project', 'chefDeProjet', 'developers', 'slaTask']));
    }

    public function getSla($taskId)
    {
        $task = Task::find($taskId);

        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
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
            'status' => [$required, 'string', Rule::in(['pending', 'in_progress', 'done', 'completed', 'validated'])],
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

        if (array_key_exists('description', $payload) && ! array_key_exists('goal', $payload)) {
            $payload['goal'] = $payload['description'];
        }

        if (array_key_exists('goal', $payload) && ! array_key_exists('description', $payload)) {
            $payload['description'] = $payload['goal'];
        }

        return $payload;
    }

    private function normalizeStatus(string $status): string
    {
        return $status === 'completed' ? 'done' : $status;
    }
}
