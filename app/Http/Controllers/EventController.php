<?php
namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::orderBy('starts_at')->get();

        return response()->json($events);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'date_type' => ['required', Rule::in(['single', 'range'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if ($validated['date_type'] === 'single') {
            $validated['ends_at'] = null;
        }

        unset($validated['date_type']);

        $event = Event::create($validated);

        return response()->json($event, 201);
    }

    public function show($id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'message' => 'Event not found'
            ], 404);
        }

        return response()->json($event);
    }

    public function update(Request $request, $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'message' => 'Event not found'
            ], 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'date_type' => ['sometimes', Rule::in(['single', 'range'])],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $startsAt = $validated['starts_at'] ?? $event->starts_at?->format('Y-m-d');
        $endsAt = array_key_exists('ends_at', $validated)
            ? $validated['ends_at']
            : $event->ends_at?->format('Y-m-d');

        if (!is_null($endsAt) && $endsAt < $startsAt) {
            return response()->json([
                'message' => 'The ends_at field must be after or equal to starts_at.'
            ], 422);
        }

        if (($validated['date_type'] ?? null) === 'single') {
            $validated['ends_at'] = null;
        }

        unset($validated['date_type']);

        $event->update($validated);

        return response()->json($event);
    }

    public function destroy($id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'message' => 'Event not found'
            ], 404);
        }

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully'
        ]);
    }

    public function upcoming()
    {
        $events = Event::where(function ($query) {
            $query->whereNotNull('ends_at')
                ->where('ends_at', '>=', now()->subDay());
        })
        ->orWhere(function ($query) {
            $query->whereNull('ends_at')
                ->where('starts_at', '>=', now()->subDay());
        })
        ->orderBy('starts_at')
        ->get();

        return response()->json($events);
    }
}
