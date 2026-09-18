<?php

namespace App\Http\Controllers;

use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;

class GoogleDriveController extends Controller
{
    public function __construct(
        protected GoogleDriveService $driveService
    ) {
    }

    public function folder(?string $folderId = null): JsonResponse
    {
        $folderId ??= config('services.google_drive.folder_id');

        $response = $this->driveService
         ->getDrive()
         ->files
         ->listFiles([
             'q' => "'{$folderId}' in parents and trashed = false",

             'fields' => 'files(id,name,mimeType,webViewLink,thumbnailLink,modifiedTime,size)',

             'orderBy' => 'folder,name',

             'includeItemsFromAllDrives' => true,
             'supportsAllDrives' => true,
         ]);

        $items = collect($response->getFiles())->map(function ($file) {

            $isFolder =
                $file->getMimeType() ===
                'application/vnd.google-apps.folder';

            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'type' => $isFolder ? 'folder' : 'file',
                'mime_type' => $file->getMimeType(),
                'url' => $file->getWebViewLink(),
                'thumbnail' => $file->getThumbnailLink(),
                'modified_at' => $file->getModifiedTime(),
                'size' => $file->getSize(),
            ];
        });

        return response()->json([
            'folder_id' => $folderId,

            'folders' => $items
                ->where('type', 'folder')
                ->values(),

            'files' => $items
                ->where('type', 'file')
                ->values(),
        ]);
    }

    public function folderInfo(string $folderId): JsonResponse
    {
        try {
            $folder = $this->driveService
                ->getDrive()
                ->files
                ->get(
                    $folderId,
                    [
                        'fields' => 'id,name,mimeType,webViewLink,parents',
                        'supportsAllDrives' => true,
                    ]
                );

            return response()->json([
                'success' => true,

                'folder' => [
                    'id' => $folder->getId(),
                    'name' => $folder->getName(),
                    'mime_type' => $folder->getMimeType(),
                    'url' => $folder->getWebViewLink(),
                    'parents' => $folder->getParents(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
