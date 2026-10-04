<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Establishment photo upload — for the account's own establishment only. The
 *              Photos page itself is merged into the Establishment Profile page (see
 *              routes/web.php's redirect and Establishment\ProfileController); this
 *              controller now only handles the upload action.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Http\Controllers\Establishment;

use App\Http\Controllers\Concerns\ManagesEstablishmentImages;
use App\Http\Requests\UploadEstablishmentImageRequest;
use App\Models\Listing;
use App\Services\EstablishmentImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImagesController extends EstablishmentController
{
    use ManagesEstablishmentImages;

    public function store(UploadEstablishmentImageRequest $objRequest, EstablishmentImageUploader $objUploader): RedirectResponse
    {
        $objListing = $this->_ownListing($objRequest);
        $arrData = $objRequest->validated();

        $arrUploadedImages = $objUploader->upload($objRequest->user(), $objListing, $arrData['photos'], $arrData['credit'] ?? null);

        return back()->with('toast', count($arrUploadedImages) === 1
            ? 'Your photo was submitted for approval.'
            : count($arrUploadedImages).' photos were submitted for approval.');
    }

    private function _ownListing(Request $objRequest): Listing
    {
        abort_if($objRequest->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');

        return Listing::query()->findOrFail($objRequest->user()->establishment_id);
    }
}
