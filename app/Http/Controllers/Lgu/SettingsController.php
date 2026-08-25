<?php

namespace App\Http\Controllers\Lgu;

use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends LguController
{
    /**
     * Settings: profile, account information, assigned municipality, and preferences.
     */
    public function index(Request $request): View
    {
        return $this->renderLgu($request, 'lgu.settings', 'settings', 'Settings');
    }
}
