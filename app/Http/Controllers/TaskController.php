<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;

class TaskController extends Controller
{
    public function index()
    {
        // جيب جميع الـtasks من DB
        $tasks = Task::all();

        // رجعهم كـ JSON
        return response()->json($tasks);
    }
    public function show($id)
    {
        // جيب الـtask بالـid المحدد
        $task = Task::with('chefDeProjet')->find($id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
 
        // رجع الـtask كـ JSON
        return response()->json($task);
    }
    function store(Request $request)
    {
        // تحقق من البيانات المدخلة
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|string|in:pending,in_progress,completed',
            'chef_de_projet_id' => 'required|exists:users,id',
        ]);

        // أنشئ الـtask الجديد
        $task = Task::create($validatedData);

        // رجع الـtask الجديد كـ JSON
        return response()->json($task, 201);
    }
    function update(Request $request, $id)
    {
        // تحقق من البيانات المدخلة
        $validatedData = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|required|string|in:pending,in_progress,completed',
            'chef_de_projet_id' => 'sometimes|required|exists:users,id',
        ]);

        // جيب الـtask بالـid المحدد
        $task = Task::find($id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        // حدث الـtask بالبيانات الجديدة
        $task->update($validatedData);

        // رجع الـtask المحدث كـ JSON
        return response()->json($task);
    }
    function destroy($id)
    {
        // جيب الـtask بالـid المحدد
        $task = Task::find($id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        // احذف الـtask
        $task->delete();

        // رجع رسالة نجاح
        return response()->json(['message' => 'Task deleted successfully']);
    }
    function tasksByChefDeProjet($chefDeProjetId)
    {
        // جيب كل الـtasks اللي عندهم chef_de_projet_id يساوي $chefDeProjetId
        $tasks = Task::where('chef_de_projet_id', $chefDeProjetId)->get();

        // رجعهم كـ JSON
        return response()->json($tasks);
    }
    function tasksByStatus($status)
    {
        // جيب كل الـtasks اللي عندهم status يساوي $status
        $tasks = Task::where('status', $status)->get();

        // رجعهم كـ JSON
        return response()->json($tasks);
    }
    function tasksByChefDeProjetAndStatus($chefDeProjetId, $status)
    {
        // جيب كل الـtasks اللي عندهم chef_de_projet_id يساوي $chefDeProjetId و status يساوي $status
        $tasks = Task::where('chef_de_projet_id', $chefDeProjetId)
                     ->where('status', $status)
                     ->get();

        // رجعهم كـ JSON
        return response()->json($tasks);
    }
    function updateStatus(Request $request, $id)
    {
        // تحقق من البيانات المدخلة
        $validatedData = $request->validate([
            'status' => 'required|string|in:pending,in_progress,completed',
        ]);

        // جيب الـtask بالـid المحدد
        $task = Task::find($id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        // حدث حالة الـtask
        $task->status = $validatedData['status'];
        $task->save();

        // رجع الـtask المحدث كـ JSON
        return response()->json($task);
    }

    /**
     * Get task SLA
     */
    public function getSla($taskId)
    {
        $task = Task::find($taskId);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $sla = $task->slaTask;

        if (!$sla) {
            return response()->json(['message' => 'No SLA found for this task'], 404);
        }

        return response()->json($sla);
    }

    /**
     * Create or update task SLA
     */
    public function updateSla(Request $request, $taskId)
    {
        $task = Task::find($taskId);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'max_response_time' => 'required|integer|min:1',
            'max_resolution_time' => 'required|integer|min:1',
            'priority' => 'required|string|in:low,medium,high,critical',
        ]);

        $sla = $task->slaTask()->updateOrCreate(
            ['task_id' => $taskId],
            $validated
        );

        return response()->json([
            'message' => 'Task SLA updated successfully',
            'data' => $sla,
        ]);
    }
}
