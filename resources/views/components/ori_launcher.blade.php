{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Standalone "Ask Ori" floating launcher — avatar, glow, and speech bubble (replaces the
    old pill button).
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
{{--
    id="chatbot-toggle" is kept from the previous launcher so the existing
    click-to-open wiring in resources/js/app.js's initChatbot() keeps
    working unchanged until the dedicated behavior sub-stage rewires it.
    aria-expanded is updated at runtime by that same script.

    Deliberately NOT id="chatbot-toggle-avatar" on the <img> below — the old
    script hides that element on open (it used to swap Ori for an X icon).
    This design keeps Ori always visible, so that old behavior is skipped
    (a null getElementById lookup there is a harmless no-op) until the
    behavior sub-stage replaces it properly.
--}}
<button
    type="button"
    id="chatbot-toggle"
    class="ori-launcher"
    aria-label="Open Ori, the iTOUR tourism assistant"
    aria-haspopup="dialog"
    aria-expanded="false"
    aria-controls="chatbot-panel"
>
    <span class="ori-launcher__bubble" id="ori-launcher-bubble">
        Ask Ori
        <span class="ori-launcher__bubble-tail" aria-hidden="true"></span>
    </span>

    <span class="ori-launcher__avatar-wrap">
        <picture>
            <source srcset="{{ asset('images/ori.webp') }}" type="image/webp">
            <img
                class="ori-launcher__avatar"
                src="{{ asset('images/ori.png') }}"
                alt=""
                width="96"
                height="114"
                decoding="async"
            >
        </picture>
    </span>
</button>
