<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ShopifyImageDownloadController extends Controller
{
   public function download(Request $request)
{
    $request->validate([
        'csv' => ['required', 'file', 'mimes:csv,txt'],
    ]);

    $csvPath = $request->file('csv')->getRealPath();

    $batchId = uniqid('shopify_images_', true);

    $tempDir = storage_path("app/temp/{$batchId}");
    File::ensureDirectoryExists($tempDir);

    $zipFileName = "shopify-images-{$batchId}.zip";
    $zipPath = storage_path("app/{$zipFileName}");

    $zip = new ZipArchive;

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        File::deleteDirectory($tempDir);

        return response()->json([
            'message' => 'Could not create ZIP file.',
        ], 500);
    }

    $handle = fopen($csvPath, 'r');

    if (!$handle) {
        $zip->close();
        File::delete($zipPath);
        File::deleteDirectory($tempDir);

        return response()->json([
            'message' => 'Could not read CSV file.',
        ], 500);
    }

    $usedFilenames = [];
    $downloadedFiles = [];

    while (($row = fgetcsv($handle)) !== false) {
        $url = trim($row[0] ?? '');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            continue;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $originalFilename = basename($path);

        if (!$originalFilename || !str_contains($originalFilename, '.')) {
            $originalFilename = uniqid('image_', true) . '.jpg';
        }

        $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalFilename);

        if (isset($usedFilenames[$safeFilename])) {
            $info = pathinfo($safeFilename);

            $name = $info['filename'] ?? 'image';
            $extension = isset($info['extension']) ? '.' . $info['extension'] : '';

            $safeFilename = $name . '_' . uniqid() . $extension;
        }

        $usedFilenames[$safeFilename] = true;

        $tempFilePath = $tempDir . DIRECTORY_SEPARATOR . $safeFilename;

        try {
            $response = Http::
                get($url);

            if (!$response->successful()) {
                File::delete($tempFilePath);
                continue;
            }

            if (!File::exists($tempFilePath) || File::size($tempFilePath) === 0) {
                File::delete($tempFilePath);
                continue;
            }

            $zip->addFile($tempFilePath, $safeFilename);
            $downloadedFiles[] = $tempFilePath;
        } catch (\Throwable $e) {
            File::delete($tempFilePath);
            continue;
        }
    }

    fclose($handle);
    $zip->close();

    File::delete($downloadedFiles);
    File::deleteDirectory($tempDir);

    if (!File::exists($zipPath) || File::size($zipPath) === 0) {
        File::delete($zipPath);

        return response()->json([
            'message' => 'No valid images were downloaded.',
        ], 422);
    }

    return response()
        ->download($zipPath, 'shopify-images.zip')
        ->deleteFileAfterSend(true);
}
}
