<?php

namespace App\Http\Controllers;

use App\Models\Developer;
use App\Models\Task;
use App\Models\DeveloperTask;
use Illuminate\Http\Request;

class DeveloperTaskController extends Controller
{
    /**
     * Get all developer-task assignments
     */
    public function index()
    {
        $assignments = DeveloperTask::with(['developer', 'task'])->get();
        return response()->json($assignments);
    }

    /**
     * Get a specific assignment
     */
    public function show($developerId, $taskId)
    {
        $assignment = DeveloperTask::where('developer_id', $developerId)
            ->where('task_id', $taskId)
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        return response()->json($assignment);
    }

    /**
     * Assign a developer to a task
     */
    public function assignDeveloperToTask(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'developer_id' => 'required|exists:developers,id',
            'task_id' => 'required|exists:tasks,id',
            'role' => 'required|string|max:255',
        ]);

        // Check if developer exists
        $developer = Developer::find($validated['developer_id']);
        if (!$developer) {
            return response()->json(['message' => 'Developer not found'], 404);
        }

        // Check if task exists
        $task = Task::find($validated['task_id']);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        // Check if assignment already exists
        $existingAssignment = DeveloperTask::where('developer_id', $validated['developer_id'])
            ->where('task_id', $validated['task_id'])
            ->first();

        if ($existingAssignment) {
            return response()->json(['message' => 'Developer is already assigned to this task'], 409);
        }

        // Create the assignment
        $assignment = DeveloperTask::create([
            'developer_id' => $validated['developer_id'],
            'task_id' => $validated['task_id'],
            'role' => $validated['role'],
            'assigned_at' => now(),
        ]);

        return response()->json([
            'message' => 'Developer assigned to task successfully',
            'data' => $assignment,
        ], 201);
    }

    /**
     * Update an assignment
     */
    public function updateAssignment(Request $request, $developerId, $taskId)
    {
        // Find the assignment
        $assignment = DeveloperTask::where('developer_id', $developerId)
            ->where('task_id', $taskId)
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        // Validate the request
        $validated = $request->validate([
            'role' => 'sometimes|required|string|max:255',
            'assigned_at' => 'sometimes|nullable|date',
        ]);

        // Update the assignment
        $assignment->update($validated);

        return response()->json([
            'message' => 'Assignment updated successfully',
            'data' => $assignment,
        ]);
    }

    /**
     * Unassign a developer from a task
     */
    public function unassignDeveloperFromTask($developerId, $taskId)
    {
        $assignment = DeveloperTask::where('developer_id', $developerId)
            ->where('task_id', $taskId)
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        $assignment->delete();

        return response()->json(['message' => 'Developer unassigned from task successfully']);
    }

    /**
     * Get all tasks assigned to a developer
     */
    public function tasksByDeveloper($developerId)
    {
        $developer = Developer::find($developerId);
        if (!$developer) {
            return response()->json(['message' => 'Developer not found'], 404);
        }

        $tasks = $developer->tasks()->with('chefDeProjet', 'project')->get();

        return response()->json($tasks);
    }

    /**
     * Get all developers assigned to a task
     */
    public function developersByTask($taskId)
    {
        $task = Task::find($taskId);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $developers = $task->developers()->get();

        return response()->json($developers);
    }

    /**
     * Get tasks by developer and role
     */
    public function tasksByDeveloperAndRole($developerId, $role)
    {
        $developer = Developer::find($developerId);
        if (!$developer) {
            return response()->json(['message' => 'Developer not found'], 404);
        }

        $tasks = $developer->tasks()
            ->wherePivot('role', $role)
            ->get();

        return response()->json($tasks);
    }

    /**
     * Get all assignments by role
     */
    public function assignmentsByRole($role)
    {
        $assignments = DeveloperTask::where('role', $role)
            ->with('developer', 'task')
            ->get();

        return response()->json($assignments);
    }

    /**
     * Get tasks by developer and task status
     */
    public function tasksByDeveloperAndStatus($developerId, $status)
    {
        $developer = Developer::find($developerId);
        if (!$developer) {
            return response()->json(['message' => 'Developer not found'], 404);
        }

        $tasks = $developer->tasks()
            ->where('status', $status)
            ->get();

        return response()->json($tasks);
    }

    /**
     * Get task assignment details
     */
    public function assignmentDetails($developerId, $taskId)
    {
        $assignment = DeveloperTask::where('developer_id', $developerId)
            ->where('task_id', $taskId)
            ->with('developer', 'task')
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        return response()->json($assignment);
    }

    /**
     * Get count of developers assigned to a task
     */
    public function countDevelopersByTask($taskId)
    {
        $task = Task::find($taskId);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $count = $task->developers()->count();

        return response()->json(['task_id' => $taskId, 'developer_count' => $count]);
    }

    /**
     * Get count of tasks assigned to a developer
     */
    public function countTasksByDeveloper($developerId)
    {
        $developer = Developer::find($developerId);
        if (!$developer) {
            return response()->json(['message' => 'Developer not found'], 404);
        }

        $count = $developer->tasks()->count();

        return response()->json(['developer_id' => $developerId, 'task_count' => $count]);
    }

    /**
     * Bulk assign developers to a task
     */
    public function bulkAssignDevelopersToTask(Request $request, $taskId)
    {
        $task = Task::find($taskId);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $validated = $request->validate([
            'developers' => 'required|array',
            'developers.*.developer_id' => 'required|exists:developers,id',
            'developers.*.role' => 'required|string|max:255',
        ]);

        $assignments = [];
        foreach ($validated['developers'] as $dev) {
            $existingAssignment = DeveloperTask::where('developer_id', $dev['developer_id'])
                ->where('task_id', $taskId)
                ->first();

            if (!$existingAssignment) {
                $assignment = DeveloperTask::create([
                    'developer_id' => $dev['developer_id'],
                    'task_id' => $taskId,
                    'role' => $dev['role'],
                    'assigned_at' => now(),
                ]);
                $assignments[] = $assignment;
            }
        }

        return response()->json([
            'message' => 'Developers assigned to task successfully',
            'data' => $assignments,
        ], 201);
    }

    /**
     * Remove all developers from a task
     */
    public function removeAllDevelopersFromTask($taskId)
    {
        $task = Task::find($taskId);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $count = DeveloperTask::where('task_id', $taskId)->delete();

        return response()->json([
            'message' => "Removed $count developers from task",
            'removed_count' => $count,
        ]);
    }
}