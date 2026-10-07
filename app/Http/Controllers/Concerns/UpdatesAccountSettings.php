<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Shared Account Profile, Change Password, and Notifications
 * settings-tab handling used by the Establishment, LGU, and PTO Settings
 * controllers.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Concerns;

use App\Events\UserPasswordChanged;
use App\Models\NotificationPreference;
use App\Support\SessionSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * "Account Profile" (name/email), "Change Password", and "Notifications"
 * tab handling shared by Establishment\SettingsController,
 * Lgu\SettingsController, and Pto\SettingsController — identical logic
 * across all three settings pages, differing only in each role's set of
 * notification preferences (see notificationPreferenceDefinitions()).
 */
trait UpdatesAccountSettings
{
    /**
     * The notification toggles shown on this role's Settings > Notifications
     * tab: key (stable, used as the DB row's `npf_key` and the form field name),
     * label (exact on-screen text), default (its checked state when no row
     * has been saved yet). Implemented per controller/role.
     *
     * @return array<int, array{key: string, label: string, default: bool}>
     */
    abstract protected function notificationPreferenceDefinitions(): array;

    /**
     * @return array<int, array{key: string, label: string, checked: bool}>
     */
    protected function notificationPreferencesFor(int $intUserId): array
    {
        $objSaved = NotificationPreference::query()
            ->where('usr_id', $intUserId)
            ->pluck('npf_enabled', 'npf_key');

        return collect($this->notificationPreferenceDefinitions())
            ->map(fn (array $arrPref) => [
                'key' => $arrPref['key'],
                'label' => $arrPref['label'],
                'checked' => $objSaved->has($arrPref['key']) ? (bool) $objSaved[$arrPref['key']] : $arrPref['default'],
            ])
            ->all();
    }

    public function updateProfile(Request $objRequest): RedirectResponse
    {
        $objUser = $objRequest->user();

        $arrData = $objRequest->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('tbl_users', 'usr_email')->ignore($objUser)],
        ]);

        try {
            $objUser->update(['usr_name' => $arrData['name'], 'usr_email' => $arrData['email']]);
        } catch (\Throwable $objException) {
            Log::error('Failed to update account profile.', ['exception' => $objException, 'usr_id' => $objUser->usr_id]);

            return back()->with('toast', 'Something went wrong while saving your profile. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Profile changes saved.');
    }

    public function updatePassword(Request $objRequest): RedirectResponse
    {
        $arrData = $objRequest->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::default()],
        ]);

        $objUser = $objRequest->user();

        try {
            $objUser->forceFill([
                'usr_password' => $arrData['password'],
                'usr_must_change_password' => false,
                'usr_password_changed_at' => now(),
            ])->save();
        } catch (\Throwable $objException) {
            Log::error('Failed to update account password.', ['exception' => $objException, 'usr_id' => $objUser->usr_id]);

            return back()->with('toast', 'Something went wrong while updating your password. Please try again.')->with('toast_tone', 'danger');
        }

        SessionSecurity::invalidateOtherSessionsFor($objUser, $objRequest->session()->getId());

        event(new UserPasswordChanged($objUser));

        return back()->with('toast', 'Password updated.');
    }

    public function updatePreferences(Request $objRequest): RedirectResponse
    {
        $objUser = $objRequest->user();
        $objKeys = collect($this->notificationPreferenceDefinitions())->pluck('key');

        try {
            foreach ($objKeys as $key) {
                NotificationPreference::query()->updateOrCreate(
                    ['usr_id' => $objUser->usr_id, 'npf_key' => $key],
                    ['npf_enabled' => $objRequest->boolean("preferences.{$key}")],
                );
            }
        } catch (\Throwable $objException) {
            Log::error('Failed to update notification preferences.', ['exception' => $objException, 'usr_id' => $objUser->usr_id]);

            return back()->with('toast', 'Something went wrong while saving your preferences. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Preferences saved.');
    }
}
