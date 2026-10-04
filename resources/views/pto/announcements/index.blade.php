@php
    $typeTone = fn ($type) => match ($type) {
        'Promotion' => 'success',
        'Advisory' => 'danger',
        'Event' => 'info',
        default => 'neutral',
    };
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Announcements"
        description="Promotions, advisories, and events — published items appear on the public landing page within their scheduled window."
    >
        <x-slot:actions>
            <button type="button" data-modal-open="announcement-form-modal" data-modal-mode="add" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-plus" aria-hidden="true"></i>
                New Announcement
            </button>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div class="mt-6 flex flex-col gap-3">
        @forelse ($announcements as $announcement)
            <div class="rounded-md border border-sand-200 bg-sand-0 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <x-dashboard.status-badge :tone="$typeTone($announcement->ann_type)">{{ $announcement->ann_type }}</x-dashboard.status-badge>
                            <x-dashboard.status-badge :tone="$announcement->ann_is_published ? 'success' : 'neutral'">{{ $announcement->ann_is_published ? 'Published' : 'Draft' }}</x-dashboard.status-badge>
                        </div>
                        <p class="mt-1.5 font-display text-sm font-bold text-sand-900">{{ $announcement->ann_title }}</p>
                        <p class="mt-1 line-clamp-2 text-sm text-sand-600">{{ $announcement->ann_body }}</p>
                        <p class="mt-1.5 text-xs text-sand-500">
                            {{ $announcement->ann_start_date?->format('M j, Y') ?? 'No start date' }}
                            – {{ $announcement->ann_end_date?->format('M j, Y') ?? 'No end date' }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            data-modal-open="announcement-form-modal"
                            data-edit-trigger="announcement-form-modal"
                            data-edit-values="{{ json_encode([
                                'ann_title' => $announcement->ann_title,
                                'ann_body' => $announcement->ann_body,
                                'ann_type' => $announcement->ann_type,
                                'ann_start_date' => $announcement->ann_start_date?->toDateString(),
                                'ann_end_date' => $announcement->ann_end_date?->toDateString(),
                            ]) }}"
                            data-edit-action="{{ route('pto.announcements.update', $announcement) }}"
                            class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300"
                        >
                            Edit
                        </button>
                        <form method="POST" action="{{ route('pto.announcements.togglePublish', $announcement) }}">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="rounded-sm border px-3 py-1.5 text-xs font-semibold {{ $announcement->ann_is_published ? 'border-danger/30 text-danger hover:bg-danger-bg' : 'border-primary-300 text-primary-700 hover:bg-primary-100/50' }}">
                                {{ $announcement->ann_is_published ? 'Unpublish' : 'Publish' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <x-dashboard.empty-state icon="ti-speakerphone" title="No announcements yet" description="Create one above — it stays a draft until you publish it." />
        @endforelse
    </div>

    <x-dashboard.modal id="announcement-form-modal" title="Announcement" max-width="max-w-2xl">
        <form
            id="announcement-form"
            method="POST"
            action="{{ route('pto.announcements.store') }}"
            data-default-action="{{ route('pto.announcements.store') }}"
            data-default-method="POST"
            class="flex flex-col gap-4"
        >
            @csrf
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Title <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="ann_title" type="text" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Body <span class="text-danger" aria-hidden="true">*</span></label>
                <textarea name="ann_body" rows="4" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Type <span class="text-danger" aria-hidden="true">*</span></label>
                    <select name="ann_type" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                        @foreach ($types as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Start Date</label>
                    <input name="ann_start_date" type="date" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">End Date</label>
                    <input name="ann_end_date" type="date" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                </div>
            </div>
            <p class="text-xs text-sand-500">New announcements save as a draft — use Publish from the list once you're ready.</p>
        </form>

        <x-slot:footer>
            <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
            <button type="submit" form="announcement-form" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">Save Announcement</button>
        </x-slot:footer>
    </x-dashboard.modal>
</x-layouts.dashboard>
