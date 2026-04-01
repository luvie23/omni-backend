<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title',
        'starts_at',
        'ends_at',
        'location',
        'description',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    protected $appends = [
        'is_range',
    ];

    public function getIsRangeAttribute(): bool
    {
        return !is_null($this->ends_at) && !$this->starts_at->equalTo($this->ends_at);
    }
}
