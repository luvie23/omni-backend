<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationRequest extends Model
{
    protected $fillable = [
        'name',
        'company_name',
        'address',
        'city',
        'state',
        'zip',
        'phone_number',
        'email',
        'details',
        'status'
    ];
}
