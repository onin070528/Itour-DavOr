{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : Dashboard notification bell — the signed-in user's latest Laravel database notifications,
                 an unread count, open (marks read and follows the notification's own iTOUR link), and mark all read.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
@props(['user'])

@php
    $intUnreadCount = $user->unreadNotifications()->count();
    $objNotifications = $user->notifications()->latest()->limit(8)->get();
@endphp

<div class="relative">
    <button type="button" data-dropdown-toggle class="relative text-sand-600 hover:text-sand-900" aria-label="Notifications{{ $intUnreadCount ? " ({$intUnreadCount} unread)" : '' }}">
        <i class="ti ti-bell text-lg" aria-hidden="true"></i>
        @if ($intUnreadCount > 0)
            <span data-notification-count class="absolute -top-1.5 -right-2 min-w-4 rounded-full bg-accent-500 px-1 text-center text-[10px] leading-4 font-bold text-sand-0">{{ $intUnreadCount > 9 ? '9+' : $intUnreadCount }}</span>
        @endif
    </button>

    <div data-dropdown-menu class="absolute right-0 z-30 mt-2 hidden w-80 max-w-[calc(100vw-2rem)] rounded-md border border-sand-200 bg-sand-0 shadow-md">
        <div class="flex items-center justify-between border-b border-sand-200 px-3.5 py-2.5">
            <p class="text-sm font-semibold text-sand-900">Notifications</p>
            @if ($intUnreadCount > 0)
                <form method="POST" action="{{ route('notifications.readAll') }}">
                    @csrf
                    <button type="submit" class="text-xs font-semibold text-primary-700 hover:text-primary-900">Mark all as read</button>
                </form>
            @endif
        </div>

        <ul class="max-h-96 divide-y divide-sand-100 overflow-y-auto">
            @forelse ($objNotifications as $objNotification)
                <li>
                    <form method="POST" action="{{ route('notifications.open', $objNotification->id) }}">
                        @csrf
                        <button type="submit" @class(['flex w-full items-start gap-2.5 px-3.5 py-2.5 text-left hover:bg-sand-50', 'bg-primary-100/40' => $objNotification->read_at === null])>
                            <span @class(['mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full', 'bg-accent-500' => $objNotification->read_at === null, 'bg-transparent' => $objNotification->read_at !== null]) aria-hidden="true"></span>
                            <span class="min-w-0">
                                <span class="block text-xs text-sand-800">{{ $objNotification->data['message'] ?? 'Notification' }}</span>
                                <span class="mt-0.5 block text-[11px] text-sand-500">{{ $objNotification->created_at->diffForHumans() }}</span>
                            </span>
                        </button>
                    </form>
                </li>
            @empty
                <li class="px-3.5 py-6 text-center text-xs text-sand-500">No notifications yet.</li>
            @endforelse
        </ul>
    </div>
</div>
