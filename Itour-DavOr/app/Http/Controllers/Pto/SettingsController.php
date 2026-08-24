<?php

namespace App\Http\Controllers\Pto;

use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends PtoController
{
    /**
     * Settings: profile, account information, and basic preferences.
     */
    public function index(Request $request): View
    {
        return $this->renderPto($request, 'pto.settings', 'settings', 'Settings');
    }
}
