<?php

use App\Http\Controllers\ChefDeProjetController;
use App\Http\Controllers\DeveloperController;
use App\Http\Controllers\DeveloperProjectController;
use App\Http\Controllers\DeveloperTaskController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::pattern('id', '[0-9]+');
Route::pattern('manager', '[0-9]+');
Route::pattern('managerId', '[0-9]+');
Route::pattern('projectId', '[0-9]+');
Route::pattern('taskId', '[0-9]+');
Route::pattern('developerId', '[0-9]+');
Route::pattern('chefDeProjetId', '[0-9]+');

// ── AI Chat Proxy (uses GROQ_API_KEY from .env, secured by Keycloak auth) ──
Route::middleware('keycloak.auth')->post('ai/chat', function (Request $request) {
    $messages  = $request->input('messages', []);
    $model     = $request->input('model', 'llama-3.1-8b-instant');
    $maxTokens = $request->input('max_tokens', 256);
    $temp      = $request->input('temperature', 0.7);

    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
        'Content-Type'  => 'application/json',
    ])->post('https://api.groq.com/openai/v1/chat/completions', [
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => $temp,
        'max_tokens'  => $maxTokens,
    ]);

    if ($response->failed()) {
        return response()->json(['error' => 'AI service unavailable'], 502);
    }

    return response()->json($response->json());
});

Route::post('auth/register', [\App\Http\Controllers\AuthController::class, 'register']);

Route::middleware('keycloak.auth')->group(function (): void {
    Route::get('auth/me', function (Request $request) {
        $claims = $request->attributes->get('keycloak_claims', []);
        $roles = $request->attributes->get('keycloak_roles', []);
        $actorIds = $request->attributes->get('keycloak_actor_ids', []);

        return response()->json([
            'user' => [
                'id' => $claims['sub'] ?? null,
                'username' => $claims['preferred_username'] ?? null,
                'name' => $claims['name'] ?? null,
                'email' => $claims['email'] ?? null,
                'given_name' => $claims['given_name'] ?? null,
                'family_name' => $claims['family_name'] ?? null,
                'roles' => $roles,
                'actor_ids' => $actorIds,
            ],
            'claims' => $claims,
        ]);
    });

    Route::middleware('keycloak.role:manager,chef_de_projet,developer')->group(function (): void {
        Route::get('tasks', [TaskController::class, 'index']);
        Route::get('tasks/{id}', [TaskController::class, 'show']);
        Route::get('tasks-by-status/{status}', [TaskController::class, 'tasksByStatus']);
        Route::get('tasks/{taskId}/sla', [TaskController::class, 'getSla']);
        Route::get('tasks/{taskId}/developers', [DeveloperTaskController::class, 'developersByTask']);
        Route::get('tasks/{taskId}/developers/count', [DeveloperTaskController::class, 'countDevelopersByTask']);

        Route::get('projects', [ProjectController::class, 'index']);
        Route::get('projects/{id}', [ProjectController::class, 'show']);
        Route::get('projects/{projectId}/tasks', [ProjectController::class, 'getTasks']);
        Route::get('projects/{projectId}/developers', [ProjectController::class, 'getDevelopers']);
        Route::get('projects/{projectId}/statistics', [ProjectController::class, 'statistics']);
        Route::get('projects/{projectId}/progress', [ProjectController::class, 'progress']);
        Route::get('projects/{projectId}/timeline', [ProjectController::class, 'timeline']);
        Route::get('projects/{projectId}/sla', [ProjectController::class, 'getSla']);
        Route::get('projects/{projectId}/files', [ProjectController::class, 'getFiles']);
        Route::get('projects/{projectId}/files/{fileId}', [ProjectFileController::class, 'show']);
        Route::get('projects/{projectId}/files/{fileId}/download', [ProjectFileController::class, 'download']);

        Route::get('developers', [DeveloperController::class, 'index']);
        Route::get('developers/active/list', [DeveloperController::class, 'getActiveDevelopers']);
        Route::get('developers/search/{name}', [DeveloperController::class, 'searchByName']);
        Route::get('developers/{developerId}', [DeveloperController::class, 'show']);
        Route::get('developers/{developerId}/stats', [DeveloperController::class, 'getDeveloperStats']);
        Route::get('developers/{developerId}/timeline', [DeveloperController::class, 'getDeveloperTimeline']);
        Route::get('developers/{developerId}/projects', [DeveloperProjectController::class, 'getDeveloperProjects']);
        Route::get('developers/{developerId}/tasks', [DeveloperTaskController::class, 'tasksByDeveloper']);
        Route::get('developers/{developerId}/tasks/status/{status}', [DeveloperTaskController::class, 'tasksByDeveloperAndStatus']);
        Route::get('developers/{developerId}/tasks/role/{role}', [DeveloperTaskController::class, 'tasksByDeveloperAndRole']);
        Route::get('developers/{developerId}/tasks/count', [DeveloperTaskController::class, 'countTasksByDeveloper']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    });

    Route::middleware('keycloak.role:manager,chef_de_projet')->group(function (): void {
        Route::get('tasks-by-chef-de-projet/{chefDeProjetId}', [TaskController::class, 'tasksByChefDeProjet']);
        Route::get('tasks-by-chef-de-projet-and-status/{chefDeProjetId}/{status}', [TaskController::class, 'tasksByChefDeProjetAndStatus']);

        Route::get('chefs-de-projet', [ChefDeProjetController::class, 'index']);
        Route::get('chefs-de-projet/active/list', [ChefDeProjetController::class, 'getActiveChefs']);
        Route::get('chefs-de-projet/search/{name}', [ChefDeProjetController::class, 'searchByName']);
        Route::get('chefs-de-projet/{id}', [ChefDeProjetController::class, 'show']);
        Route::get('chefs-de-projet/{id}/projects', [ChefDeProjetController::class, 'getSupervisedProjects']);
        Route::get('chefs-de-projet/{id}/tasks', [ChefDeProjetController::class, 'getValidatedTasks']);
        Route::get('chefs-de-projet/{id}/stats', [ChefDeProjetController::class, 'getChefStats']);
        
        // Task Management (Managers and Chefs)
        Route::post('tasks', [TaskController::class, 'store']);
        Route::match(['put', 'patch'], 'tasks/{id}', [TaskController::class, 'update']);
        Route::delete('tasks/{id}', [TaskController::class, 'destroy']);
        Route::patch('tasks/{id}/status', [TaskController::class, 'updateStatus']);
        Route::match(['put', 'patch'], 'tasks/{taskId}/sla', [TaskController::class, 'updateSla']);
        Route::post('tasks/{taskId}/developers/bulk', [DeveloperTaskController::class, 'bulkAssignDevelopersToTask']);
        Route::delete('tasks/{taskId}/developers', [DeveloperTaskController::class, 'removeAllDevelopersFromTask']);
    });

    Route::middleware('keycloak.role:manager')->group(function (): void {
        Route::post('developers', [DeveloperController::class, 'store']);
        Route::match(['put', 'patch'], 'developers/{developerId}', [DeveloperController::class, 'update']);
        Route::delete('developers/{developerId}', [DeveloperController::class, 'destroy']);

        Route::post('chefs-de-projet', [ChefDeProjetController::class, 'store']);
        Route::match(['put', 'patch'], 'chefs-de-projet/{id}', [ChefDeProjetController::class, 'update']);
        Route::delete('chefs-de-projet/{id}', [ChefDeProjetController::class, 'destroy']);
        Route::post('chefs-de-projet/{id}/assign-project', [ChefDeProjetController::class, 'assignToProject']);

        Route::get('managers', [ManagerController::class, 'index']);
        Route::get('managers/search', [ManagerController::class, 'search']);
        Route::post('managers', [ManagerController::class, 'store']);
        Route::get('managers/{id}', [ManagerController::class, 'show']);
        Route::match(['put', 'patch'], 'managers/{id}', [ManagerController::class, 'update']);
        Route::delete('managers/{id}', [ManagerController::class, 'destroy']);

        Route::match(['put', 'patch'], 'projects/{id}', [ProjectController::class, 'update']);
        Route::delete('projects/{id}', [ProjectController::class, 'destroy']);
        Route::match(['put', 'patch'], 'projects/{projectId}/sla', [ProjectController::class, 'updateSla']);
        Route::post('projects/{projectId}/files', [ProjectFileController::class, 'uploadFile']);
        Route::delete('projects/{projectId}/files/{fileId}', [ProjectFileController::class, 'destroy']);
        Route::get('projects/{projectId}/export', [ProjectController::class, 'exportData']);

        Route::get('developer-task-assignments', [DeveloperTaskController::class, 'index']);
        Route::post('developer-task-assignments', [DeveloperTaskController::class, 'assignDeveloperToTask']);
        Route::get('developer-task-assignments/role/{role}', [DeveloperTaskController::class, 'assignmentsByRole']);
        Route::get('developer-task-assignments/{developerId}/{taskId}', [DeveloperTaskController::class, 'show']);
        Route::match(['put', 'patch'], 'developer-task-assignments/{developerId}/{taskId}', [DeveloperTaskController::class, 'updateAssignment']);
        Route::delete('developer-task-assignments/{developerId}/{taskId}', [DeveloperTaskController::class, 'unassignDeveloperFromTask']);
        Route::get('developer-task-assignments/{developerId}/{taskId}/details', [DeveloperTaskController::class, 'assignmentDetails']);

        Route::get('projects/{projectId}/developer-assignments', [DeveloperProjectController::class, 'listProjectDevelopers']);
        Route::post('projects/{projectId}/developers/{developerId}', [DeveloperProjectController::class, 'assignDeveloperToProject']);
        Route::match(['put', 'patch'], 'projects/{projectId}/developers/{developerId}', [DeveloperProjectController::class, 'updateDeveloperRole']);
        Route::delete('projects/{projectId}/developers/{developerId}', [DeveloperProjectController::class, 'removeDeveloperFromProject']);
        Route::get('projects/{projectId}/developers/{developerId}/history', [DeveloperProjectController::class, 'projectDeveloperHistory']);
        Route::post('projects/{projectId}/developers/bulk', [DeveloperProjectController::class, 'bulkAssignDevelopers']);
    });

    Route::middleware(['keycloak.role:chef_de_projet', 'actor.route:chef_de_projet,id'])->group(function (): void {
        Route::post('chefs-de-projet/{id}/validate-task', [ChefDeProjetController::class, 'validateTask']);
    });

    Route::middleware(['keycloak.role:manager', 'actor.route:manager,managerId'])->group(function (): void {
        Route::get('managers/{managerId}/projects', [ManagerController::class, 'getProjects']);
        Route::post('managers/{managerId}/projects', [ManagerController::class, 'createProject']);
        Route::get('managers/{managerId}/projects/{projectId}', [ManagerController::class, 'projectDetails']);
        Route::get('managers/{managerId}/projects/{projectId}/assignment-data', [ManagerController::class, 'projectAssignmentData']);
        Route::post('managers/{managerId}/projects/{projectId}/assignments', [ManagerController::class, 'saveProjectAssignments']);
        Route::match(['put', 'patch'], 'managers/{managerId}/projects/{projectId}', [ManagerController::class, 'updateProject']);
        Route::delete('managers/{managerId}/projects/{projectId}', [ManagerController::class, 'deleteProject']);
        Route::get('managers/{managerId}/developers', [ManagerController::class, 'getDevelopers']);
        Route::post('managers/{managerId}/developers', [ManagerController::class, 'assignDeveloper']);
        Route::delete('managers/{managerId}/developers/{developerId}', [ManagerController::class, 'removeDeveloper']);
        Route::post('managers/{managerId}/developers/bulk', [ManagerController::class, 'bulkAssignDevelopers']);
        Route::get('managers/{managerId}/chefs-de-projet', [ManagerController::class, 'getChefsDeProjets']);
        Route::post('managers/{managerId}/chefs-de-projet', [ManagerController::class, 'assignChefDeProjet']);
        Route::delete('managers/{managerId}/chefs-de-projet/{chefDeProjetId}', [ManagerController::class, 'removeChefDeProjet']);
        Route::get('managers/{managerId}/counts/projects', [ManagerController::class, 'projectCount']);
        Route::get('managers/{managerId}/counts/developers', [ManagerController::class, 'developerCount']);
        Route::get('managers/{managerId}/counts/chefs-de-projet', [ManagerController::class, 'chefDeProjetCount']);
        Route::get('managers/{managerId}/statistics', [ManagerController::class, 'statistics']);
        Route::get('managers/{managerId}/export', [ManagerController::class, 'exportData']);
    });
});
