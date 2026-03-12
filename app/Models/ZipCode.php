<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZipCode extends Model
{
    protected $table = 'zip_codes';

    protected $primaryKey = 'zip';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'zip',
        'city',
        'state',
        'latitude',
        'longitude'
    ];
}
