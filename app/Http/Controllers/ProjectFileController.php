<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\Request;

class ProjectFileController extends Controller
{
    /**
     * Upload a file to a project
     */
    public function uploadFile(Request $request, $projectId)
    {
        // Validate the request
        $validated = $request->validate([
            'file' => 'required|file|max:10240', // 10MB in kilobytes
        ]);

        // Get the project
        $project = Project::find($projectId);

        if (!$project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        // Get the uploaded file
        $file = $request->file('file');

        // Get the original filename
        $originalFilename = $file->getClientOriginalName();

        // Create a unique filename to avoid conflicts
        $uniqueFilename = time() . '_' . uniqid() . '_' . $originalFilename;

        // Get the project's folder path
        $projectFolderPath = storage_path('app/' . $project->folder_path);

        // Ensure the project folder exists
        if (!file_exists($projectFolderPath)) {
            mkdir($projectFolderPath, 0755, true);
        }

        // Store the file in the project's folder
        $file->move($projectFolderPath, $uniqueFilename);

        // Create the filepath for database storage (relative path)
        $filepath = $project->folder_path . '/' . $uniqueFilename;

        // Create the project file record
        $projectFile = ProjectFile::create([
            'project_id' => $projectId,
            'filename' => $originalFilename,
            'filepath' => $filepath,
        ]);

        // Return JSON response with the uploaded file info
        return response()->json([
            'message' => 'File uploaded successfully',
            'data' => $projectFile,
        ], 201);
    }
}
