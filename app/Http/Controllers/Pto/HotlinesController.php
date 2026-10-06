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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HotlinesController extends PtoController
{
    private const AGENCY_TYPES = ['Police', 'Fire', 'Hospital/Medical', 'Disaster Office', 'Coast Guard', 'Other'];

    public function index(Request $objRequest): View
    {
        $objHotlines = Hotline::query()->orderBy('hot_sort_order')->get();

        return $this->renderPto($objRequest, 'pto.hotlines.index', 'hotlines', 'Hotlines', [
            'hotlines' => $objHotlines,
            'agencyTypes' => self::AGENCY_TYPES,
        ]);
    }

    public function store(Request $objRequest): RedirectResponse
    {
        $arrData = $this->validatedFields($objRequest);

        try {
            $objHotline = Hotline::query()->create([
                ...$arrData,
                'hot_sort_order' => ((int) Hotline::query()->max('hot_sort_order')) + 1,
                'hot_created_by' => $objRequest->user()->usr_id,
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to create hotline.', ['exception' => $objException]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::created($objRequest->user(), 'hotline', $objHotline->hot_id, null, null, $arrData);

        return back()->with('toast', "{$objHotline->hot_agency_name} was added.");
    }

    public function update(Request $objRequest, Hotline $hotline): RedirectResponse
    {
        $arrData = $this->validatedFields($objRequest);
        $arrBefore = $hotline->getOriginal();

        try {
            $hotline->update([...$arrData, 'hot_updated_by' => $objRequest->user()->usr_id]);
        } catch (\Throwable $objException) {
            Log::error('Failed to update hotline.', ['exception' => $objException, 'hotline_id' => $hotline->hot_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($objRequest->user(), 'hotline', $hotline->hot_id, null, null, OperationLogger::diff($arrBefore, $hotline));

        return back()->with('toast', 'Hotline saved.');
    }

    /**
     * Deactivate only — hotlines are never hard-deleted (A3-style audit
     * trail requirement: PTO actions always leave a record).
     */
    public function deactivate(Request $objRequest, Hotline $hotline): RedirectResponse
    {
        $arrBefore = $hotline->getOriginal();

        try {
            $hotline->update(['hot_is_active' => ! $hotline->hot_is_active, 'hot_updated_by' => $objRequest->user()->usr_id]);
        } catch (\Throwable $objException) {
            Log::error('Failed to toggle hotline activation.', ['exception' => $objException, 'hot_id' => $hotline->hot_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($objRequest->user(), 'hotline', $hotline->hot_id, null, null, OperationLogger::diff($arrBefore, $hotline));

        return back()->with('toast', $hotline->hot_is_active ? "{$hotline->hot_agency_name} reactivated." : "{$hotline->hot_agency_name} deactivated.");
    }

    /**
     * Swaps this hotline's sort order with its immediate neighbor in the
     * requested direction — simple, no drag-and-drop JS required.
     */
    public function reorder(Request $objRequest, Hotline $hotline): RedirectResponse
    {
        $arrData = $objRequest->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $objNeighbor = Hotline::query()
            ->when($arrData['direction'] === 'up', fn ($objQuery) => $objQuery->where('hot_sort_order', '<', $hotline->hot_sort_order)->orderByDesc('hot_sort_order'))
            ->when($arrData['direction'] === 'down', fn ($objQuery) => $objQuery->where('hot_sort_order', '>', $hotline->hot_sort_order)->orderBy('hot_sort_order'))
            ->first();

        if ($objNeighbor) {
            [$hotlineOrder, $neighborOrder] = [$hotline->hot_sort_order, $objNeighbor->hot_sort_order];

            try {
                DB::transaction(function () use ($hotline, $objNeighbor, $hotlineOrder, $neighborOrder) {
                    $hotline->update(['hot_sort_order' => $neighborOrder]);
                    $objNeighbor->update(['hot_sort_order' => $hotlineOrder]);
                });
            } catch (\Throwable $objException) {
                Log::error('Failed to reorder hotlines.', ['exception' => $objException, 'hot_id' => $hotline->hot_id]);

                return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
            }
        }

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFields(Request $objRequest): array
    {
        $arrData = $objRequest->validate([
            'hot_agency_name' => ['required', 'string', 'max:255'],
            'hot_agency_type' => ['required', 'string', Rule::in(self::AGENCY_TYPES)],
            'hot_contact_number' => ['required', 'string', 'max:255'],
            'hot_scope' => ['nullable', 'string', 'max:255'],
        ]);

        $arrData['hot_scope'] = $objRequest->input('hot_scope') ?: 'Province-wide';
        $arrData['hot_is_24_7'] = $objRequest->boolean('hot_is_24_7');

        return $arrData;
    }
}
