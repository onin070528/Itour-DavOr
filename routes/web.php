<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Web route definitions for the public site and the PTO, LGU and Establishment portals.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\Establishment\ActivityLogController as EstablishmentActivityLogController;
use App\Http\Controllers\Establishment\ArrivalsController as EstablishmentArrivalsController;
use App\Http\Controllers\Establishment\DashboardController as EstablishmentDashboardController;
use App\Http\Controllers\Establishment\FeedbackController as EstablishmentFeedbackController;
use App\Http\Controllers\Establishment\ImagesController as EstablishmentImagesController;
use App\Http\Controllers\Establishment\ProfileController as EstablishmentProfileController;
use App\Http\Controllers\Establishment\SettingsController as EstablishmentSettingsController;
use App\Http\Controllers\EstablishmentImageFileController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FeedbackController as PublicFeedbackController;
use App\Http\Controllers\HotlinesController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Lgu\AuditLogsController as LguAuditLogsController;
use App\Http\Controllers\Lgu\DashboardController as LguDashboardController;
use App\Http\Controllers\Lgu\DirectoryController as LguDirectoryController;
use App\Http\Controllers\Lgu\FeedbackController as LguFeedbackController;
use App\Http\Controllers\Lgu\ImagesController as LguImagesController;
use App\Http\Controllers\Lgu\MonthlyReportsController as LguMonthlyReportsController;
use App\Http\Controllers\Lgu\SettingsController as LguSettingsController;
use App\Http\Controllers\Lgu\UsersController as LguUsersController;
use App\Http\Controllers\ListingDetailController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\Pto\AnnouncementsController as PtoAnnouncementsController;
use App\Http\Controllers\Pto\AuditLogsController as PtoAuditLogsController;
use App\Http\Controllers\Pto\DashboardController as PtoDashboardController;
use App\Http\Controllers\Pto\DirectoryController as PtoDirectoryController;
use App\Http\Controllers\Pto\FeedbackController as PtoFeedbackController;
use App\Http\Controllers\Pto\HotlinesController as PtoHotlinesController;
use App\Http\Controllers\Pto\ImagesController as PtoImagesController;
use App\Http\Controllers\Pto\MonthlyReportsController as PtoMonthlyReportsController;
use App\Http\Controllers\Pto\MunicipalReportsController as PtoMunicipalReportsController;
use App\Http\Controllers\Pto\SettingsController as PtoSettingsController;
use App\Http\Controllers\Pto\UsersController as PtoUsersController;
use App\Http\Controllers\ReportVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/explore', [ExploreController::class, 'index'])->name('explore');
Route::get('/listings/{listing}', [ListingDetailController::class, 'show'])->name('listings.show');
Route::post('/listings/{listing}/feedback', [PublicFeedbackController::class, 'store'])->middleware('throttle:5,1')->name('listings.feedback.store');
Route::get('/hotlines', [HotlinesController::class, 'index'])->name('hotlines');
Route::get('/verify-report', [ReportVerificationController::class, 'show'])->name('reports.verify');
Route::get('/privacy', [PrivacyController::class, 'show'])->name('privacy');

// The only route that ever serves an establishment image file — public for
// a Published image, authorized-only otherwise. See
// App\Http\Controllers\EstablishmentImageFileController.
Route::get('/establishment-images/{image}/{variant}', [EstablishmentImageFileController::class, 'show'])->name('establishmentImages.file');

// Visitor self-registration form, reached by scanning the QR code posted at
// an establishment — deliberately public (no auth), since guests filling
// it in are never logged in. {establishment} is the listing's id slug, so
// every establishment has its own unique, scannable check-in URL/QR code.
Route::get('/checkin/{establishment}', [CheckinController::class, 'show'])->name('lgu.establishmentQr');
Route::post('/checkin/{establishment}', [CheckinController::class, 'store'])->middleware('throttle:qr-checkin')->name('checkin.store');

// Separate from the check-in QR: each destination/establishment also has its
// own feedback QR, scanned after the visit, opening a formal feedback form.
Route::get('/feedback/{listing}', [PublicFeedbackController::class, 'show'])->name('feedback.form');
Route::post('/feedback/{listing}', [PublicFeedbackController::class, 'submit'])->middleware('throttle:5,1')->name('feedback.submit');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
});

Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/{notificationId}/open', [NotificationsController::class, 'open'])->name('open');
    Route::post('/read-all', [NotificationsController::class, 'readAll'])->name('readAll');
});

Route::post('/logout', [SessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:pto_administrator'])->prefix('pto')->name('pto.')->group(function () {
    Route::get('/', [PtoDashboardController::class, 'index'])->name('dashboard');

    Route::prefix('municipal-reports')->name('municipalReports.')->group(function () {
        Route::get('/', [PtoMunicipalReportsController::class, 'index'])->name('index');
        Route::get('/{municipalReport}', [PtoMunicipalReportsController::class, 'show'])->name('show');
        Route::patch('/{municipalReport}/approve', [PtoMunicipalReportsController::class, 'approve'])->name('approve');
        Route::patch('/{municipalReport}/return', [PtoMunicipalReportsController::class, 'return'])->name('return');
        Route::get('/{municipalReport}/official-report', [PtoMunicipalReportsController::class, 'officialReport'])->middleware('throttle:report-export')->name('officialReport');
        Route::get('/{municipalReport}/official-report.pdf', [PtoMunicipalReportsController::class, 'officialReportPdf'])->middleware('throttle:report-export')->name('officialReport.pdf');
        Route::get('/{municipalReport}/official-report.xlsx', [PtoMunicipalReportsController::class, 'officialReportExcel'])->middleware('throttle:report-export')->name('officialReport.excel');
    });

    Route::prefix('monthly-reports')->name('monthlyReports.')->group(function () {
        Route::get('/', [PtoMonthlyReportsController::class, 'index'])->name('index');
        Route::get('/official-report', [PtoMonthlyReportsController::class, 'officialReport'])->middleware('throttle:report-export')->name('officialReport');
        Route::get('/official-report.pdf', [PtoMonthlyReportsController::class, 'officialReportPdf'])->middleware('throttle:report-export')->name('officialReport.pdf');
        Route::get('/official-report.xlsx', [PtoMonthlyReportsController::class, 'officialReportExcel'])->middleware('throttle:report-export')->name('officialReport.excel');
        Route::get('/{monthlyArrivalReport}', [PtoMonthlyReportsController::class, 'show'])->name('show');
    });

    Route::prefix('directory')->name('directory.')->group(function () {
        Route::get('/', [PtoDirectoryController::class, 'index'])->name('index');
        Route::post('/', [PtoDirectoryController::class, 'store'])->name('store');
        Route::put('/{listing}', [PtoDirectoryController::class, 'update'])->name('update');
        Route::put('/{listing}/status', [PtoDirectoryController::class, 'updateStatus'])->name('updateStatus');
        Route::patch('/{listing}/publish', [PtoDirectoryController::class, 'publish'])->name('publish');
        Route::patch('/{listing}/return', [PtoDirectoryController::class, 'returnToLgu'])->name('returnToLgu');
        Route::patch('/{listing}/unpublish', [PtoDirectoryController::class, 'unpublish'])->name('unpublish');
    });

    Route::prefix('feedback')->name('feedback.')->group(function () {
        Route::get('/', [PtoFeedbackController::class, 'index'])->name('index');
        Route::get('/analytics', [PtoFeedbackController::class, 'analytics'])->name('analytics');
    });

    Route::prefix('hotlines')->name('hotlines.')->group(function () {
        Route::get('/', [PtoHotlinesController::class, 'index'])->name('index');
        Route::post('/', [PtoHotlinesController::class, 'store'])->name('store');
        Route::put('/{hotline}', [PtoHotlinesController::class, 'update'])->name('update');
        Route::put('/{hotline}/deactivate', [PtoHotlinesController::class, 'deactivate'])->name('deactivate');
        Route::put('/{hotline}/reorder', [PtoHotlinesController::class, 'reorder'])->name('reorder');
    });

    Route::prefix('announcements')->name('announcements.')->group(function () {
        Route::get('/', [PtoAnnouncementsController::class, 'index'])->name('index');
        Route::post('/', [PtoAnnouncementsController::class, 'store'])->name('store');
        Route::put('/{announcement}', [PtoAnnouncementsController::class, 'update'])->name('update');
        Route::put('/{announcement}/publish', [PtoAnnouncementsController::class, 'togglePublish'])->name('togglePublish');
    });

    Route::get('/users', [PtoUsersController::class, 'index'])->name('users');
    Route::get('/users/establishments', [PtoUsersController::class, 'availableEstablishments'])->name('users.availableEstablishments');
    Route::post('/users/resend-welcome-email', [PtoUsersController::class, 'resendWelcomeEmail'])->name('users.resendWelcomeEmail');
    Route::post('/users', [PtoUsersController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [PtoUsersController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/toggle-status', [PtoUsersController::class, 'toggleStatus'])->name('users.toggleStatus');

    Route::get('/audit-logs', [PtoAuditLogsController::class, 'index'])->name('auditLogs');
    Route::get('/audit-logs/export', [PtoAuditLogsController::class, 'export'])->name('auditLogs.export');

    Route::get('/settings', [PtoSettingsController::class, 'index'])->name('settings');
    Route::post('/settings/profile', [PtoSettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings/password', [PtoSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/preferences', [PtoSettingsController::class, 'updatePreferences'])->name('settings.preferences');
    Route::put('/settings/categories/{category}/qr', [PtoSettingsController::class, 'toggleCategoryQr'])->name('settings.categories.toggleQr');

    Route::prefix('images')->name('images.')->group(function () {
        Route::get('/', [PtoImagesController::class, 'index'])->name('index');
        Route::post('/', [PtoImagesController::class, 'store'])->middleware('throttle:establishment-image-upload')->name('store');
        Route::get('/queue', [PtoImagesController::class, 'queue'])->name('queue');
        Route::get('/{listing}/manage', [PtoImagesController::class, 'manage'])->name('manage');
        Route::patch('/{image}/approve', [PtoImagesController::class, 'approve'])->name('approve');
        Route::patch('/{image}/return', [PtoImagesController::class, 'return'])->name('return');
        Route::patch('/{listing}/approve-batch', [PtoImagesController::class, 'approveBatch'])->name('approveBatch');
        Route::patch('/{listing}/return-batch', [PtoImagesController::class, 'returnBatch'])->name('returnBatch');
        Route::post('/{image}/replace', [PtoImagesController::class, 'replaceImage'])->middleware('throttle:establishment-image-upload')->name('replace');
        Route::patch('/{image}/remove', [PtoImagesController::class, 'removeImage'])->name('remove');
        Route::patch('/{image}/cover', [PtoImagesController::class, 'setCoverImage'])->name('cover');
        Route::patch('/{image}/credit', [PtoImagesController::class, 'updateImageCredit'])->name('credit');
        Route::put('/{listing}/reorder', [PtoImagesController::class, 'reorderImages'])->name('reorder');
    });
});

Route::middleware(['auth', 'role:lgu', 'lgu.municipality'])->prefix('lgu')->name('lgu.')->group(function () {
    Route::get('/', [LguDashboardController::class, 'index'])->name('dashboard');

    Route::prefix('directory')->name('directory.')->group(function () {
        Route::get('/destinations', [LguDirectoryController::class, 'destinations'])->name('destinations');
        Route::post('/destinations', [LguDirectoryController::class, 'storeDestination'])->name('destinations.store');
        Route::put('/destinations/{listing}', [LguDirectoryController::class, 'updateDestination'])->name('destinations.update');
        Route::patch('/destinations/{listing}/archive', [LguDirectoryController::class, 'archiveDestination'])->name('destinations.archive');
        Route::get('/establishments', [LguDirectoryController::class, 'establishments'])->name('establishments');
        Route::patch('/establishments/{listing}/submit', [LguDirectoryController::class, 'submitToPto'])->name('establishments.submit');
        Route::patch('/establishments/{listing}/return', [LguDirectoryController::class, 'returnToEstablishment'])->name('establishments.return');
    });

    Route::prefix('monthly-reports')->name('monthlyReports.')->group(function () {
        Route::get('/', [LguMonthlyReportsController::class, 'index'])->name('index');
        Route::get('/{listing}/manual-entry', [LguMonthlyReportsController::class, 'showManualEntry'])->name('manualEntry');
        Route::post('/{listing}/manual-entry', [LguMonthlyReportsController::class, 'storeManualEntry'])->name('manualEntry.store');
        Route::post('/consolidate', [LguMonthlyReportsController::class, 'consolidate'])->name('consolidate');
        Route::get('/{monthlyArrivalReport}', [LguMonthlyReportsController::class, 'show'])->name('show');
        Route::patch('/{monthlyArrivalReport}/verify', [LguMonthlyReportsController::class, 'verify'])->name('verify');
        Route::get('/{monthlyArrivalReport}/edit', [LguMonthlyReportsController::class, 'edit'])->name('edit');
        Route::put('/{monthlyArrivalReport}', [LguMonthlyReportsController::class, 'update'])->name('update');
    });

    Route::prefix('feedback')->name('feedback.')->group(function () {
        Route::get('/', [LguFeedbackController::class, 'index'])->name('index');
        Route::get('/analytics', [LguFeedbackController::class, 'analytics'])->name('analytics');
    });

    Route::get('/users', [LguUsersController::class, 'index'])->name('users');
    Route::post('/users/resend-welcome-email', [LguUsersController::class, 'resendWelcomeEmail'])->name('users.resendWelcomeEmail');
    Route::post('/users', [LguUsersController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [LguUsersController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/toggle-status', [LguUsersController::class, 'toggleStatus'])->name('users.toggleStatus');

    Route::get('/audit-logs', [LguAuditLogsController::class, 'index'])->name('auditLogs');
    Route::get('/audit-logs/export', [LguAuditLogsController::class, 'export'])->name('auditLogs.export');

    Route::get('/settings', [LguSettingsController::class, 'index'])->name('settings');
    Route::post('/settings/profile', [LguSettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings/password', [LguSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/preferences', [LguSettingsController::class, 'updatePreferences'])->name('settings.preferences');

    Route::prefix('images')->name('images.')->group(function () {
        Route::get('/', [LguImagesController::class, 'index'])->name('index');
        Route::post('/', [LguImagesController::class, 'store'])->middleware('throttle:establishment-image-upload')->name('store');
        Route::get('/queue', [LguImagesController::class, 'queue'])->name('queue');
        Route::get('/{listing}/manage', [LguImagesController::class, 'manage'])->name('manage');
        Route::patch('/{image}/approve', [LguImagesController::class, 'approve'])->name('approve');
        Route::patch('/{image}/return', [LguImagesController::class, 'return'])->name('return');
        Route::patch('/{listing}/approve-batch', [LguImagesController::class, 'approveBatch'])->name('approveBatch');
        Route::patch('/{listing}/return-batch', [LguImagesController::class, 'returnBatch'])->name('returnBatch');
        Route::post('/{image}/replace', [LguImagesController::class, 'replaceImage'])->middleware('throttle:establishment-image-upload')->name('replace');
        Route::patch('/{image}/remove', [LguImagesController::class, 'removeImage'])->name('remove');
        Route::patch('/{image}/cover', [LguImagesController::class, 'setCoverImage'])->name('cover');
        Route::patch('/{image}/credit', [LguImagesController::class, 'updateImageCredit'])->name('credit');
        Route::put('/{listing}/reorder', [LguImagesController::class, 'reorderImages'])->name('reorder');
    });
});

Route::middleware(['auth', 'role:establishment'])->prefix('establishment')->name('establishment.')->group(function () {
    Route::get('/', [EstablishmentDashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [EstablishmentProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [EstablishmentProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/submit', [EstablishmentProfileController::class, 'submit'])->name('profile.submit');
    Route::get('/qr-code', [EstablishmentProfileController::class, 'qr'])->name('qr');
    Route::get('/photos', [EstablishmentProfileController::class, 'photos'])->name('photos');

    Route::prefix('arrivals')->name('arrivals.')->group(function () {
        Route::get('/record', [EstablishmentArrivalsController::class, 'record'])->name('record');
        Route::post('/', [EstablishmentArrivalsController::class, 'store'])->name('store');
        Route::get('/', [EstablishmentArrivalsController::class, 'index'])->name('index');
        Route::get('/monthly', [EstablishmentArrivalsController::class, 'monthlyReports'])->name('monthly');
        Route::post('/monthly/submit', [EstablishmentArrivalsController::class, 'submitMonthlyReport'])->name('monthly.submit');
    });

    Route::prefix('feedback')->name('feedback.')->group(function () {
        Route::get('/', [EstablishmentFeedbackController::class, 'index'])->name('index');
        Route::get('/analytics', [EstablishmentFeedbackController::class, 'analytics'])->name('analytics');
    });

    Route::get('/activity-log', [EstablishmentActivityLogController::class, 'index'])->name('activityLog');

    Route::get('/settings', [EstablishmentSettingsController::class, 'index'])->name('settings');
    Route::post('/settings/profile', [EstablishmentSettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings/password', [EstablishmentSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/preferences', [EstablishmentSettingsController::class, 'updatePreferences'])->name('settings.preferences');

    Route::prefix('images')->name('images.')->group(function () {
        // Photos merged into the Establishment Profile page — any direct
        // link to the old page permanently redirects there, scrolled to
        // the photos section. GET-only: Route::redirect() answers every
        // HTTP method and would swallow the upload POST to the same URL.
        Route::get('/', fn () => redirect('/establishment/profile#photos', 301))->name('index');
        Route::post('/', [EstablishmentImagesController::class, 'store'])->middleware('throttle:establishment-image-upload')->name('store');
        Route::post('/{image}/replace', [EstablishmentImagesController::class, 'replaceImage'])->middleware('throttle:establishment-image-upload')->name('replace');
        Route::patch('/{image}/remove', [EstablishmentImagesController::class, 'removeImage'])->name('remove');
        Route::patch('/{image}/cover', [EstablishmentImagesController::class, 'setCoverImage'])->name('cover');
        Route::patch('/{image}/credit', [EstablishmentImagesController::class, 'updateImageCredit'])->name('credit');
        Route::put('/{listing}/reorder', [EstablishmentImagesController::class, 'reorderImages'])->name('reorder');
    });
});
