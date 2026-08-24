@props(['icon' => 'ti-map-search', 'title' => 'Nothing here yet', 'description' => null])

<div {{ $attributes->class(['flex flex-col items-center justify-center rounded-md border border-sand-200 bg-sand-0 px-6 py-16 text-center']) }}>
    <i class="ti {{ $icon }} mb-3 text-3xl text-sand-400" aria-hidden="true"></i>
    <p class="font-display text-base font-bold text-sand-900">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-sand-600">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
