<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
    /**
     * Get all projects
     */
    public function index()
    {
        $projects = Project::with('manager', 'chefDeProjet', 'tasks', 'developers')->get();
        return response()->json($projects);
    }

    /**
     * Get a specific project
     */
    public function show($id)
    {
        $project = Project::with('manager', 'chefDeProjet', 'tasks', 'developers', 'slaProject')->find($id);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        return response()->json($project);
    }


    /**
     * Update a project
     */
    public function update(Request $request, $id)
    {
        $project = Project::find($id);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'deadline' => 'sometimes|nullable|date|after:today',
            'manager_id' => 'sometimes|required|exists:managers,id',
            'chef_de_projet_id' => 'sometimes|nullable|exists:chef_de_projets,id',
        ]);

        $project->update($validated);

        return response()->json([
            'message' => 'Project updated successfully',
            'data' => $project->load('manager', 'chefDeProjet'),
        ]);
    }

    /**
     * Delete a project
     */
    public function destroy($id)
    {
        $project = Project::find($id);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        // Delete the project folder if it exists
        if ($project->folder_path && Storage::exists($project->folder_path)) {
            Storage::deleteDirectory($project->folder_path);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }

    /**
     * Get tasks for a project
     */
    public function getTasks($projectId)
    {
        $project = Project::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $tasks = $project->tasks()->with('chefDeProjet', 'developers')->get();

        return response()->json($tasks);
    }

    /**
     * Get developers for a project
     */
    public function getDevelopers($projectId)
    {
        $project = Project::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $developers = $project->developers()->get();

        return response()->json($developers);
    }

    /**
     * Get project statistics
     */
    public function statistics($projectId)
    {
        $project = Project::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $stats = [
            'project_id' => $projectId,
            'project_name' => $project->name,
            'total_tasks' => $project->tasks()->count(),
            'total_developers' => $project->developers()->count(),
            'completed_tasks' => $project->tasks()->where('status', 'completed')->count(),
            'pending_tasks' => $project->tasks()->where('status', 'pending')->count(),
            'in_progress_tasks' => $project->tasks()->where('status', 'in_progress')->count(),
            'has_chef_de_projet' => $project->chef_de_projet_id !== null,
            'has_deadline' => $project->deadline !== null,
            'days_until_deadline' => $project->deadline ? now()->diffInDays($project->deadline, false) : null,
        ];

        return response()->json($stats);
    }

    /**
     * Get project progress
     */
    public function progress($projectId)
    {
        $project = Project::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $totalTasks = $project->tasks()->count();
        $completedTasks = $project->tasks()->where('status', 'completed')->count();
        $inProgressTasks = $project->tasks()->where('status', 'in_progress')->count();
        $pendingTasks = $project->tasks()->where('status', 'pending')->count();

        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

        return response()->json([
            'project_id' => $projectId,
            'project_name' => $project->name,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'in_progress_tasks' => $inProgressTasks,
            'pending_tasks' => $pendingTasks,
            'progress_percentage' => $progress,
        ]);
    }

    /**
     * Get project timeline
     */
    public function timeline($projectId)
    {
        $project = Project::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $timeline = [
            'project_created' => $project->created_at,
            'deadline' => $project->deadline,
            'tasks' => $project->tasks()->select('id', 'title', 'status', 'created_at', 'updated_at')->get(),
            'developers_assigned' => $project->developers()->select('developers.id', 'developers.name', 'developer_project.joined_at')
                ->get(),
        ];

        return response()->json($timeline);
    }

    /**
     * Get project SLA
     */
    public function getSla($projectId)
    {
        $project = Project::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $sla = $project->slaProject;

        if (!$sla) {
            return response()->json(['message' => 'No SLA found for this project'], 404);
        }

        return response()->json($sla);
    }

    /**
     * Create or update project SLA
     */
    public function updateSla(Request $request, $projectId)
    {
        $project = Project::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $validated = $request->validate([
            'response_time_hours' => 'required|integer|min:1',
            'resolution_time_days' => 'required|integer|min:1',
            'priority_level' => 'required|string|in:low,medium,high,critical',
        ]);

        $sla = $project->slaProject()->updateOrCreate(
            ['project_id' => $projectId],
            $validated
        );

        return response()->json([
            'message' => 'Project SLA updated successfully',
            'data' => $sla,
        ]);
    }

    /**
     * Get project files
     */
    public function getFiles($projectId)
    {
        $project = Project::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $files = $project->files()->get();

        return response()->json($files);
    }

    /**
     * Export project data
     */
    public function exportData($projectId)
    {
        $project = Project::with('manager', 'chefDeProjet', 'tasks', 'developers', 'slaProject')->find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $data = [
            'project' => $project,
            'tasks' => $project->tasks,
            'developers' => $project->developers,
            'sla' => $project->slaProject,
        ];

        return response()->json($data);
    }
}