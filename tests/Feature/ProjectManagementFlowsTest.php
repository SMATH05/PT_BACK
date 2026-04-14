<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateWithKeycloak;
use App\Models\ChefDeProjet;
use App\Models\Developer;
use App\Models\Manager;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectManagementFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(AuthenticateWithKeycloak::class);
    }

    public function test_project_sla_endpoint_accepts_frontend_field_names(): void
    {
        $project = Project::factory()->create();

        $response = $this->patchJson("/api/projects/{$project->id}/sla", [
            'response_time_hours' => 4,
            'resolution_time_days' => 7,
            'priority_level' => 'high',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.max_response_time', 4)
            ->assertJsonPath('data.max_resolution_time', 7)
            ->assertJsonPath('data.priority', 'high');

        $this->assertDatabaseHas('sla_projects', [
            'project_id' => $project->id,
            'max_response_time' => 4,
            'max_resolution_time' => 7,
            'priority' => 'high',
        ]);
    }

    public function test_developer_must_belong_to_project_before_task_assignment(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'chef_de_projet_id' => $project->chef_de_projet_id,
        ]);
        $developer = Developer::factory()->create();

        $response = $this->postJson('/api/developer-task-assignments', [
            'developer_id' => $developer->id,
            'task_id' => $task->id,
            'role' => 'backend',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Developer must be assigned to the project before being assigned to the task');
    }

    public function test_replacing_project_developers_cleans_removed_task_assignments(): void
    {
        $manager = Manager::factory()->create();
        $chef = ChefDeProjet::factory()->create(['manager_id' => $manager->id]);
        $project = Project::factory()->create([
            'manager_id' => $manager->id,
            'chef_de_projet_id' => $chef->id,
        ]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'chef_de_projet_id' => $chef->id,
            'status' => 'in_progress',
        ]);
        $removedDeveloper = Developer::factory()->create(['manager_id' => $manager->id]);
        $keptDeveloper = Developer::factory()->create(['manager_id' => $manager->id]);

        $project->developers()->attach($removedDeveloper->id, [
            'position' => 'frontend',
            'joined_at' => now(),
        ]);
        $project->developers()->attach($keptDeveloper->id, [
            'position' => 'backend',
            'joined_at' => now(),
        ]);
        $task->developers()->attach($removedDeveloper->id, [
            'role' => 'frontend',
            'assigned_at' => now(),
        ]);

        $response = $this->postJson("/api/managers/{$manager->id}/projects/{$project->id}/assignments", [
            'replace_project_developers' => true,
            'project_developers' => [
                [
                    'developer_id' => $keptDeveloper->id,
                    'position' => 'lead backend',
                ],
            ],
            'task_assignments' => [
                [
                    'task_id' => $task->id,
                    'developers' => [
                        [
                            'developer_id' => $keptDeveloper->id,
                            'role' => 'reviewer',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('developer_project', [
            'project_id' => $project->id,
            'developer_id' => $removedDeveloper->id,
        ]);
        $this->assertDatabaseMissing('developer_task', [
            'task_id' => $task->id,
            'developer_id' => $removedDeveloper->id,
        ]);
        $this->assertDatabaseHas('developer_project', [
            'project_id' => $project->id,
            'developer_id' => $keptDeveloper->id,
            'position' => 'lead backend',
        ]);
        $this->assertDatabaseHas('developer_task', [
            'task_id' => $task->id,
            'developer_id' => $keptDeveloper->id,
            'role' => 'reviewer',
        ]);
    }

    public function test_project_files_can_be_uploaded_listed_downloaded_and_deleted(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create([
            'folder_path' => 'projects/test-project',
        ]);

        $uploadResponse = $this->post("/api/projects/{$project->id}/files", [
            'file' => UploadedFile::fake()->create('specification.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $uploadResponse
            ->assertCreated()
            ->assertJsonPath('data.filename', 'specification.pdf');

        $fileId = $uploadResponse->json('data.id');
        $filepath = $uploadResponse->json('data.filepath');

        $this->assertStringContainsString('projects/test-project/files/', $filepath);
        Storage::disk('local')->assertExists($filepath);
        $this->assertDatabaseHas('project_files', [
            'id' => $fileId,
            'project_id' => $project->id,
            'filename' => 'specification.pdf',
        ]);

        $this->getJson("/api/projects/{$project->id}/files")
            ->assertOk()
            ->assertJsonPath('0.id', $fileId)
            ->assertJsonPath('0.download_url', url("/api/projects/{$project->id}/files/{$fileId}/download"));

        $this->get("/api/projects/{$project->id}/files/{$fileId}/download")
            ->assertOk();

        $this->deleteJson("/api/projects/{$project->id}/files/{$fileId}")
            ->assertOk()
            ->assertJsonPath('message', 'File deleted successfully');

        Storage::disk('local')->assertMissing($filepath);
        $this->assertDatabaseMissing('project_files', ['id' => $fileId]);
    }

    public function test_creating_project_scaffolds_a_workspace_structure(): void
    {
        Storage::fake('local');

        $manager = Manager::factory()->create();

        $response = $this->postJson("/api/managers/{$manager->id}/projects", [
            'name' => 'Workspace Project',
            'client' => 'Acme',
            'description' => 'Project with scaffold',
            'status' => 'pending',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.folder_path', 'projects/Workspace_Project')
            ->assertJsonPath('data.workspace_exists', true);

        Storage::disk('local')->assertExists('projects/Workspace_Project/README.md');
        Storage::disk('local')->assertExists('projects/Workspace_Project/project.json');
        Storage::disk('local')->assertExists('projects/Workspace_Project/docs/.gitkeep');
        Storage::disk('local')->assertExists('projects/Workspace_Project/files/.gitkeep');
        Storage::disk('local')->assertExists('projects/Workspace_Project/src/.gitkeep');
        Storage::disk('local')->assertExists('projects/Workspace_Project/tasks/.gitkeep');
        Storage::disk('local')->assertExists('projects/Workspace_Project/deliverables/.gitkeep');
        Storage::disk('local')->assertExists('projects/Workspace_Project/notes/.gitkeep');
    }
}
