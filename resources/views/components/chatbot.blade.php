{{--
    Floating Ori tourism assistant widget, pinned to the bottom-right corner of
    the public site. The panel and message list are fully wired up; the reply
    in app.js's initChatbot() is a placeholder — swap it for a real request
    to your AI backend once one exists.
--}}
<div id="chatbot-root" class="fixed bottom-5 right-5 z-50 flex flex-col items-end gap-3 sm:bottom-6 sm:right-6">
    <div
        id="chatbot-panel"
        class="hidden w-[calc(100vw-2.5rem)] max-w-sm flex-col overflow-hidden rounded-2xl border border-sand-200 bg-sand-0 shadow-2xl"
        role="dialog"
        aria-modal="false"
        aria-labelledby="chatbot-heading"
    >
        <div class="flex items-center justify-between gap-3 border-b border-sand-100 bg-sand-50 px-4 py-3">
            <div class="flex items-center gap-2 text-sand-900">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-accent-500 bg-sand-0">
                    <img
                        src="{{ asset('storage/itour-images/ori-chatbot-ai.png') }}"
                        alt="Ori"
                        class="h-full w-full object-cover"
                    >
                </span>
                <div>
                    <p id="chatbot-heading" class="text-sm font-semibold leading-tight text-sand-900">Ori <span class="text-sand-400">&bull;</span> iTOUR Guide</p>
                    <p class="text-xs leading-tight text-sand-500">Your iTOUR Tourism Assistant</p>
                </div>
            </div>
            <button
                type="button"
                id="chatbot-close"
                class="rounded-sm p-1.5 text-sand-500 transition-colors hover:bg-sand-100 hover:text-sand-800"
                aria-label="Close chat"
            >
                <i class="ti ti-x text-lg" aria-hidden="true"></i>
            </button>
        </div>

        <div id="chatbot-messages" class="flex h-96 flex-col gap-3 overflow-y-auto bg-sand-0 px-4 py-4">
            <div class="flex items-start gap-2">
                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full border border-accent-300">
                    <img
                        src="{{ asset('storage/itour-images/ori-chatbot-ai.png') }}"
                        alt="Ori"
                        class="h-full w-full object-cover"
                    >
                </span>
                <div class="max-w-[85%] rounded-md rounded-tl-none bg-sand-100 px-3 py-2 text-sm leading-relaxed text-sand-800">
                    <p class="mb-2 font-medium text-sand-900">Hi! I'm Ori. 👋</p>
                    <p>I can help you explore tourism information in Davao Oriental, including destinations, accommodations, activities, and other available tourism information.</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 pl-9">
                <button type="button" data-chatbot-suggestion class="chatbot-suggestion rounded-full border border-primary-300 bg-sand-0 px-3 py-1.5 text-xs font-medium text-primary-700 transition-colors hover:bg-primary-100">
                    What places can I visit in Mati?
                </button>
                <button type="button" data-chatbot-suggestion class="chatbot-suggestion rounded-full border border-primary-300 bg-sand-0 px-3 py-1.5 text-xs font-medium text-primary-700 transition-colors hover:bg-primary-100">
                    What destinations are near me?
                </button>
                <button type="button" data-chatbot-suggestion class="chatbot-suggestion rounded-full border border-primary-300 bg-sand-0 px-3 py-1.5 text-xs font-medium text-primary-700 transition-colors hover:bg-primary-100">
                    Where can I stay in Davao Oriental?
                </button>
                <button type="button" data-chatbot-suggestion class="chatbot-suggestion rounded-full border border-primary-300 bg-sand-0 px-3 py-1.5 text-xs font-medium text-primary-700 transition-colors hover:bg-primary-100">
                    What tourism activities are available?
                </button>
            </div>
        </div>

        <form id="chatbot-form" class="flex items-center gap-2 border-t border-sand-200 bg-sand-0 p-3">
            <label for="chatbot-input" class="sr-only">Type your message</label>
            <input
                id="chatbot-input"
                type="text"
                autocomplete="off"
                placeholder="Ask Ori about Davao Oriental…"
                class="w-full flex-1 rounded-sm border border-sand-300 bg-sand-0 px-3 py-2 text-sm text-sand-800 placeholder-sand-400 focus:border-primary-500 focus:outline-none"
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
        class="group flex items-center gap-2.5 rounded-full border-2 border-accent-400 bg-sand-0 py-1.5 pl-1.5 pr-1.5 shadow-[0_4px_18px_rgba(0,0,0,0.22)] transition-all duration-200 hover:scale-105 hover:border-accent-500 hover:shadow-[0_6px_22px_rgba(0,0,0,0.26),0_0_16px_rgba(249,115,22,0.25)] sm:pr-4"
        aria-expanded="false"
        aria-controls="chatbot-panel"
        aria-label="Chat with Ori, your iTOUR tourism assistant"
    >
        <span class="relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-sand-0">
            <img
                id="chatbot-toggle-avatar"
                src="{{ asset('storage/itour-images/ori-chatbot-ai.png') }}"
                alt="Ori"
                class="h-full w-full object-cover"
            >
            <i id="chatbot-toggle-icon-close" class="ti ti-x hidden text-xl text-primary-700" aria-hidden="true"></i>
        </span>
        <span class="hidden pr-1 text-sm font-semibold text-sand-800 sm:inline">Ask Ori</span>
    </button>
</div>
