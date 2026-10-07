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

    public function index(Request $request): View
    {
        $announcements = Announcement::query()->orderByDesc('ann_start_date')->get();

        return $this->renderPto($request, 'pto.announcements.index', 'announcements', 'Announcements', [
            'announcements' => $announcements,
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('create', Announcement::class), 403);

        $data = $this->validatedFields($request);

        try {
            $announcement = Announcement::query()->create([
                ...$data,
                'ann_is_published' => false,
                'ann_created_by' => $request->user()->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create announcement.', ['exception' => $e]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::created($request->user(), 'announcement', $announcement->ann_id, null, null, $data);

        return back()->with('toast', "{$announcement->ann_title} was created as a draft.");
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($request->user()->can('update', $announcement), 403);

        $data = $this->validatedFields($request);
        $before = $announcement->getOriginal();

        try {
            $announcement->update([...$data, 'ann_updated_by' => $request->user()->id]);
        } catch (\Throwable $e) {
            Log::error('Failed to update announcement.', ['exception' => $e, 'announcement_id' => $announcement->ann_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($request->user(), 'announcement', $announcement->ann_id, null, null, OperationLogger::diff($before, $announcement));

        return back()->with('toast', 'Announcement saved.');
    }

    public function togglePublish(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($request->user()->can('togglePublish', $announcement), 403);

        $before = $announcement->getOriginal();

        $announcement->update(['ann_is_published' => ! $announcement->ann_is_published, 'ann_updated_by' => $request->user()->id]);

        OperationLogger::updated($request->user(), 'announcement', $announcement->ann_id, null, null, OperationLogger::diff($before, $announcement));

        return back()->with('toast', $announcement->ann_is_published ? "{$announcement->ann_title} published." : "{$announcement->ann_title} unpublished.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFields(Request $request): array
    {
        return $request->validate([
            'ann_title' => ['required', 'string', 'max:255'],
            'ann_body' => ['required', 'string'],
            'ann_type' => ['required', 'string', Rule::in(self::TYPES)],
            'ann_start_date' => ['nullable', 'date'],
            'ann_end_date' => ['nullable', 'date', 'after_or_equal:ann_start_date'],
        ]);
    }
}
