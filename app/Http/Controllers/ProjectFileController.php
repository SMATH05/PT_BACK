<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithActorScope;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProjectFileController extends Controller
{
    use InteractsWithActorScope;

    public function uploadFile(Request $request, $projectId)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $project = Project::find($projectId);

        if (! $project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        if (! $this->canManageProject($request, $project)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $file = $validated['file'];
        $disk = config('filesystems.default', 'local');
        $baseFolderPath = $project->folder_path ?: 'projects/' . $project->id;
        $folderPath = $baseFolderPath . '/files';
        $uniqueFilename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();

        Storage::disk($disk)->makeDirectory($folderPath);
        $filepath = $file->storeAs($folderPath, $uniqueFilename, $disk);

        $projectFile = ProjectFile::create([
            'project_id' => $projectId,
            'filename' => $file->getClientOriginalName(),
            'filepath' => $filepath,
            'disk' => $disk,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'message' => 'File uploaded successfully',
            'data' => $this->formatProjectFile($projectFile),
        ], 201);
    }

    public function show(Request $request, $projectId, $fileId): Response
    {
        $project = Project::find($projectId);
        abort_unless($project !== null, 404, 'Project not found');
        abort_unless($this->canAccessProject($request, $project), 403, 'Forbidden');

        $projectFile = $this->findProjectFile($projectId, $fileId);
        $disk = $projectFile->disk ?? config('filesystems.default', 'local');

        abort_unless(Storage::disk($disk)->exists($projectFile->filepath), 404, 'File not found on disk');

        return Storage::disk($disk)->response(
            $projectFile->filepath,
            $projectFile->filename,
            ['Content-Type' => $projectFile->mime_type ?? 'application/octet-stream']
        );
    }

    public function download(Request $request, $projectId, $fileId): Response
    {
        $project = Project::find($projectId);
        abort_unless($project !== null, 404, 'Project not found');
        abort_unless($this->canAccessProject($request, $project), 403, 'Forbidden');

        $projectFile = $this->findProjectFile($projectId, $fileId);
        $disk = $projectFile->disk ?? config('filesystems.default', 'local');

        abort_unless(Storage::disk($disk)->exists($projectFile->filepath), 404, 'File not found on disk');

        return Storage::disk($disk)->download($projectFile->filepath, $projectFile->filename);
    }

    public function destroy(Request $request, $projectId, $fileId)
    {
        $project = Project::find($projectId);
        if (! $project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        if (! $this->canManageProject($request, $project)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $projectFile = $this->findProjectFile($projectId, $fileId);
        $disk = $projectFile->disk ?? config('filesystems.default', 'local');

        if (Storage::disk($disk)->exists($projectFile->filepath)) {
            Storage::disk($disk)->delete($projectFile->filepath);
        }

        $projectFile->delete();

        return response()->json(['message' => 'File deleted successfully']);
    }

    private function findProjectFile($projectId, $fileId): ProjectFile
    {
        $project = Project::find($projectId);
        abort_unless($project !== null, 404, 'Project not found');

        $projectFile = $project->files()->find($fileId);
        abort_unless($projectFile !== null, 404, 'File not found');

        return $projectFile;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProjectFile(ProjectFile $projectFile): array
    {
        return [
            'id' => $projectFile->id,
            'project_id' => $projectFile->project_id,
            'filename' => $projectFile->filename,
            'filepath' => $projectFile->filepath,
            'disk' => $projectFile->disk,
            'mime_type' => $projectFile->mime_type,
            'size' => $projectFile->size,
            'view_url' => url("/api/projects/{$projectFile->project_id}/files/{$projectFile->id}"),
            'download_url' => url("/api/projects/{$projectFile->project_id}/files/{$projectFile->id}/download"),
            'created_at' => $projectFile->created_at,
        ];
    }
}
