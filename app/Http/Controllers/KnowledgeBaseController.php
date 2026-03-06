<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeBaseArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $articles = KnowledgeBaseArticle::query()
            ->where('is_published', true)
            ->orderByRaw('sort_order IS NULL, sort_order ASC')
            ->latest()
            ->get();

        return response()->json([
            'data' => $articles->map(fn ($article) => $this->payload($article)),
        ]);
    }

    public function show(Request $request)
    {
        $articleId = (int) $request->route('article');

        $article = KnowledgeBaseArticle::findOrFail($articleId);

        abort_unless($article->is_published, 404);

        return response()->json([
            'data' => $this->payload($article),
        ]);
    }

    public function stream(Request $request)
    {


        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired video link.');

        $articleId = (int) $request->route('article');

        $article = KnowledgeBaseArticle::findOrFail($articleId);

        abort_unless($article->is_published, 404);
        abort_unless(Storage::disk('private')->exists($article->video_path), 404);

        $fullPath = Storage::disk('private')->path($article->video_path);
        $size = filesize($fullPath);
        $mime = $article->video_mime_type ?: 'video/mp4';

        $start = 0;
        $end = $size - 1;

        $headers = [
            'Content-Type' => $mime,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ];

        if ($range = $request->header('Range')) {
            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = (int) $matches[1];
                if ($matches[2] !== '') {
                    $end = (int) $matches[2];
                }

                $start = max(0, $start);
                $end = min($end, $size - 1);

                if ($start > $end) {
                    return response('', 416, [
                        'Content-Range' => "bytes */{$size}",
                    ]);
                }

                $length = $end - $start + 1;

                $headers['Content-Length'] = (string) $length;
                $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";

                return response()->stream(function () use ($fullPath, $start, $length) {
                    $handle = fopen($fullPath, 'rb');
                    fseek($handle, $start);

                    $remaining = $length;
                    while ($remaining > 0 && !feof($handle)) {
                        $chunkSize = min(8192, $remaining);
                        echo fread($handle, $chunkSize);
                        flush();
                        $remaining -= $chunkSize;
                    }

                    fclose($handle);
                }, 206, $headers);
            }
        }

        $headers['Content-Length'] = (string) $size;

        return response()->stream(function () use ($fullPath) {
            $handle = fopen($fullPath, 'rb');

            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function payload(KnowledgeBaseArticle $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'description' => $article->description,
            'video_original_name' => $article->video_original_name,
            'video_mime_type' => $article->video_mime_type,
            'video_size' => $article->video_size,
            'created_at' => $article->created_at,
            'updated_at' => $article->updated_at,
            'video_stream_url' => URL::temporarySignedRoute(
                'knowledge-base.video',
                now()->addHours(2),
                ['article' => $article->id]
            ),
        ];
    }
}
