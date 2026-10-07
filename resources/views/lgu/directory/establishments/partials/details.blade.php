{{-- Establishment information (details page). Expects $listing. --}}
@php
    $arrDetails = [
        'Category' => $listing->categoryName(),
        'Establishment type' => $listing->type ?? 'Not set',
        'Owner / Manager' => $listing->owner_name,
        'Barangay / Address' => $listing->barangay,
        'Municipality / City' => $listing->municipality,
        'Coordinates' => $listing->lat !== null && $listing->lng !== null ? $listing->lat.', '.$listing->lng : null,
        'Contact person / office' => $listing->contact_office,
        'Contact number' => $listing->contact_phone,
        'Email' => $listing->email,
        'Website or social page' => $listing->website,
        'Operating hours' => $listing->hours,
        'Accreditation status' => $listing->accreditation_status,
        'License number' => $listing->license_number,
        'Category note' => $listing->category_note,
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
            <dd class="detail-value whitespace-pre-line">{{ $listing->description ?: 'No description yet.' }}</dd>
        </div>
    </dl>
</section>
