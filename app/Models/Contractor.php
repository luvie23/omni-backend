<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contractor extends Model
{
    protected $fillable = [
        'company_name',
        'email',
        'contact_number',
        'company_website_url',
        'logo_path',
        'mailing_address',
        'city',
        'state',
        'zip',
        'service_area',
    ];

    public function users()
    {
       return $this->hasMany(\App\Models\User::class);
    }

    public function certifiedPeople()
    {
        return $this->hasMany(\App\Models\CertifiedPerson::class);
    }

    public function quotationRequests()
    {
        return $this->belongsToMany(
            QuotationRequest::class,
            'contractor_quotation_request'
        )->withPivot('sent_at')->withTimestamps();
    }

    public function zipCode()
    {
        return $this->belongsTo(\App\Models\ZipCode::class, 'zip', 'zip');
    }
}
