<?php

namespace App\Http\Controllers\Pto;

use App\Support\PtoMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsersController extends PtoController
{
    /**
     * User Management: PTO, LGU, and Establishment accounts, province-wide.
     */
    public function index(Request $request): View
    {
        return $this->renderPto($request, 'pto.users', 'users', 'User Management', [
            'users' => PtoMockData::users(),
        ]);
    }
}
