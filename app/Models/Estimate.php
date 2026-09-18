<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Estimate extends Model
{
    protected $fillable = [
        'name',
        'customer_name',
        'project_address',
        'note',
        'calculator_data',
    ];

    protected $casts = [
        'calculator_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
