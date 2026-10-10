{{-- Establishment information (details page). Expects $listing. --}}
@php
    $arrDetails = [
        'Category' => $listing->categoryName(),
        'Establishment type' => $listing->lst_type ?? 'Not set',
        'Owner / Manager' => $listing->lst_owner_name,
        'Barangay / Address' => $listing->lst_barangay,
        'Municipality / City' => $listing->lst_municipality,
        'Coordinates' => $listing->lst_lat !== null && $listing->lst_lng !== null ? $listing->lst_lat.', '.$listing->lst_lng : null,
        'Contact person / office' => $listing->lst_contact_office,
        'Contact number' => $listing->lst_contact_phone,
        'Email' => $listing->lst_email,
        'Website or social page' => $listing->lst_website,
        'Operating hours' => $listing->lst_hours,
        'Accreditation status' => $listing->lst_accreditation_status,
        'License number' => $listing->lst_license_number,
        'Category note' => $listing->lst_category_note,
    ];
@endphp

<section class="dashboard-panel">
    <h2 class="dashboard-panel-title">Establishment information</h2>

    <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach ($arrDetails as $strTerm => $strValue)
            @continue($strValue === null || $strValue === '')
            <div>
                <dt class="detail-term">{{ $strTerm }}</dt>
                <dd class="detail-value">{{ $strValue }}</dd>
            </div>
        @endforeach

        <div class="sm:col-span-2">
            <dt class="detail-term">Description</dt>
            <dd class="detail-value whitespace-pre-line">{{ $listing->lst_description ?: 'No description yet.' }}</dd>
        </div>
    </dl>
</section>
