import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

// Optional LAN testing (e.g. scanning a QR code with a phone on the same
// Wi-Fi). When VITE_DEV_LAN_HOST is set to this PC's LAN IP in .env, the dev
// server listens on all interfaces and public/hot advertises that IP instead
// of localhost/[::1], which a phone cannot reach. Unset = default behavior.
const strLanHost = loadEnv('development', process.cwd(), '').VITE_DEV_LAN_HOST;

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/ori_launcher.css',
                'resources/js/app.js',
                'resources/js/dashboard.js',
                'resources/js/establishment.js',
                'resources/js/storage_notice.js',
                'resources/js/user_account.js',
            ],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Manrope', {
                    weights: [500, 600, 700, 800],
                }),
            ],
        }),
        tailwindcss(),
        // Offline caching for the public Hotlines page (Stage 5) — a tourist
        // who loses signal mid-trip can still reach provincial emergency
        // numbers. Scoped to just that page/data rather than the whole app,
        // since nothing else here has been asked to work offline yet.
        VitePWA({
            registerType: 'autoUpdate',
            // Laravel/Blade has no single HTML entry for 'auto' injection to
            // hook into — resources/js/app.js registers the service worker
            // itself via the virtual:pwa-register module instead.
            injectRegister: false,
            // Vite's own build output is public/build/ (hashed assets), but
            // a service worker can only ever control pages under its own
            // directory — writing it there would scope it to /build/* and
            // it could never see /hotlines. public/ (the site root) is
            // Laravel's actual document root, so sw.js lands at /sw.js and
            // can control the whole origin.
            outDir: 'public',
            manifest: {
                name: 'iTOUR — Davao Oriental Tourism',
                short_name: 'iTOUR',
                start_url: '/',
                display: 'standalone',
                background_color: '#faf6f0',
                theme_color: '#0f6e63',
                icons: [
                    { src: '/favicon.ico', sizes: '48x48', type: 'image/x-icon' },
                ],
            },
            workbox: {
                navigateFallback: null,
                globPatterns: [],
                runtimeCaching: [
                    {
                        urlPattern: ({ url, request }) => request.mode === 'navigate' && url.pathname === '/hotlines',
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'itour-hotlines-page',
                            networkTimeoutSeconds: 3,
                            expiration: { maxEntries: 1, maxAgeSeconds: 60 * 60 * 24 * 7 },
                        },
                    },
                    // Storage & Cache Usage notice's own built JS — hashed/
                    // immutable like every Vite asset, so CacheFirst is safe:
                    // a content change always ships under a new filename.
                    {
                        urlPattern: ({ url }) => url.pathname.startsWith('/build/assets/storage_notice-'),
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'itour-storage-notice-assets',
                            expiration: { maxEntries: 4, maxAgeSeconds: 60 * 60 * 24 * 30 },
                        },
                    },
                ],
            },
        }),
    ],
    server: {
        ...(strLanHost ? {
            host: '0.0.0.0',
            hmr: { host: strLanHost },
            cors: true,
        } : {}),
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
