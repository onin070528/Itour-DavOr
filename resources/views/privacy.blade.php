{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Public privacy notice.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.public title="Privacy Notice">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-2xl sm:text-3xl">Privacy Notice</h1>
        <p class="mt-2 text-sm text-sand-600">Last reviewed: {{ now()->format('F Y') }}</p>

        <div class="mt-8 space-y-8 text-sm leading-relaxed text-sand-700 sm:text-base">
            <section>
                <h2 class="font-display text-lg font-bold text-sand-900">Who we are</h2>
                <p class="mt-2">
                    iTOUR is operated by the Provincial Tourism Office (PTO) of Davao Oriental, with the
                    municipal tourism offices (LGUs) and registered tourism establishments of the province
                    as co-users of the system.
                </p>
            </section>

            <section>
                <h2 class="font-display text-lg font-bold text-sand-900">What we collect</h2>
                <ul class="mt-2 list-disc space-y-1.5 pl-5">
                    <li>
                        <strong>QR visitor arrival forms</strong> — name, gender, age group, place of origin
                        (local/foreign), and companion headcounts, submitted when you scan an establishment's
                        check-in QR code.
                    </li>
                    <li>
                        <strong>Tourist feedback</strong> you choose to submit about a destination or establishment — your
                        feedback text, and optionally your name and visit date, together with the time you gave consent.
                        No account, contact details, or location are collected with it.
                    </li>
                    <li><strong>Staff accounts</strong> — name, email, and role, for PTO, LGU, and establishment users who log in to manage listings.</li>
                </ul>
                <p class="mt-3">
                    <strong>Your location (Find Near Me).</strong> Only when you choose Allow Location, your device's
                    current location is sent once to iTOUR, rounded to about 11 metres, and used for that one
                    nearby search. It is not stored in our database, logs, or your session, and it is never
                    shared with anyone else.
                </p>
            </section>

            <section>
                <h2 class="font-display text-lg font-bold text-sand-900">Why we collect it</h2>
                <p class="mt-2">
                    Arrival and feedback data is used to produce tourism statistics and reports for the
                    province and its municipalities — visitor volume, origin, and satisfaction trends that
                    inform tourism planning. Staff account data exists only so authorized users can manage
                    listings, reports, and photos.
                </p>
                <p class="mt-3">
                    <strong>How feedback is analyzed.</strong> Feedback is read in English. If you write in another
                    language (for example Bisaya or Tagalog), its text is first sent to an external translation service
                    to be translated into English. The English text is then scored with a fixed tourism word list — not
                    AI — to measure positive, neutral, or negative experiences and recurring concerns for tourism
                    analysis. Your original words are kept as you wrote them, and the results are never shown publicly.
                </p>
            </section>

            <section>
                <h2 class="font-display text-lg font-bold text-sand-900">Who sees it</h2>
                <p class="mt-2">
                    Arrival and feedback data tied to a specific establishment is visible to the PTO, the
                    LGU tourism office of that establishment's municipality, and the establishment itself.
                    Consolidated statistics (not individual visitor records) may be shared more broadly as
                    part of provincial tourism reporting.
                </p>
            </section>

            <section>
                <h2 class="font-display text-lg font-bold text-sand-900">Third parties</h2>
                <p class="mt-2">We don't sell your data. A few third-party services help run the site and do receive some data:</p>
                <ul class="mt-2 list-disc space-y-1.5 pl-5">
                    <li><strong>Mapbox</strong> — powers the maps; receives your IP address and the map area you view.</li>
                    <li><strong>Google Maps</strong> — opens only when you choose Get Directions, with the destination's location. iTOUR does not send your location; Google Maps may ask for it itself.</li>
                    <li><strong>OpenAI (translation service)</strong> — receives only the text of feedback that is not in English, so it can be translated into English for tourism analysis. Your name, visit date, and IP address are not sent with it. Please don't include personal details in your feedback.</li>
                    <li><strong>Our hosting provider</strong> — stores the application and its database, and sees standard web traffic logs (IP address, pages requested) like any hosted web application.</li>
                </ul>
            </section>

            <section>
                <h2 class="font-display text-lg font-bold text-sand-900">How long we keep it</h2>
                <p class="mt-2 rounded-sm border border-dashed border-sand-300 bg-sand-100 px-4 py-3">
                    <strong>Not yet confirmed.</strong> A specific retention period for arrival records and
                    feedback is to be confirmed by the Provincial Tourism Office. This page will be updated
                    once that period is set.
                </p>
            </section>

            <section>
                <h2 class="font-display text-lg font-bold text-sand-900">Your rights</h2>
                <p class="mt-2">
                    Under the Data Privacy Act of 2012 (Republic Act No. 10173), you have the right to be
                    informed, to access, to object, to correct, to erase or block, to data portability, and
                    to file a complaint regarding your personal data. To exercise any of these rights,
                    contact us using the details below.
                </p>
            </section>

            <section>
                <h2 class="font-display text-lg font-bold text-sand-900">Contact</h2>
                <p class="mt-2 rounded-sm border border-dashed border-sand-300 bg-sand-100 px-4 py-3">
                    <strong>Not yet confirmed.</strong> A designated Data Protection Officer (DPO) contact
                    for the Provincial Tourism Office has not been published here yet. In the meantime,
                    reach the PTO at
                    <a href="mailto:tourism@davaooriental.gov.ph" class="font-semibold text-primary-700 hover:text-primary-900">tourism@davaooriental.gov.ph</a>
                    or (087) 388 3611.
                </p>
            </section>
        </div>
    </div>
</x-layouts.public>
