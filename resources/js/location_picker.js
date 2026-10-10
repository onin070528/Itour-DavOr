/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Mapbox location picker for the LGU/PTO listing forms
 * (resources/views/components/dashboard/location-picker.blade.php).
 * Click the map or drag the pin to fill the form's latitude/longitude
 * inputs; typing in the inputs moves the pin. The inputs stay the real
 * form fields — the server validates them again on save
 * (App\Rules\WithinDavaoOrientalBounds), so nothing here is trusted. The
 * outside-the-province note is only a hint. Mapbox order is [lng, lat].
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

/** Pin color — the destination color of the public maps. */
const PICKER_PIN_COLOR = '#125d5a';

/** Decimal places written to the inputs (about 0.1 m). */
const PICKER_COORDINATE_DECIMALS = 6;

/**
 * Every [data-location-picker] on the page.
 */
export function initLocationPickers() {
    document.querySelectorAll('[data-location-picker]').forEach((root) => initLocationPicker(root));
}

function initLocationPicker(root) {
    const latitudeInput = document.getElementById(root.dataset.latitudeInput);
    const longitudeInput = document.getElementById(root.dataset.longitudeInput);
    const mapElement = root.querySelector('[data-location-picker-map]');
    const warning = root.querySelector('[data-location-picker-warning]');
    const clearButton = root.querySelector('[data-location-picker-clear]');

    if (!latitudeInput || !longitudeInput || !mapElement) {
        return;
    }

    const bounds = {
        minLatitude: Number(root.dataset.minLatitude),
        maxLatitude: Number(root.dataset.maxLatitude),
        minLongitude: Number(root.dataset.minLongitude),
        maxLongitude: Number(root.dataset.maxLongitude),
    };
    const isDisabled = root.dataset.disabled === 'true' || latitudeInput.disabled;
    const token = root.dataset.mapboxToken;
    let map = null;
    let marker = null;
    let isMarkerShown = false;

    if (isDisabled && clearButton) {
        clearButton.hidden = true;
    }

    // Summary comment: inputs <-> pin, both ways; typing never loops back.
    latitudeInput.addEventListener('input', () => syncFromInputs(false));
    longitudeInput.addEventListener('input', () => syncFromInputs(false));
    clearButton?.addEventListener('click', clearLocation);

    if (!window.mapboxgl || !token || !mapboxgl.supported()) {
        console.error('[location-picker] mapbox-gl unavailable, no public token, or no WebGL — coordinates can still be typed.');
        showFallback();
        return;
    }

    // Summary comment: build the map only once it is visible (a map made in a
    // hidden modal measures 0x0), and re-read the inputs every time it is
    // shown again — the PTO modal fills them without input events.
    const observer = new IntersectionObserver((entries) => {
        const isVisible = entries.some((entry) => entry.isIntersecting);

        if (!isVisible) {
            return;
        }

        if (!map) {
            createMap();
        } else {
            map.resize();
        }

        syncFromInputs(true);
    });
    observer.observe(mapElement);

    function createMap() {
        try {
            mapboxgl.accessToken = token;
            map = new mapboxgl.Map({
                container: mapElement,
                style: 'mapbox://styles/mapbox/streets-v12',
                center: [Number(root.dataset.centerLongitude), Number(root.dataset.centerLatitude)],
                zoom: 8,
            });
        } catch (error) {
            console.error('[location-picker] mapboxgl.Map() threw:', error);
            showFallback();
            return;
        }

        let isLoaded = false;
        map.on('load', () => {
            isLoaded = true;
        });
        map.on('error', (event) => {
            console.error('[location-picker] map error:', event?.error ?? event);

            if (!isLoaded) {
                showFallback();
            }
        });
        map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');

        marker = new mapboxgl.Marker({ color: PICKER_PIN_COLOR, draggable: !isDisabled });
        marker.on('dragend', () => {
            const position = marker.getLngLat();
            writeInputs(position.lat, position.lng);
        });

        if (!isDisabled) {
            map.on('click', (event) => {
                placeMarker(event.lngLat.lat, event.lngLat.lng);
                writeInputs(event.lngLat.lat, event.lngLat.lng);
            });
        }
    }

    /**
     * Moves (or removes) the pin to match the inputs. With $shouldCenter the
     * map also jumps to the pin, once — no animation.
     */
    function syncFromInputs(shouldCenter) {
        const latitude = parseCoordinate(latitudeInput.value);
        const longitude = parseCoordinate(longitudeInput.value);
        const isValidPoint = latitude !== null && longitude !== null
            && latitude >= -90 && latitude <= 90 && longitude >= -180 && longitude <= 180;

        updateWarning(isValidPoint ? latitude : null, isValidPoint ? longitude : null);

        if (!map || !marker) {
            return;
        }

        if (!isValidPoint) {
            hideMarker();
            return;
        }

        placeMarker(latitude, longitude);

        if (shouldCenter) {
            map.jumpTo({ center: [longitude, latitude], zoom: Math.max(map.getZoom(), 13) });
        }
    }

    function placeMarker(latitude, longitude) {
        marker.setLngLat([longitude, latitude]);

        if (!isMarkerShown) {
            marker.addTo(map);
            isMarkerShown = true;
        }
    }

    function hideMarker() {
        marker?.remove();
        isMarkerShown = false;
    }

    function writeInputs(latitude, longitude) {
        latitudeInput.value = latitude.toFixed(PICKER_COORDINATE_DECIMALS);
        longitudeInput.value = longitude.toFixed(PICKER_COORDINATE_DECIMALS);
        updateWarning(latitude, longitude);
    }

    function clearLocation() {
        latitudeInput.value = '';
        longitudeInput.value = '';
        hideMarker();
        updateWarning(null, null);
    }

    /**
     * A hint only — the server rejects points outside the guard on save.
     */
    function updateWarning(latitude, longitude) {
        if (!warning) {
            return;
        }

        const hasPoint = latitude !== null && longitude !== null;
        const isInside = hasPoint
            && latitude >= bounds.minLatitude && latitude <= bounds.maxLatitude
            && longitude >= bounds.minLongitude && longitude <= bounds.maxLongitude;

        warning.hidden = !hasPoint || isInside;
    }

    function showFallback() {
        const fallback = root.querySelector('[data-map-fallback]');

        if (fallback) {
            fallback.hidden = false;
        }
    }
}

/**
 * A typed coordinate as a number, or null when blank or not a number.
 */
function parseCoordinate(value) {
    const trimmed = String(value ?? '').trim();

    if (trimmed === '') {
        return null;
    }

    const number = Number(trimmed);

    return Number.isFinite(number) ? number : null;
}
