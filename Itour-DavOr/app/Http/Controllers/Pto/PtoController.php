<?php

namespace App\Http\Controllers\Pto;

use App\Http\Controllers\Controller;
use App\Support\PtoNavigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

abstract class PtoController extends Controller
{
    /**
     * Render a PTO page with the sidebar nav, active state, and shared
     * sidebar chrome (System heading, Settings link) already wired up.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderPto(Request $request, string $view, string $activeKey, string $pageTitle, array $data = []): View
    {
        return view($view, array_merge([
            'user' => $request->user(),
            'navSections' => PtoNavigation::sections($activeKey),
            'pageTitle' => $pageTitle,
            'accountHeading' => 'System',
            'settingsHref' => route('pto.settings'),
        ], $data));
    }
}
