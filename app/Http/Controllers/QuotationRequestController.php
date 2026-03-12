<?php

namespace App\Http\Controllers;

use App\Models\QuotationRequest;
use Illuminate\Http\Request;

class QuotationRequestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|size:2',
            'zip' => 'required|string|max:10',
            'phone_number' => 'required|string|max:25',
            'email' => 'required|email|max:255',
            'details' => 'nullable|string',
        ]);

        $quote = QuotationRequest::create($data);

        return response()->json([
            'message' => 'Quotation request submitted successfully.',
            'data' => $quote,
        ], 201);
    }
}
