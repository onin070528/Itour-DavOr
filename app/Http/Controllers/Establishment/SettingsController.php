<?php

namespace App\Http\Controllers\Establishment;

use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends EstablishmentController
{
    /**
     * Settings: account profile, contact information, and preferences.
     */
    public function index(Request $request): View
    {
        return $this->renderEstablishment($request, 'establishment.settings', 'settings', 'Settings');
    }
}
