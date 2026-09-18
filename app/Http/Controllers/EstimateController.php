<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstimateController extends Controller
{
    /**
     * List the authenticated user's estimates.
     */
    public function index(Request $request): JsonResponse
    {
        $estimates = $request->user()
            ->estimates()
            ->latest()
            ->get([
                'id',
                'name',
                'customer_name',
                'project_address',
                'note',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'estimates' => $estimates,
        ]);
    }

    /**
     * Save a new estimate.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'customer_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'project_address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'note' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'calculator_data' => [
                'required',
                'array',
            ],

            'calculator_data.estimator' => [
                'required',
                'array',
            ],

            'calculator_data.labor' => [
                'required',
                'array',
            ],
        ]);

        $estimate = $request->user()
            ->estimates()
            ->create($data);

        return response()->json([
            'message' => 'Estimate saved successfully.',
            'estimate' => $estimate,
        ], 201);
    }

    /**
     * Open a saved estimate.
     */
    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        $estimate = $this->getUserEstimate(
            $request,
            $id
        );

        return response()->json([
            'estimate' => $estimate,
        ]);
    }

    /**
     * Update an existing estimate.
     */
    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $estimate = $this->getUserEstimate(
            $request,
            $id
        );

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'customer_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'project_address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'note' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'calculator_data' => [
                'required',
                'array',
            ],

            'calculator_data.estimator' => [
                'required',
                'array',
            ],

            'calculator_data.labor' => [
                'required',
                'array',
            ],
        ]);

        $estimate->update($data);

        return response()->json([
            'message' => 'Estimate updated successfully.',
            'estimate' => $estimate->fresh(),
        ]);
    }

    /**
     * Delete a saved estimate.
     */
    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $estimate = $this->getUserEstimate(
            $request,
            $id
        );

        $estimate->delete();

        return response()->json([
            'message' => 'Estimate deleted successfully.',
        ]);
    }

    /**
     * Get an estimate that belongs to the authenticated user.
     *
     * Returns 404 if the estimate does not exist or belongs
     * to another user.
     */
    private function getUserEstimate(
        Request $request,
        int $id
    ): Estimate {
        return Estimate::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
