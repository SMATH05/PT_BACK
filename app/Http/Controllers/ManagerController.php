<?php

namespace App\Http\Controllers;

use App\Models\Manager;
use App\Models\Project;
use App\Models\Developer;
use App\Models\ChefDeProjet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ManagerController extends Controller
{
    /**
     * Get all managers
     */
    public function index()
    {
        $managers = Manager::with('projects', 'developers', 'chefDeProjets')->get();
        return response()->json($managers);
    }

    /**
     * Get a specific manager
     */
    public function show($id)
    {
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
            'name' => 'required|string|max:255|unique:managers',
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
        $manager = Manager::find($id);

        if (!$manager) {
            return response()->json(['message' => 'Manager not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:managers,name,' . $id,
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
    public function destroy($id)
    {
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
            'description' => 'nullable|string',
            'deadline' => 'nullable|date|after:today',
            'chef_de_projet_id' => 'nullable|exists:chef_de_projets,id',
        ]);

        // Create a safe folder name (replace spaces and special characters with underscores)
        $safeFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $validated['name']);
        $safeFolderName = preg_replace('/_+/', '_', $safeFolderName); // Replace multiple underscores with single
        $safeFolderName = trim($safeFolderName, '_'); // Remove leading/trailing underscores

        // Create the folder path
        $folderPath = 'projects/' . $safeFolderName;
        $fullPath = storage_path('app/' . $folderPath);

        // Create folder if it doesn't exist
        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        // Create the project record
        $project = Project::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'deadline' => $validated['deadline'] ?? null,
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
            'description' => 'sometimes|nullable|string',
            'chef_de_projet_id' => 'sometimes|nullable|exists:chef_de_projets,id',
        ]);

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