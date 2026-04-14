<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Developer;
use App\Models\Task;

class DeveloperController extends Controller
{
    /**
     * List all developers.
     */
    public function index()
    {
        $developers = Developer::with('manager')->get();

        return response()->json($developers);
    }

    /**
     * Retrieve details of a single developer.
     */
    public function show($developerId)
    {
        $developer = Developer::with('manager', 'projects', 'tasks')->findOrFail($developerId);

        return response()->json([
            'id' => $developer->id,
            'name' => $developer->name,
            'email' => $developer->email,
            'manager' => $developer->manager,
            'projects' => $developer->projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'position' => $project->pivot->position,
                    'joined_at' => $project->pivot->joined_at,
                ];
            }),
            'tasks' => $developer->tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'role' => $task->pivot->role,
                    'assigned_at' => $task->pivot->assigned_at,
                ];
            }),
        ]);
    }

    /**
     * Create a new developer with validation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:developers,email',
            'manager_id' => 'nullable|exists:managers,id',
        ]);

        $developer = Developer::create($validated);

        return response()->json([
            'message' => 'Developer created successfully',
            'data' => $developer,
        ], 201);
    }

    /**
     * Update developer information.
     */
    public function update(Request $request, $developerId)
    {
        $developer = Developer::findOrFail($developerId);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:developers,email,' . $developerId,
            'manager_id' => 'nullable|exists:managers,id',
        ]);

        $developer->update($validated);

        return response()->json([
            'message' => 'Developer updated successfully',
            'data' => $developer,
        ]);
    }

    /**
     * Delete a developer from the system.
     */
    public function destroy($developerId)
    {
        $developer = Developer::findOrFail($developerId);

        $developer->delete();

        return response()->json(['message' => 'Developer deleted successfully']);
    }

    /**
     * Search developers by name.
     */
    public function searchByName($name)
    {
        $developers = Developer::where('name', 'like', '%' . $name . '%')
                               ->with('manager')
                               ->get();

        return response()->json($developers);
    }

    /**
     * Return statistics for a developer.
     */
    public function getDeveloperStats($developerId)
    {
        $developer = Developer::findOrFail($developerId);

        $totalProjects = $developer->projects()->count();
        $totalTasks = $developer->tasks()->count();
        $completedTasks = $developer->tasks()->whereIn('status', ['done', 'completed', 'validated'])->count();
        $pendingTasks = $developer->tasks()->where('status', 'pending')->count();
        $inProgressTasks = $developer->tasks()->where('status', 'in_progress')->count();

        return response()->json([
            'developer_id' => $developerId,
            'total_projects' => $totalProjects,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'pending_tasks' => $pendingTasks,
            'in_progress_tasks' => $inProgressTasks,
        ]);
    }

    /**
     * List developers currently active in projects or tasks.
     */
    public function getActiveDevelopers()
    {
        $activeDevelopers = Developer::whereHas('projects')
                                     ->orWhereHas('tasks', function ($query) {
                                         $query->whereNotIn('status', ['done', 'completed', 'validated']);
                                     })
                                     ->with('manager')
                                     ->get();

        return response()->json($activeDevelopers);
    }

    /**
     * Show a timeline of developer activity.
     */
    public function getDeveloperTimeline($developerId)
    {
        $developer = Developer::findOrFail($developerId);

        $timeline = [];

        // Project assignments
        $projects = $developer->projects()->with('manager')->get();
        foreach ($projects as $project) {
            $timeline[] = [
                'type' => 'project_assignment',
                'date' => $project->pivot->joined_at,
                'title' => 'Assigned to project: ' . $project->name,
                'details' => [
                    'project_id' => $project->id,
                    'position' => $project->pivot->position,
                    'manager' => $project->manager ? $project->manager->name : null,
                ],
            ];
        }

        // Task assignments
        $tasks = $developer->tasks()->with('project')->get();
        foreach ($tasks as $task) {
            $timeline[] = [
                'type' => 'task_assignment',
                'date' => $task->pivot->assigned_at,
                'title' => 'Assigned to task: ' . $task->title,
                'details' => [
                    'task_id' => $task->id,
                    'role' => $task->pivot->role,
                    'status' => $task->status,
                    'project' => $task->project ? $task->project->name : null,
                ],
            ];
        }

        // Sort timeline by date
        usort($timeline, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return response()->json([
            'developer_id' => $developerId,
            'timeline' => $timeline,
        ]);
    }
}
