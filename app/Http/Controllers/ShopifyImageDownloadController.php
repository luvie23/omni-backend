<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use ZipArchive;

class ShopifyImageDownloadController extends Controller
{
    public function download(Request $request)
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $csvPath = $request->file('csv')->getRealPath();

        $zipFileName = 'shopify-images-' . time() . '.zip';
        $zipPath = storage_path('app/' . $zipFileName);

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'Could not create ZIP file.'], 500);
        }

        $handle = fopen($csvPath, 'r');

        while (($row = fgetcsv($handle)) !== false) {
            $url = trim($row[0] ?? '');

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $response = Http::get($url);

            if (!$response->successful()) {
                continue;
            }

            $path = parse_url($url, PHP_URL_PATH);
            $filename = basename($path);

            if (!$filename) {
                $filename = uniqid('image_') . '.jpg';
            }

            $zip->addFromString($filename, $response->body());
        }

        fclose($handle);
        $zip->close();

        return response()
            ->download($zipPath, 'shopify-images.zip')
            ->deleteFileAfterSend(true);
    }
}
