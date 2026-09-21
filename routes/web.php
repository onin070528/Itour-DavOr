<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\Establishment\ArrivalsController as EstablishmentArrivalsController;
use App\Http\Controllers\Establishment\DashboardController as EstablishmentDashboardController;
use App\Http\Controllers\Establishment\FeedbackController as EstablishmentFeedbackController;
use App\Http\Controllers\Establishment\ProfileController as EstablishmentProfileController;
use App\Http\Controllers\Establishment\ReportsController as EstablishmentReportsController;
use App\Http\Controllers\Establishment\SettingsController as EstablishmentSettingsController;
use App\Http\Controllers\Establishment\StatisticsController as EstablishmentStatisticsController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Lgu\DashboardController as LguDashboardController;
use App\Http\Controllers\Lgu\DirectoryController as LguDirectoryController;
use App\Http\Controllers\Lgu\FeedbackController as LguFeedbackController;
use App\Http\Controllers\Lgu\MonitoringController as LguMonitoringController;
use App\Http\Controllers\Lgu\ReportsController as LguReportsController;
use App\Http\Controllers\Lgu\SettingsController as LguSettingsController;
use App\Http\Controllers\Lgu\UsersController as LguUsersController;
use App\Http\Controllers\Pto\DashboardController as PtoDashboardController;
use App\Http\Controllers\Pto\DirectoryController as PtoDirectoryController;
use App\Http\Controllers\Pto\FeedbackController as PtoFeedbackController;
use App\Http\Controllers\Pto\MonitoringController as PtoMonitoringController;
use App\Http\Controllers\Pto\MunicipalReportsController as PtoMunicipalReportsController;
use App\Http\Controllers\Pto\ReportsController as PtoReportsController;
use App\Http\Controllers\Pto\SettingsController as PtoSettingsController;
use App\Http\Controllers\Pto\UsersController as PtoUsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/explore', [ExploreController::class, 'index'])->name('explore');

// Visitor self-registration form, reached by scanning the QR code posted at
// an establishment — deliberately public (no auth), since guests filling
// it in are never logged in. {establishment} is the listing's id slug, so
// every establishment has its own unique, scannable check-in URL/QR code.
Route::get('/checkin/{establishment}', [CheckinController::class, 'show'])->name('lgu.establishmentQr');
Route::post('/checkin/{establishment}', [CheckinController::class, 'store'])->name('checkin.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::post('/logout', [SessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:pto_administrator'])->prefix('pto')->name('pto.')->group(function () {
    Route::get('/', [PtoDashboardController::class, 'index'])->name('dashboard');

    Route::prefix('monitoring')->name('monitoring.')->group(function () {
        Route::get('/arrivals', [PtoMonitoringController::class, 'arrivals'])->name('arrivals');
        Route::get('/statistics', [PtoMonitoringController::class, 'statistics'])->name('statistics');
        Route::get('/destination-performance', [PtoMonitoringController::class, 'destinations'])->name('destinations');
    });

    Route::prefix('municipal-reports')->name('municipalReports.')->group(function () {
        Route::get('/', [PtoMunicipalReportsController::class, 'index'])->name('index');
        Route::get('/{municipalReport}', [PtoMunicipalReportsController::class, 'show'])->name('show');
        Route::patch('/{municipalReport}/approve', [PtoMunicipalReportsController::class, 'approve'])->name('approve');
        Route::patch('/{municipalReport}/return', [PtoMunicipalReportsController::class, 'return'])->name('return');
    });

    Route::prefix('directory')->name('directory.')->group(function () {
        Route::get('/destinations', [PtoDirectoryController::class, 'destinations'])->name('destinations');
        Route::post('/destinations', [PtoDirectoryController::class, 'storeDestination'])->name('destinations.store');
        Route::put('/destinations/{listing}', [PtoDirectoryController::class, 'updateDestination'])->name('destinations.update');
        Route::patch('/destinations/{listing}/archive', [PtoDirectoryController::class, 'archiveDestination'])->name('destinations.archive');
        Route::get('/establishments', [PtoDirectoryController::class, 'establishments'])->name('establishments');
        Route::get('/map', [PtoDirectoryController::class, 'map'])->name('map');
    });

    Route::prefix('feedback')->name('feedback.')->group(function () {
        Route::get('/', [PtoFeedbackController::class, 'index'])->name('index');
        Route::get('/analytics', [PtoFeedbackController::class, 'analytics'])->name('analytics');
    });

    Route::get('/reports', [PtoReportsController::class, 'index'])->name('reports');

    Route::get('/users', [PtoUsersController::class, 'index'])->name('users');
    Route::post('/users', [PtoUsersController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [PtoUsersController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/toggle-status', [PtoUsersController::class, 'toggleStatus'])->name('users.toggleStatus');

    Route::get('/settings', [PtoSettingsController::class, 'index'])->name('settings');
    Route::post('/settings/profile', [PtoSettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings/password', [PtoSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/preferences', [PtoSettingsController::class, 'updatePreferences'])->name('settings.preferences');
});

Route::middleware(['auth', 'role:lgu', 'lgu.municipality'])->prefix('lgu')->name('lgu.')->group(function () {
    Route::get('/', [LguDashboardController::class, 'index'])->name('dashboard');

    Route::prefix('monitoring')->name('monitoring.')->group(function () {
        Route::get('/arrivals', [LguMonitoringController::class, 'arrivals'])->name('arrivals');
        Route::get('/statistics', [LguMonitoringController::class, 'statistics'])->name('statistics');
        Route::get('/destination-performance', [LguMonitoringController::class, 'destinations'])->name('destinations');
    });

    Route::prefix('directory')->name('directory.')->group(function () {
        Route::get('/destinations', [LguDirectoryController::class, 'destinations'])->name('destinations');
        Route::post('/destinations', [LguDirectoryController::class, 'storeDestination'])->name('destinations.store');
        Route::put('/destinations/{listing}', [LguDirectoryController::class, 'updateDestination'])->name('destinations.update');
        Route::patch('/destinations/{listing}/archive', [LguDirectoryController::class, 'archiveDestination'])->name('destinations.archive');
        Route::get('/establishments', [LguDirectoryController::class, 'establishments'])->name('establishments');
        Route::patch('/establishments/{listing}/verify', [LguDirectoryController::class, 'verifyEstablishment'])->name('establishments.verify');
    });

    Route::prefix('feedback')->name('feedback.')->group(function () {
        Route::get('/', [LguFeedbackController::class, 'index'])->name('index');
        Route::get('/analytics', [LguFeedbackController::class, 'analytics'])->name('analytics');
    });

    Route::get('/reports', [LguReportsController::class, 'index'])->name('reports');

    Route::get('/users', [LguUsersController::class, 'index'])->name('users');
    Route::post('/users', [LguUsersController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [LguUsersController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/toggle-status', [LguUsersController::class, 'toggleStatus'])->name('users.toggleStatus');

    Route::get('/settings', [LguSettingsController::class, 'index'])->name('settings');
    Route::post('/settings/profile', [LguSettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings/password', [LguSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/preferences', [LguSettingsController::class, 'updatePreferences'])->name('settings.preferences');
});

Route::middleware(['auth', 'role:establishment'])->prefix('establishment')->name('establishment.')->group(function () {
    Route::get('/', [EstablishmentDashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [EstablishmentProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [EstablishmentProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/images', [EstablishmentProfileController::class, 'storeImage'])->name('profile.images.store');
    Route::patch('/profile/images/{image}/primary', [EstablishmentProfileController::class, 'setPrimaryImage'])->name('profile.images.primary');
    Route::delete('/profile/images/{image}', [EstablishmentProfileController::class, 'destroyImage'])->name('profile.images.destroy');
    Route::get('/qr-code', [EstablishmentProfileController::class, 'qr'])->name('qr');

    Route::prefix('arrivals')->name('arrivals.')->group(function () {
        Route::get('/record', [EstablishmentArrivalsController::class, 'record'])->name('record');
        Route::post('/', [EstablishmentArrivalsController::class, 'store'])->name('store');
        Route::get('/', [EstablishmentArrivalsController::class, 'index'])->name('index');
    });

    Route::get('/statistics', [EstablishmentStatisticsController::class, 'index'])->name('statistics');

    Route::prefix('feedback')->name('feedback.')->group(function () {
        Route::get('/', [EstablishmentFeedbackController::class, 'index'])->name('index');
        Route::get('/analytics', [EstablishmentFeedbackController::class, 'analytics'])->name('analytics');
    });

    Route::get('/reports', [EstablishmentReportsController::class, 'index'])->name('reports');

    Route::get('/settings', [EstablishmentSettingsController::class, 'index'])->name('settings');
    Route::post('/settings/profile', [EstablishmentSettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings/password', [EstablishmentSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/preferences', [EstablishmentSettingsController::class, 'updatePreferences'])->name('settings.preferences');
});
