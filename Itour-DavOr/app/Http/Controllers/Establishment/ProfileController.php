<?php

namespace App\Http\Controllers\Establishment;

use App\Support\EstablishmentMockData;
use App\Support\TourismCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends EstablishmentController
{
    /**
     * Establishment Profile: view/edit the account's own public tourism
     * profile. The municipality is fixed to the account's assignment.
     */
    public function edit(Request $request): View
    {
        $name = $request->user()->organization_name;

        return $this->renderEstablishment($request, 'establishment.profile', 'establishment.profile', 'Establishment Profile', [
            'profile' => EstablishmentMockData::profile($name),
            'gallery' => EstablishmentMockData::galleryImages($name),
            'categories' => TourismCatalog::categories(),
        ]);
    }

    /**
     * QR Code: the establishment-specific QR tourists scan to reach the
     * arrival self-registration form.
     */
    public function qr(Request $request): View
    {
        $name = $request->user()->organization_name;

        return $this->renderEstablishment($request, 'establishment.qr', 'establishment.qr', 'QR Code', [
            'profile' => EstablishmentMockData::profile($name),
        ]);
    }
}
