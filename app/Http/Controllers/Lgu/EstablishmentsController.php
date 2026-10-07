<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : LGU establishment management — list, search/filter, add (with photos), view, and edit, scoped to the LGU's own municipality.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Enums\ImageStatus;
use App\Enums\ReportingMethod;
use App\Http\Controllers\Concerns\AuthorizesOwnMunicipality;
use App\Http\Controllers\Concerns\ManagesDestinationListings;
use App\Http\Requests\SaveEstablishmentRequest;
use App\Models\Category;
use App\Models\Listing;
use App\Policies\ImagePolicy;
use App\Services\EstablishmentImageUploader;
use App\Services\ListingPublishWorkflow;
use App\Support\OperationLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The establishment record itself (R1, R12, R14). Creating an
 * establishment never creates an account and never publishes anything:
 * it starts as Manual/Paper (ReportingMethod::default()) and DRAFT (not
 * requested as a destination listing). The municipality always comes from
 * the signed-in LGU — the form has no municipality field and a submitted
 * one is never read. The destination listing actions (submit to PTO /
 * return to the establishment) stay in Lgu\DirectoryController.
 */
class EstablishmentsController extends LguController
{
    use AuthorizesOwnMunicipality, ManagesDestinationListings;

    /**
     * One page, two views (D1). Establishments: the municipality's
     * establishments with search, category/type filters, and the reporting
     * method, account, QR, and destination listing state of every row.
     * Attractions: its destination-only records (no account, QR, or
     * reporting) with their destination listing state.
     */
    public function index(Request $request): View
    {
        $objUser = $request->user();
        $blnIsAttractionsView = $request->query('view') === 'attractions';

        $colEstablishments = $this->_ownEstablishmentsQuery($request)
            ->with(['categoryRecord', 'establishmentUser'])
            ->orderBy('name')
            ->get();

        $colAttractions = Listing::query()
            ->where('municipality_id', $objUser->municipality_id)
            ->where('category', 'destinations')
            ->withCount(['establishmentImages as intPhotoCount' => fn ($objQuery) => $objQuery->where('img_status', ImageStatus::Published)])
            ->orderBy('name')
            ->get();

        return $this->renderLgu($request, 'lgu.directory.establishments.index', $blnIsAttractionsView ? 'directory.attractions' : 'directory.establishments', $blnIsAttractionsView ? 'Tourist Attractions' : 'Establishments', [
            'establishments' => $colEstablishments,
            'attractions' => $colAttractions,
            'blnIsAttractionsView' => $blnIsAttractionsView,
            'categories' => $this->_establishmentCategories(),
            'intPhotoReviewCount' => $this->imageApprovalCount($objUser),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->renderLgu($request, 'lgu.directory.establishments.create', 'directory.establishments', 'Add Establishment', [
            'categories' => $this->_establishmentCategories(),
        ]);
    }

    /**
     * Saves the establishment, then (optionally) its photos through the
     * existing photo workflow (EstablishmentImageUploader — LGU uploads go
     * to the PTO for approval, unchanged). A photo problem never loses the
     * establishment: it is saved first and the LGU lands on its edit page
     * to retry the photos.
     */
    public function store(SaveEstablishmentRequest $request, EstablishmentImageUploader $objUploader): RedirectResponse
    {
        $objLgu = $request->user();
        $arrFields = $this->_normalizedFields($request->establishmentFields());

        try {
            $objListing = DB::transaction(function () use ($arrFields, $objLgu): Listing {
                $objCategory = Category::query()->findOrFail($arrFields['cat_id']);

                $objNewListing = Listing::query()->make([
                    ...$arrFields,
                    'slug' => $this->uniqueDestinationSlug($arrFields['name']),
                    'category' => $objCategory->legacySlug(),
                    // Municipality always comes from the LGU's own account.
                    'municipality' => $objLgu->organization_subtitle,
                    'municipality_id' => $objLgu->municipality_id,
                    // Not requested as a destination listing yet.
                    'status' => 'DRAFT',
                ]);
                $objNewListing->reporting_mode = ReportingMethod::default();
                $objNewListing->save();

                return $objNewListing;
            });
        } catch (\Throwable $e) {
            Log::error('Failed to create LGU establishment.', ['exception' => $e]);

            return back()->withInput()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::created($objLgu, 'establishment', $objListing->id, $objListing->municipality_id, $objListing->id, [
            'name' => $objListing->name,
            'category' => $objListing->categoryRecord?->cat_name,
            'type' => $objListing->type,
            'municipality' => $objListing->municipality,
            'reporting_mode' => $objListing->reportingMethod()->value,
        ]);

        $arrPhotos = $request->file('photos', []);

        if ($arrPhotos === []) {
            return redirect()->route('lgu.directory.establishments.show', $objListing)
                ->with('toast', "{$objListing->name} was added.");
        }

        // Summary comment: photos go through the unchanged photo workflow.
        try {
            $objUploader->upload($objLgu, $objListing, $arrPhotos, $request->validated('credit'));
        } catch (ValidationException $e) {
            return redirect()->route('lgu.directory.establishments.edit', $objListing)
                ->withFragment('photos')
                ->with('toast', "{$objListing->name} was added, but the photos were not: {$e->validator->errors()->first()}")
                ->with('toast_tone', 'danger');
        } catch (\Throwable $e) {
            Log::error('Failed to upload photos for a new LGU establishment.', ['exception' => $e, 'listing_id' => $objListing->id]);

            return redirect()->route('lgu.directory.establishments.edit', $objListing)
                ->withFragment('photos')
                ->with('toast', "{$objListing->name} was added, but the photos could not be uploaded. Please try again below.")
                ->with('toast_tone', 'danger');
        }

        return redirect()->route('lgu.directory.establishments.show', $objListing)
            ->with('toast', "{$objListing->name} was added. Its photos were sent to the PTO for approval.");
    }

    /**
     * Establishment details: information, photos, reporting method,
     * account, QR, and destination listing state.
     */
    public function show(Request $request, Listing $listing, ImagePolicy $objImagePolicy): View
    {
        $this->_authorizeOwnEstablishment($request, $listing);

        $listing->load(['categoryRecord', 'establishmentUser', 'establishmentImages']);

        return $this->renderLgu($request, 'lgu.directory.establishments.show', 'directory.establishments', $listing->name, [
            'listing' => $listing,
            'blnCanUploadPhotos' => $objImagePolicy->uploadFor($request->user(), $listing),
            'blnCanManageQr' => $request->user()->can('manageQr', $listing),
        ]);
    }

    public function edit(Request $request, Listing $listing, ImagePolicy $objImagePolicy): View
    {
        $this->_authorizeOwnEstablishment($request, $listing);

        $listing->load(['categoryRecord', 'establishmentImages']);

        return $this->renderLgu($request, 'lgu.directory.establishments.edit', 'directory.establishments', "Edit {$listing->name}", [
            'listing' => $listing,
            // A Published listing's held changes are what the LGU is editing.
            'formListing' => $listing->withPendingChanges(),
            'categories' => $this->_establishmentCategories(),
            'blnCanUploadPhotos' => $objImagePolicy->uploadFor($request->user(), $listing),
        ]);
    }

    /**
     * Authorization (own municipality, security-logged) happens in
     * SaveEstablishmentRequest::authorize(). The municipality and status
     * never change here. On a Published listing, edits to public
     * destination content are held for PTO review
     * (ListingPublishWorkflow::submitPendingChanges()) — the published
     * version stays live — while contact and operating fields save
     * straight away.
     */
    public function update(SaveEstablishmentRequest $request, Listing $listing, ListingPublishWorkflow $objWorkflow): RedirectResponse
    {
        abort_if($listing->category === 'destinations', 404);

        $arrFields = $this->_normalizedFields($request->establishmentFields());
        $blnIsPublished = $listing->isPubliclyVisible();
        $arrProposed = [];

        if ($blnIsPublished) {
            $arrProposed = $listing->publicFieldChanges($arrFields);
            $arrFields = array_diff_key($arrFields, array_flip(Listing::PUBLIC_CONTENT_FIELDS));
        }

        if (isset($arrFields['cat_id'])) {
            $arrFields['category'] = Category::query()->findOrFail($arrFields['cat_id'])->legacySlug();
        }

        $arrBefore = $listing->getOriginal();

        try {
            $listing->update($arrFields);
        } catch (\Throwable $e) {
            Log::error('Failed to update LGU establishment.', ['exception' => $e, 'listing_id' => $listing->id]);

            return back()->withInput()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated($request->user(), 'establishment', $listing->id, $listing->municipality_id, $listing->id, OperationLogger::diff($arrBefore, $listing));

        if (! $blnIsPublished) {
            return redirect()->route('lgu.directory.establishments.show', $listing)->with('toast', 'Establishment saved.');
        }

        try {
            $objWorkflow->submitPendingChanges($request->user(), $listing, $arrProposed);
        } catch (ValidationException $e) {
            return redirect()->route('lgu.directory.establishments.show', $listing)
                ->with('toast', $e->validator->errors()->first())->with('toast_tone', 'danger');
        }

        $strToast = $arrProposed === []
            ? 'Establishment saved.'
            : 'Saved. Changes to the public listing were sent to the PTO for review — the published version stays live until they are approved.';

        return redirect()->route('lgu.directory.establishments.show', $listing)->with('toast', $strToast);
    }

    /**
     * Own municipality (403 + security log otherwise), establishments only
     * — a destination's slug is not an establishment page (404).
     */
    private function _authorizeOwnEstablishment(Request $request, Listing $objListing): void
    {
        $this->authorizeOwnMunicipality($request, $objListing);
        abort_if($objListing->category === 'destinations', 404);
    }

    /**
     * Establishments in the signed-in LGU's municipality only — derived
     * from the account, never from the request.
     */
    private function _ownEstablishmentsQuery(Request $request)
    {
        return Listing::query()
            ->where('municipality_id', $request->user()->municipality_id)
            ->where('category', '!=', 'destinations');
    }

    /**
     * Active establishment categories in display order — never Tourist
     * Destinations (R13).
     *
     * @return Collection<int, Category>
     */
    private function _establishmentCategories(): Collection
    {
        return Category::query()->active()->forEstablishments()->get();
    }

    /**
     * `barangay` is NOT NULL in the schema but optional for a tour guide;
     * store an empty string instead of failing the insert.
     *
     * @param  array<string, mixed>  $arrFields
     * @return array<string, mixed>
     */
    private function _normalizedFields(array $arrFields): array
    {
        if (array_key_exists('barangay', $arrFields)) {
            $arrFields['barangay'] ??= '';
        }

        return $arrFields;
    }
}
