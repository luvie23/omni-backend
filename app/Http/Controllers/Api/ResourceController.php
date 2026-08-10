<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResourceController extends Controller
{
    /**
     * Get all resources.
     */
    public function index(Request $request)
    {
        $resources = Resource::query()
            ->with('creator:id,name')
            ->latest()
            ->paginate(20);

        return response()->json($resources);
    }

    /**
     * Get a single resource.
     */
    public function show(Resource $resource)
    {
        $resource->load('creator:id,name');

        return response()->json($resource);
    }

    /**
     * Upload a new resource.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'file' => [
                'required',
                'file',
                'max:51200', // 50 MB
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png',
            ],
        ]);

        $file = $request->file('file');

        $path = $file->store('resources', 'private');

        $resource = Resource::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,

            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),

            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Resource uploaded successfully.',
            'resource' => $resource,
        ], 201);
    }

    /**
     * Update resource information.
     */
    public function update(Request $request, Resource $resource)
    {
        $validated = $request->validate([
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],
        ]);

        $resource->update($validated);

        return response()->json([
            'message' => 'Resource updated successfully.',
            'resource' => $resource,
        ]);
    }

    /**
     * Replace the resource's file.
     */
    public function replaceFile(Request $request, Resource $resource)
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png',
            ],
        ]);

        $file = $request->file('file');

        // Delete old file
        if ($resource->file_path) {
            Storage::disk('private')->delete($resource->file_path);
        }

        // Store new file
        $path = $file->store('resources', 'private');

        $resource->update([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return response()->json([
            'message' => 'Resource file replaced successfully.',
            'resource' => $resource,
        ]);
    }

    /**
     * View/stream the file.
     */
   public function view(Resource $resource)
{
    $disk = Storage::disk('private');

    if (!$disk->exists($resource->file_path)) {
        return response()->json([
            'message' => 'File not found.',
        ], 404);
    }

    $filePath = $disk->path($resource->file_path);

    return response()->file($filePath, [
        'Content-Type' => $resource->file_type,
        'Content-Disposition' => 'inline; filename="' . $resource->file_name . '"',
    ]);
}

    /**
     * Download the file.
     */
    public function download(Resource $resource)
    {
        if (!Storage::disk('private')->exists($resource->file_path)) {
            return response()->json([
                'message' => 'File not found.',
            ], 404);
        }

        return Storage::disk('private')->download(
            $resource->file_path,
            $resource->file_name
        );
    }

    /**
     * Delete resource.
     */
    public function destroy(Resource $resource)
    {
        // Delete physical file
        if ($resource->file_path) {
            Storage::disk('private')->delete($resource->file_path);
        }

        // Delete database record
        $resource->delete();

        return response()->json([
            'message' => 'Resource deleted successfully.',
        ]);
    }
}
