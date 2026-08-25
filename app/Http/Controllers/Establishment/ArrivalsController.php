<?php

namespace App\Http\Controllers\Establishment;

use App\Support\EstablishmentMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArrivalsController extends EstablishmentController
{
    /**
     * Record Arrival: the Enter → Review → Submit wizard used to log a guest.
     * Submission is a frontend-only mock (no backend persistence yet).
     */
    public function record(Request $request): View
    {
        return $this->renderEstablishment($request, 'establishment.arrivals.record', 'arrivals.record', 'Record Arrival');
    }

    /**
     * Arrival Records: guest arrivals previously recorded for this establishment.
     */
    public function index(Request $request): View
    {
        $name = $request->user()->organization_name;

        return $this->renderEstablishment($request, 'establishment.arrivals.index', 'arrivals.index', 'Arrival Records', [
            'arrivals' => EstablishmentMockData::arrivals($name),
        ]);
    }
}
