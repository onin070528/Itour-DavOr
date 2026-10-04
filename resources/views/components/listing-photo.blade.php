@props(['listing'])

{{-- Shared cover-photo slot for every public card (Explore, landing preview
     cards, Signature Experiences): the cover photo when one exists,
     otherwise a neutral category icon (7E — never a stock/DOT placeholder
     photo). $listing is the TourismCatalog::listings() array shape. --}}
@if ($listing['displayImageUrl'])
    <img
        src="{{ $listing['displayImageUrl'] }}"
        alt="{{ $listing['name'] }}"
        loading="lazy"
        {{ $attributes->merge(['class' => 'absolute inset-0 h-full w-full object-cover']) }}
    >
@else
    <div {{ $attributes->merge(['class' => 'absolute inset-0 flex items-center justify-center bg-sand-200']) }}>
        <i class="ti {{ $listing['categoryIcon'] }} text-4xl text-sand-400" aria-hidden="true"></i>
    </div>
@endif
