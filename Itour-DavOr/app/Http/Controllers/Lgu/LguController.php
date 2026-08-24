<?php

namespace App\Http\Controllers\Lgu;

use App\Http\Controllers\Controller;
use App\Support\LguNavigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

abstract class LguController extends Controller
{
    /**
     * Render an LGU page with the sidebar nav, active state, and shared
     * sidebar chrome already wired up, plus the current user's assigned
     * municipality — every LGU page is scoped to it.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderLgu(Request $request, string $view, string $activeKey, string $pageTitle, array $data = []): View
    {
        $user = $request->user();

        return view($view, array_merge([
            'user' => $user,
            'municipality' => $user->organization_subtitle,
            'navSections' => LguNavigation::sections($activeKey),
            'pageTitle' => $pageTitle,
            'accountHeading' => 'System',
            'settingsHref' => route('lgu.settings'),
        ], $data));
    }
}
