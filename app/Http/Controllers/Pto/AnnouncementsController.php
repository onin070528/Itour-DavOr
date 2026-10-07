<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: PTO management of announcements, promotions, and advisories —
 * create, edit, schedule, and publish/unpublish. Published items appear on
 * the public landing page (see LandingController).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Models\Announcement;
use App\Support\OperationLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementsController extends PtoController
{
    private const TYPES = ['Promotion', 'Advisory', 'Event', 'Announcement'];

    public function index(Request $objRequest): View
    {
        $objAnnouncements = Announcement::query()->orderByDesc('ann_start_date')->get();

        return $this->renderPto($objRequest, 'pto.announcements.index', 'announcements', 'Announcements', [
            'announcements' => $objAnnouncements,
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $objRequest): RedirectResponse
    {
        abort_unless($objRequest->user()->can('create', Announcement::class), 403);

        $arrData = $this->validatedFields($objRequest);

        try {
            $objAnnouncement = Announcement::query()->create([
                ...$arrData,
                'ann_is_published' => false,
                'ann_created_by' => $objRequest->user()->usr_id,
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to create announcement.', ['exception' => $objException]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::created($objRequest->user(), 'announcement', $objAnnouncement->ann_id, null, null, $arrData);

        return back()->with('toast', "{$objAnnouncement->ann_title} was created as a draft.");
    }

    public function update(Request $objRequest, Announcement $announcement): RedirectResponse
    {
        abort_unless($objRequest->user()->can('update', $announcement), 403);

        $arrData = $this->validatedFields($objRequest);
        $arrBefore = $announcement->getOriginal();

        try {
            $announcement->update([...$arrData, 'ann_updated_by' => $objRequest->user()->usr_id]);
        } catch (\Throwable $objException) {
            Log::error('Failed to update announcement.', ['exception' => $objException, 'announcement_id' => $announcement->ann_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($objRequest->user(), 'announcement', $announcement->ann_id, null, null, OperationLogger::diff($arrBefore, $announcement));

        return back()->with('toast', 'Announcement saved.');
    }

    public function togglePublish(Request $objRequest, Announcement $announcement): RedirectResponse
    {
        abort_unless($objRequest->user()->can('togglePublish', $announcement), 403);

        $arrBefore = $announcement->getOriginal();

        try {
            $announcement->update(['ann_is_published' => ! $announcement->ann_is_published, 'ann_updated_by' => $objRequest->user()->usr_id]);
        } catch (\Throwable $objException) {
            Log::error('Failed to toggle announcement publication.', ['exception' => $objException, 'ann_id' => $announcement->ann_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($objRequest->user(), 'announcement', $announcement->ann_id, null, null, OperationLogger::diff($arrBefore, $announcement));

        return back()->with('toast', $announcement->ann_is_published ? "{$announcement->ann_title} published." : "{$announcement->ann_title} unpublished.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFields(Request $objRequest): array
    {
        return $objRequest->validate([
            'ann_title' => ['required', 'string', 'max:255'],
            'ann_body' => ['required', 'string'],
            'ann_type' => ['required', 'string', Rule::in(self::TYPES)],
            'ann_start_date' => ['nullable', 'date'],
            'ann_end_date' => ['nullable', 'date', 'after_or_equal:ann_start_date'],
        ]);
    }
}
