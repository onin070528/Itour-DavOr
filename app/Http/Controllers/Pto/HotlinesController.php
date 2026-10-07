<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: PTO management of the province-wide hotline directory — add,
 * edit, deactivate (never hard delete), and reorder.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Models\Hotline;
use App\Support\OperationLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HotlinesController extends PtoController
{
    private const AGENCY_TYPES = ['Police', 'Fire', 'Hospital/Medical', 'Disaster Office', 'Coast Guard', 'Other'];

    public function index(Request $request): View
    {
        $hotlines = Hotline::query()->orderBy('hot_sort_order')->get();

        return $this->renderPto($request, 'pto.hotlines.index', 'hotlines', 'Hotlines', [
            'hotlines' => $hotlines,
            'agencyTypes' => self::AGENCY_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('create', Hotline::class), 403);

        $data = $this->validatedFields($request);

        try {
            $hotline = Hotline::query()->create([
                ...$data,
                'hot_sort_order' => ((int) Hotline::query()->max('hot_sort_order')) + 1,
                'hot_created_by' => $request->user()->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create hotline.', ['exception' => $e]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::created($request->user(), 'hotline', $hotline->hot_id, null, null, $data);

        return back()->with('toast', "{$hotline->hot_agency_name} was added.");
    }

    public function update(Request $request, Hotline $hotline): RedirectResponse
    {
        abort_unless($request->user()->can('update', $hotline), 403);

        $data = $this->validatedFields($request);
        $before = $hotline->getOriginal();

        try {
            $hotline->update([...$data, 'hot_updated_by' => $request->user()->id]);
        } catch (\Throwable $e) {
            Log::error('Failed to update hotline.', ['exception' => $e, 'hotline_id' => $hotline->hot_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($request->user(), 'hotline', $hotline->hot_id, null, null, OperationLogger::diff($before, $hotline));

        return back()->with('toast', 'Hotline saved.');
    }

    /**
     * Deactivate only — hotlines are never hard-deleted (A3-style audit
     * trail requirement: PTO actions always leave a record).
     */
    public function deactivate(Request $request, Hotline $hotline): RedirectResponse
    {
        abort_unless($request->user()->can('deactivate', $hotline), 403);

        $before = $hotline->getOriginal();

        $hotline->update(['hot_is_active' => ! $hotline->hot_is_active, 'hot_updated_by' => $request->user()->id]);

        OperationLogger::updated($request->user(), 'hotline', $hotline->hot_id, null, null, OperationLogger::diff($before, $hotline));

        return back()->with('toast', $hotline->hot_is_active ? "{$hotline->hot_agency_name} reactivated." : "{$hotline->hot_agency_name} deactivated.");
    }

    /**
     * Swaps this hotline's sort order with its immediate neighbor in the
     * requested direction — simple, no drag-and-drop JS required.
     */
    public function reorder(Request $request, Hotline $hotline): RedirectResponse
    {
        abort_unless($request->user()->can('reorder', $hotline), 403);

        $data = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $neighbor = Hotline::query()
            ->when($data['direction'] === 'up', fn ($q) => $q->where('hot_sort_order', '<', $hotline->hot_sort_order)->orderByDesc('hot_sort_order'))
            ->when($data['direction'] === 'down', fn ($q) => $q->where('hot_sort_order', '>', $hotline->hot_sort_order)->orderBy('hot_sort_order'))
            ->first();

        if ($neighbor) {
            [$hotlineOrder, $neighborOrder] = [$hotline->hot_sort_order, $neighbor->hot_sort_order];
            $hotline->update(['hot_sort_order' => $neighborOrder]);
            $neighbor->update(['hot_sort_order' => $hotlineOrder]);
        }

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFields(Request $request): array
    {
        $data = $request->validate([
            'hot_agency_name' => ['required', 'string', 'max:255'],
            'hot_agency_type' => ['required', 'string', Rule::in(self::AGENCY_TYPES)],
            'hot_contact_number' => ['required', 'string', 'max:255'],
            'hot_scope' => ['nullable', 'string', 'max:255'],
        ]);

        $data['hot_scope'] = $request->input('hot_scope') ?: 'Province-wide';
        $data['hot_is_24_7'] = $request->boolean('hot_is_24_7');

        return $data;
    }
}
