<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeBaseArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class KnowledgeBaseAdminController extends Controller
{
    public function index(Request $request)
    {
        $articles = KnowledgeBaseArticle::query()
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(
            $articles->through(fn ($article) => $this->payload($article))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm|max:4194304',
            'is_published' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $video = $data['video'];
        $storedPath = $video->store('knowledge-base-videos', 'private');

        $article = KnowledgeBaseArticle::create([
            'title' => $data['title'],
            'slug' => KnowledgeBaseArticle::generateUniqueSlug($data['title']),
            'description' => $data['description'] ?? null,
            'video_path' => $storedPath,
            'video_original_name' => $video->getClientOriginalName(),
            'video_mime_type' => $video->getMimeType() ?: 'application/octet-stream',
            'video_size' => $video->getSize(),
            'is_published' => (bool) ($data['is_published'] ?? false),
            'sort_order' => $data['sort_order'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Knowledge base article created successfully.',
            'data' => $this->payload($article),
        ], 201);
    }

    public function show(Request $request)
    {
        $articleId = (int) $request->route('article');

        $article = KnowledgeBaseArticle::findOrFail($articleId);

        return response()->json([
            'data' => $this->payload($article),
        ]);
    }

    public function update(Request $request)
    {
        $articleId = (int) $request->route('article');

        $article = KnowledgeBaseArticle::findOrFail($articleId);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'video' => 'sometimes|required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm|max:4194304',
            'is_published' => 'sometimes|boolean',
            'sort_order' => 'sometimes|nullable|integer|min:0',
        ]);

        if (array_key_exists('title', $data) && $data['title'] !== $article->title) {
            $article->title = $data['title'];
            $article->slug = KnowledgeBaseArticle::generateUniqueSlug($data['title']);
        }

        if (array_key_exists('description', $data)) {
            $article->description = $data['description'];
        }

        if (array_key_exists('is_published', $data)) {
            $article->is_published = (bool) $data['is_published'];
        }

        if (array_key_exists('sort_order', $data)) {
            $article->sort_order = $data['sort_order'];
        }

        if (array_key_exists('video', $data)) {
            if (Storage::disk('private')->exists($article->video_path)) {
                Storage::disk('private')->delete($article->video_path);
            }

            $video = $data['video'];

            $article->video_path = $video->store('knowledge-base-videos', 'private');
            $article->video_original_name = $video->getClientOriginalName();
            $article->video_mime_type = $video->getMimeType() ?: 'application/octet-stream';
            $article->video_size = $video->getSize();
        }

        $article->save();

        return response()->json([
            'message' => 'Knowledge base article updated successfully.',
            'data' => $this->payload($article),
        ]);
    }

    public function destroy(Request $request)
    {
        $articleId = (int) $request->route('article');

        $article = KnowledgeBaseArticle::findOrFail($articleId);

        if (Storage::disk('private')->exists($article->video_path)) {
            Storage::disk('private')->delete($article->video_path);
        }

        $article->delete();

        return response()->json([
            'message' => 'Knowledge base article deleted successfully.',
        ]);
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
            'is_published' => $article->is_published,
            'sort_order' => $article->sort_order,
            'created_by' => $article->created_by,
            'created_at' => $article->created_at,
            'updated_at' => $article->updated_at,
            'video_stream_url' => URL::temporarySignedRoute(
                'admin.knowledge-base.video',
                now()->addHours(2),
                ['article' => $article->id]
            ),
        ];
    }

    public function stream(Request $request)
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired video link.');
        $articleId = (int) $request->route('article');

        $article = KnowledgeBaseArticle::findOrFail($articleId);

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
}
