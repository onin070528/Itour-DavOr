<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : The one place an establishment's check-in QR code is built — its URL and its SVG.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Services;

use App\Models\Listing;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

/**
 * Every screen that shows, downloads, or prints a QR code goes through this
 * class, so all of them encode the same URL and look the same. The URL is
 * always the listing's uuid check-in link (never its id or slug), built
 * with route() so the host comes from APP_URL, never hard-coded. The look
 * (brand colors, rounded modules, center logo) lives in config/qr_codes.php.
 */
class QrCodeService
{
    public const DEFAULT_SIZE = 256;

    public const MIN_SIZE = 64;

    public const MAX_SIZE = 1024;

    /** Blank border around the code, in modules (the scanner's "quiet zone"). */
    public const QUIET_ZONE_MODULES = 3;

    /** The center logo may never cover more than this share of the QR's area. */
    public const MAX_LOGO_AREA_RATIO = 0.2;

    /** The embedded logo is downscaled to this pixel width to keep the SVG light. */
    public const LOGO_MAX_PIXEL_WIDTH = 360;

    /**
     * Per-request cache of the embedded logo, keyed by file path.
     *
     * @var array<string, array{strDataUri: string, fltAspectRatio: float}>
     */
    private static array $arrLogoCache = [];

    /**
     * The public check-in URL a listing's QR code encodes
     * (/checkin/{uuid}, route lgu.establishmentQr). The path comes from
     * route() and the host from APP_URL — not from the current request —
     * so a QR generated while browsing via 127.0.0.1 or an internal
     * address still points at the real public site once printed.
     *
     * @throws InvalidArgumentException when the listing has no uuid yet
     */
    public function buildCheckinUrl(Listing $objListing): string
    {
        if ($objListing->uuid === null) {
            throw new InvalidArgumentException("Listing {$objListing->id} has no uuid; run php artisan listings:backfill-uuids.");
        }

        $strBaseUrl = rtrim((string) config('app.url'), '/');
        $strCheckinPath = route('lgu.establishmentQr', ['establishment' => $objListing->uuid], false);

        return $strBaseUrl.$strCheckinPath;
    }

    /**
     * The listing's QR code as SVG markup, or null if it could not be
     * generated at all (logged; callers show a friendly fallback instead of
     * an error page). $intSize is clamped to a sane range. $blnIsBranded
     * overrides config('qr_codes.is_branded'); if branding fails for any
     * reason the plain QR is returned instead, so a usable code always wins
     * over looks.
     */
    public function generateSvg(Listing $objListing, int $intSize = self::DEFAULT_SIZE, ?bool $blnIsBranded = null): ?string
    {
        $intClampedSize = max(self::MIN_SIZE, min(self::MAX_SIZE, $intSize));
        $blnUseBranding = $blnIsBranded ?? (bool) config('qr_codes.is_branded', true);

        try {
            $strCheckinUrl = $this->buildCheckinUrl($objListing);

            // Summary comment: branded first, then the plain fallback.
            if ($blnUseBranding) {
                try {
                    return $this->_generateBrandedSvg($strCheckinUrl, $intClampedSize);
                } catch (Throwable $errBranding) {
                    Log::warning('Branded QR code failed; falling back to the plain QR code.', [
                        'listing_id' => $objListing->id,
                        'exception' => $errBranding,
                    ]);
                } // end try branded
            }

            return $this->_generatePlainSvg($strCheckinUrl, $intClampedSize);
        } catch (Throwable $errGeneration) {
            Log::error('Failed to generate a check-in QR code.', [
                'listing_id' => $objListing->id,
                'exception' => $errGeneration,
            ]);

            return null;
        } // end try generate
    }

    /**
     * Plain black-on-white QR — the fallback whenever branding is off or
     * fails. Same error correction (H) and quiet zone as the branded one.
     */
    private function _generatePlainSvg(string $strCheckinUrl, int $intSize): string
    {
        return (string) QrCode::format('svg')
            ->size($intSize)
            ->margin(self::QUIET_ZONE_MODULES)
            ->errorCorrection('H')
            ->generate($strCheckinUrl);
    }

    /**
     * iTOUR-branded QR: brand-colored rounded modules and eyes on white,
     * error correction H (so the center logo can cover modules safely),
     * then the logo overlaid in SVG (simple-qrcode's merge() is PNG-only
     * and needs imagick, which this server does not have).
     */
    private function _generateBrandedSvg(string $strCheckinUrl, int $intSize): string
    {
        $arrModuleRgb = $this->_hexToRgb((string) config('qr_codes.module_color'));
        $arrEyeRgb = $this->_hexToRgb((string) config('qr_codes.eye_color'));
        $arrBackgroundRgb = $this->_hexToRgb((string) config('qr_codes.background_color'));

        $objGenerator = QrCode::format('svg')
            ->size($intSize)
            ->margin(self::QUIET_ZONE_MODULES)
            ->errorCorrection('H')
            ->style('round', (float) config('qr_codes.module_roundness', 0.5))
            ->color($arrModuleRgb[0], $arrModuleRgb[1], $arrModuleRgb[2])
            ->backgroundColor($arrBackgroundRgb[0], $arrBackgroundRgb[1], $arrBackgroundRgb[2]);

        // Summary comment: all three finder "eyes" get the same eye color.
        for ($intEyeNumber = 0; $intEyeNumber <= 2; $intEyeNumber++) {
            $objGenerator->eyeColor($intEyeNumber, $arrEyeRgb[0], $arrEyeRgb[1], $arrEyeRgb[2], $arrEyeRgb[0], $arrEyeRgb[1], $arrEyeRgb[2]);
        } // end for each eye

        $strSvg = (string) $objGenerator->generate($strCheckinUrl);

        return $this->_overlayLogo($strSvg, $intSize);
    }

    /**
     * Places the iTOUR logo, on a plain rounded backing, at the exact
     * center of the QR. Sized by config('qr_codes.logo_width_ratio') and
     * hard-capped at MAX_LOGO_AREA_RATIO of the QR's area.
     *
     * @throws RuntimeException when the SVG or logo is not in the expected shape
     */
    private function _overlayLogo(string $strSvg, int $intSize): string
    {
        if (strrpos($strSvg, '</svg>') === false) {
            throw new RuntimeException('Generated QR SVG has no closing </svg> tag.');
        }

        $arrLogo = $this->_getLogo();

        // Summary comment: backing box = logo + padding, centered, capped in area.
        $fltBackingWidth = $intSize * (float) config('qr_codes.logo_width_ratio', 0.34);
        $fltPadding = $fltBackingWidth * 0.08;
        $fltLogoWidth = $fltBackingWidth - (2 * $fltPadding);
        $fltLogoHeight = $fltLogoWidth / $arrLogo['fltAspectRatio'];
        $fltBackingHeight = $fltLogoHeight + (2 * $fltPadding);
        $fltAreaRatio = ($fltBackingWidth * $fltBackingHeight) / ($intSize * $intSize);

        if ($fltAreaRatio > self::MAX_LOGO_AREA_RATIO) {
            throw new RuntimeException("The logo would cover {$fltAreaRatio} of the QR area; the limit is ".self::MAX_LOGO_AREA_RATIO.'.');
        }

        $fltBackingX = ($intSize - $fltBackingWidth) / 2;
        $fltBackingY = ($intSize - $fltBackingHeight) / 2;

        $strLogoMarkup = sprintf(
            '<g class="itour-qr-logo"><rect x="%1$.2f" y="%2$.2f" width="%3$.2f" height="%4$.2f" rx="%5$.2f" fill="%6$s"/>'
            .'<image x="%7$.2f" y="%8$.2f" width="%9$.2f" height="%10$.2f" preserveAspectRatio="xMidYMid meet" href="%11$s" xlink:href="%11$s"/></g>',
            $fltBackingX,
            $fltBackingY,
            $fltBackingWidth,
            $fltBackingHeight,
            $fltPadding,
            e((string) config('qr_codes.logo_backing_color')),
            $fltBackingX + $fltPadding,
            $fltBackingY + $fltPadding,
            $fltLogoWidth,
            $fltLogoHeight,
            $arrLogo['strDataUri']
        );

        // xlink:href keeps older SVG viewers/editors happy; declare its namespace once.
        if (! str_contains($strSvg, 'xmlns:xlink=')) {
            $strSvg = (string) preg_replace('/<svg\b/', '<svg xmlns:xlink="http://www.w3.org/1999/xlink"', $strSvg, 1);
        }

        $intClosingTagPosition = (int) strrpos($strSvg, '</svg>');

        return substr($strSvg, 0, $intClosingTagPosition).$strLogoMarkup.substr($strSvg, $intClosingTagPosition);
    }

    /**
     * The logo as an embedded data URI (so a downloaded SVG is fully
     * self-contained) plus its aspect ratio. Downscaled once per request
     * with GD to keep pages that show many QR codes light.
     *
     * @return array{strDataUri: string, fltAspectRatio: float}
     *
     * @throws RuntimeException when the logo file is missing or unreadable
     */
    private function _getLogo(): array
    {
        $strLogoPath = (string) config('qr_codes.logo_path');

        if (isset(self::$arrLogoCache[$strLogoPath])) {
            return self::$arrLogoCache[$strLogoPath];
        }

        $arrImageInfo = is_file($strLogoPath) ? @getimagesize($strLogoPath) : false;

        if ($arrImageInfo === false || $arrImageInfo[0] < 1 || $arrImageInfo[1] < 1) {
            throw new RuntimeException("QR logo not found or unreadable at {$strLogoPath}.");
        }

        $strResizedBytes = $this->_downscaleLogo($strLogoPath, $arrImageInfo);
        $strImageBytes = $strResizedBytes ?? (string) file_get_contents($strLogoPath);
        $strMimeType = $strResizedBytes !== null ? 'image/jpeg' : $arrImageInfo['mime'];

        self::$arrLogoCache[$strLogoPath] = [
            'strDataUri' => 'data:'.$strMimeType.';base64,'.base64_encode($strImageBytes),
            'fltAspectRatio' => $arrImageInfo[0] / $arrImageInfo[1],
        ];

        return self::$arrLogoCache[$strLogoPath];
    }

    /**
     * JPEG bytes of the logo at most LOGO_MAX_PIXEL_WIDTH wide, or null
     * when GD is unavailable or the image is already small enough (the
     * original file is then embedded as-is).
     *
     * @param  array<int|string, mixed>  $arrImageInfo
     */
    private function _downscaleLogo(string $strLogoPath, array $arrImageInfo): ?string
    {
        $blnCanResize = function_exists('imagecreatefromstring') && $arrImageInfo[0] > self::LOGO_MAX_PIXEL_WIDTH;

        if (! $blnCanResize) {
            return null;
        }

        $objSource = @imagecreatefromstring((string) file_get_contents($strLogoPath));

        if ($objSource === false) {
            return null;
        }

        $intTargetHeight = (int) round(self::LOGO_MAX_PIXEL_WIDTH * $arrImageInfo[1] / $arrImageInfo[0]);
        $objResized = imagescale($objSource, self::LOGO_MAX_PIXEL_WIDTH, $intTargetHeight, IMG_BICUBIC);

        if ($objResized === false) {
            return null;
        }

        ob_start();
        imagejpeg($objResized, null, 90);

        return (string) ob_get_clean();
    }

    /**
     * '#0a3e3b' → [10, 62, 59].
     *
     * @return array{0: int, 1: int, 2: int}
     *
     * @throws InvalidArgumentException for anything that is not #rrggbb
     */
    private function _hexToRgb(string $strHexColor): array
    {
        if (! preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $strHexColor, $arrMatches)) {
            throw new InvalidArgumentException("QR color '{$strHexColor}' must be in #rrggbb form.");
        }

        return [(int) hexdec($arrMatches[1]), (int) hexdec($arrMatches[2]), (int) hexdec($arrMatches[3])];
    }
}
