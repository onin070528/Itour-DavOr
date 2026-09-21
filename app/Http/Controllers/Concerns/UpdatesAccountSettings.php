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

use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

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
     * tab: key (stable, used as the DB row's `key` and the form field name),
     * label (exact on-screen text), default (its checked state when no row
     * has been saved yet). Implemented per controller/role.
     *
     * @return array<int, array{key: string, label: string, default: bool}>
     */
    abstract protected function notificationPreferenceDefinitions(): array;

    /**
     * @return array<int, array{key: string, label: string, checked: bool}>
     */
    protected function notificationPreferencesFor(int $userId): array
    {
        $saved = NotificationPreference::query()
            ->where('user_id', $userId)
            ->pluck('enabled', 'key');

        return collect($this->notificationPreferenceDefinitions())
            ->map(fn (array $pref) => [
                'key' => $pref['key'],
                'label' => $pref['label'],
                'checked' => $saved->has($pref['key']) ? (bool) $saved[$pref['key']] : $pref['default'],
            ])
            ->all();
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
        ]);

        try {
            $user->update($data);
        } catch (\Throwable $e) {
            Log::error('Failed to update account profile.', ['exception' => $e, 'user_id' => $user->id]);

            return back()->with('toast', 'Something went wrong while saving your profile. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Profile changes saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $request->user()->update(['password' => $data['password']]);
        } catch (\Throwable $e) {
            Log::error('Failed to update account password.', ['exception' => $e, 'user_id' => $request->user()->id]);

            return back()->with('toast', 'Something went wrong while updating your password. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Password updated.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $user = $request->user();
        $keys = collect($this->notificationPreferenceDefinitions())->pluck('key');

        try {
            foreach ($keys as $key) {
                NotificationPreference::query()->updateOrCreate(
                    ['user_id' => $user->id, 'key' => $key],
                    ['enabled' => $request->boolean("preferences.{$key}")],
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to update notification preferences.', ['exception' => $e, 'user_id' => $user->id]);

            return back()->with('toast', 'Something went wrong while saving your preferences. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Preferences saved.');
    }
}
