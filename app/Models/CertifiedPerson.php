<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertifiedPerson extends Model
{
    protected $fillable = [
        'contractor_id',
        'name',
        'certification_number',
    ];

    public function contractor()
    {
        return $this->belongsTo(\App\Models\Contractor::class);
    }
}
