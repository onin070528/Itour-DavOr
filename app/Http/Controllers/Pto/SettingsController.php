<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: PTO account settings page (profile, password, notification
 * preferences), backed by the shared UpdatesAccountSettings trait.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Http\Controllers\Concerns\UpdatesAccountSettings;
use App\Models\Category;
use App\Support\OperationLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SettingsController extends PtoController
{
    use UpdatesAccountSettings;

    /**
     * Settings: profile, account information, basic preferences, and the
     * Categories panel (QR switches).
     */
    public function index(Request $objRequest): View
    {
        return $this->renderPto($objRequest, 'pto.settings', 'settings', 'Settings', [
            'preferences' => $this->notificationPreferencesFor($objRequest->user()->usr_id),
            'categories' => Category::query()->orderBy('cat_sort_order')->get(),
        ]);
    }

    /**
     * Turning cat_is_qr_enabled off never touches existing QR codes or
     * arrival records — it only makes Listing::isQrEnabled() start
     * returning false for every listing in that category, which is read
     * everywhere arrivals get collected (directory, forms, dashboard
     * filters, report breakdowns, the public scan). Turning it back on
     * re-activates the exact same QR codes, since nothing was ever deleted.
     */
    public function toggleCategoryQr(Request $objRequest, Category $category): RedirectResponse
    {
        abort_unless($objRequest->user()->can('update', $category), 403);

        $arrBefore = $category->getOriginal();

        try {
            $category->update(['cat_is_qr_enabled' => ! $category->cat_is_qr_enabled]);
        } catch (\Throwable $objException) {
            Log::error('Failed to toggle the category QR switch.', ['exception' => $objException, 'cat_id' => $category->cat_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($objRequest->user(), 'category', $category->cat_id, null, null, OperationLogger::diff($arrBefore, $category));

        return back()->with('toast', $category->cat_is_qr_enabled
            ? "{$category->cat_name} can collect QR arrivals again."
            : "{$category->cat_name} no longer accepts QR scans. Existing QR codes and arrival records are untouched.");
    }

    protected function notificationPreferenceDefinitions(): array
    {
        return [
            ['key' => 'email_weekly_summary', 'label' => 'Email me a weekly tourism summary', 'default' => true],
            ['key' => 'email_negative_feedback', 'label' => 'Notify me when tourist feedback is flagged negative', 'default' => true],
            ['key' => 'email_new_establishment', 'label' => 'Notify me when a new establishment registers', 'default' => false],
        ];
    }
}
