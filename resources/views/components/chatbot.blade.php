{{--
    Floating AI assistant widget, pinned to the bottom-right corner of the
    public site. The panel and message list are fully wired up; the reply
    in app.js's initChatbot() is a placeholder — swap it for a real request
    to your AI backend once one exists.
--}}
<div id="chatbot-root" class="fixed bottom-5 right-5 z-50 flex flex-col items-end gap-3 sm:bottom-6 sm:right-6">
    <div
        id="chatbot-panel"
        class="hidden w-[calc(100vw-2.5rem)] max-w-sm flex-col overflow-hidden rounded-md border border-sand-200 bg-sand-0 shadow-xl"
        role="dialog"
        aria-modal="false"
        aria-labelledby="chatbot-heading"
    >
        <div class="flex items-center justify-between gap-3 bg-primary-700 px-4 py-3">
            <div class="flex items-center gap-2 text-sand-0">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/15">
                    <i class="ti ti-message-chatbot text-lg" aria-hidden="true"></i>
                </span>
                <div>
                    <p id="chatbot-heading" class="text-sm font-semibold leading-tight">iTOUR Assistant</p>
                    <p class="text-xs leading-tight text-white/70">Ask about Davao Oriental</p>
                </div>
            </div>
            <button
                type="button"
                id="chatbot-close"
                class="rounded-sm p-1.5 text-white/80 transition-colors hover:bg-white/10 hover:text-sand-0"
                aria-label="Close chat"
            >
                <i class="ti ti-x text-lg" aria-hidden="true"></i>
            </button>
        </div>

        <div id="chatbot-messages" class="flex h-96 flex-col gap-3 overflow-y-auto px-4 py-4">
            <div class="flex items-start gap-2">
                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700">
                    <i class="ti ti-message-chatbot text-sm" aria-hidden="true"></i>
                </span>
                <div class="max-w-[85%] rounded-md rounded-tl-none bg-sand-100 px-3 py-2 text-sm leading-relaxed text-sand-800">
                    Hi! I'm the iTOUR assistant. Ask me about destinations, accommodations, restaurants, or things to do across Davao Oriental.
                </div>
            </div>
        </div>

        <form id="chatbot-form" class="flex items-center gap-2 border-t border-sand-200 p-3">
            <label for="chatbot-input" class="sr-only">Type your message</label>
            <input
                id="chatbot-input"
                type="text"
                autocomplete="off"
                placeholder="Type a message…"
                class="w-full flex-1 rounded-sm border border-sand-300 bg-sand-0 px-3 py-2 text-sm text-sand-900 placeholder:text-sand-500 focus:border-primary-500 focus:outline-none"
            >
            <button
                type="submit"
                class="shrink-0 rounded-sm bg-accent-500 p-2.5 text-sand-0 transition-colors hover:bg-accent-600"
                aria-label="Send message"
            >
                <i class="ti ti-send text-lg" aria-hidden="true"></i>
            </button>
        </form>
    </div>

    <button
        type="button"
        id="chatbot-toggle"
        class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-700 text-sand-0 shadow-lg transition-transform hover:scale-105 hover:bg-primary-900"
        aria-expanded="false"
        aria-controls="chatbot-panel"
        aria-label="Open chat assistant"
    >
        <i id="chatbot-toggle-icon-open" class="ti ti-message-chatbot text-2xl" aria-hidden="true"></i>
        <i id="chatbot-toggle-icon-close" class="ti ti-x hidden text-2xl" aria-hidden="true"></i>
    </button>
</div>
