<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KnowledgeBaseArticle extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'video_path',
        'video_original_name',
        'video_mime_type',
        'video_size',
        'is_published',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $article) {
            if (blank($article->slug)) {
                $article->slug = static::generateUniqueSlug($article->title);
            }
        });
    }

    public static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
