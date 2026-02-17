<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
{
    public function index()
    {
        // Log::debug('An informational message.');
        return ['message' => 'TEST'];
    }
}
