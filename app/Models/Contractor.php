<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contractor extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'company_website_url',
        'mailing_address',
        'city',
        'state',
        'zip',
        'service_area',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function certifiedPeople()
    {
        return $this->hasMany(\App\Models\CertifiedPerson::class);
    }
}
