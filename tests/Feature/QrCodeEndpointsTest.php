<?php

use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\OperationLog;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

function qrEndpointMunicipality(string $code, string $name): Municipality
{
    return Municipality::query()->firstOrCreate(['code' => $code], ['name' => $name]);
}

/**
 * A published, QR-enabled establishment in $municipality — adopted (Online
 * iTOUR with a linked, active account) unless $blnWithAccount is false, in
 * which case it stays on the Manual/Paper default.
 */
function qrEndpointListing(Municipality $municipality, array $overrides = [], bool $blnWithAccount = true): Listing
{
    $category = Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );

    $listing = Listing::query()->create(array_merge([
        'slug' => Str::slug('qr-endpoint-'.Str::random(6)),
        'name' => 'QR Endpoint Resort',
        'category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'municipality' => $municipality->name,
        'municipality_id' => $municipality->id,
        'barangay' => 'Dahican',
        'status' => 'PUBLISHED',
    ], $overrides));

    if ($blnWithAccount) {
        $listing->forceFill(['reporting_mode' => ReportingMethod::OnlineItour])->save();
        qrEndpointUser(UserRole::Establishment, $municipality, $listing);
    }

    return $listing;
}

function qrEndpointUser(UserRole $role, ?Municipality $municipality = null, ?Listing $listing = null): User
{
    return User::factory()->create([
        'role' => $role,
        'organization_name' => $listing?->name ?? 'QR Endpoint Office',
        'organization_subtitle' => 'Davao Oriental',
        'municipality_id' => $municipality?->id,
        'establishment_id' => $listing?->id,
    ]);
}

// --- QrCodeService ---

test('the check-in URL is built from the listing uuid on the APP_URL host, never the request host', function () {
    config(['app.url' => 'https://itour.example.gov.ph']);
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'));

    $strUrl = app(QrCodeService::class)->buildCheckinUrl($listing);

    expect($strUrl)->toBe('https://itour.example.gov.ph/checkin/'.$listing->uuid);
    expect($strUrl)->toBe('https://itour.example.gov.ph'.route('lgu.establishmentQr', ['establishment' => $listing->uuid], false));
});

test('the service generates SVG markup for a listing', function () {
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'));

    $strSvg = app(QrCodeService::class)->generateSvg($listing);

    expect($strSvg)->toContain('<svg');
});

test('the service returns null and logs instead of throwing when a listing has no uuid', function () {
    Log::spy();
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'));
    DB::table('listings')->where('id', $listing->id)->update(['uuid' => null]);

    expect(app(QrCodeService::class)->generateSvg($listing->fresh()))->toBeNull();
    Log::shouldHaveReceived('error')->once();
});

// --- Authorization: establishment own only, LGU own municipality, PTO all ---

test('an establishment can view and download its own QR code', function () {
    $mati = qrEndpointMunicipality('MATI', 'City of Mati');
    $listing = qrEndpointListing($mati);
    $owner = $listing->establishmentUser;

    test()->actingAs($owner)->get(route('qrCodes.show', $listing))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');

    test()->actingAs($owner)->get(route('qrCodes.download', $listing))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="qr-endpoint-resort-qr-code.svg"');
});

test('an establishment cannot view or download another establishment\'s QR code', function () {
    $mati = qrEndpointMunicipality('MATI', 'City of Mati');
    $ownListing = qrEndpointListing($mati, ['name' => 'Own Resort']);
    $otherListing = qrEndpointListing($mati, ['name' => 'Other Resort']);

    test()->actingAs($ownListing->establishmentUser)->get(route('qrCodes.show', $otherListing))->assertForbidden();
    test()->actingAs($ownListing->establishmentUser)->get(route('qrCodes.download', $otherListing))->assertForbidden();
});

test('an LGU can view QR codes in its own municipality only', function () {
    $mati = qrEndpointMunicipality('MATI', 'City of Mati');
    $baganga = qrEndpointMunicipality('BAGANGA', 'Baganga');
    $matiListing = qrEndpointListing($mati);
    $bagangaListing = qrEndpointListing($baganga, ['name' => 'Baganga Inn']);
    $lgu = qrEndpointUser(UserRole::Lgu, $mati);

    test()->actingAs($lgu)->get(route('qrCodes.download', $matiListing))->assertOk();
    test()->actingAs($lgu)->get(route('qrCodes.download', $bagangaListing))->assertForbidden();
});

test('the PTO can view any establishment\'s QR code', function () {
    $listing = qrEndpointListing(qrEndpointMunicipality('BAGANGA', 'Baganga'));
    $pto = qrEndpointUser(UserRole::PtoAdministrator);

    test()->actingAs($pto)->get(route('qrCodes.show', $listing))->assertOk();
});

test('an establishment with no linked account has no QR code (404), even for the PTO', function () {
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'), [], false);
    $pto = qrEndpointUser(UserRole::PtoAdministrator);

    test()->actingAs($pto)->get(route('qrCodes.show', $listing))->assertNotFound();
});

test('a destination never has a QR code', function () {
    $category = Category::query()->firstOrCreate(
        ['cat_name' => 'Tourist Destinations'],
        ['cat_sort_order' => 0, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );
    $destination = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'), [
        'name' => 'Dahican Beach', 'category' => 'destinations', 'cat_id' => $category->cat_id, 'status' => 'Active',
    ], false);
    $pto = qrEndpointUser(UserRole::PtoAdministrator);

    expect($destination->isAcceptingRegistrations())->toBeFalse();
    test()->actingAs($pto)->get(route('qrCodes.show', $destination))->assertForbidden();
});

test('guests cannot reach the QR code endpoints', function () {
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'));

    test()->get(route('qrCodes.download', $listing))->assertRedirect(route('login'));
});

// --- Screens render the QR through the service ---

test('the PTO directory renders the QR code for an establishment accepting registrations', function () {
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'));
    $pto = qrEndpointUser(UserRole::PtoAdministrator);

    test()->actingAs($pto)->get(route('pto.directory.index'))
        ->assertOk()
        ->assertSee('qr-view-'.$listing->id, false)
        ->assertSee(route('qrCodes.show', $listing), false)
        ->assertSee(route('qrCodes.poster', $listing), false)
        ->assertSee(route('qrCodes.download', $listing), false)
        // PTO is read-only: no on/off switch in its modal.
        ->assertDontSee(route('qrCodes.updateStatus', $listing), false);
});

test('the establishment QR page explains why there is no QR while the establishment is suspended', function () {
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'), ['status' => 'Suspended']);

    test()->actingAs($listing->establishmentUser)->get(route('establishment.qr'))
        ->assertOk()
        ->assertSee("QR check-in isn't available yet")
        ->assertDontSee('establishment-qr-svg', false);
});

test('the establishment QR page shows the QR for an Online establishment whose listing is not published yet', function () {
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'), ['status' => 'DRAFT']);

    test()->actingAs($listing->establishmentUser)->get(route('establishment.qr'))
        ->assertOk()
        ->assertDontSee("QR check-in isn't available yet")
        ->assertSee(route('qrCodes.download', $listing), false);
});

// --- Branded QR (Phase 2b) ---

test('the branded QR is valid SVG with the brand module color and the centered logo', function () {
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'));

    $strSvg = app(QrCodeService::class)->generateSvg($listing, 256, true);

    expect(simplexml_load_string($strSvg))->not->toBeFalse();
    expect($strSvg)->toContain('fill="'.config('qr_codes.module_color').'"');
    expect($strSvg)->toContain('class="itour-qr-logo"');
    expect($strSvg)->toContain('data:image/jpeg;base64,');
});

test('the plain QR is used when branding is switched off in config', function () {
    config(['qr_codes.is_branded' => false]);
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'));

    $strSvg = app(QrCodeService::class)->generateSvg($listing);

    expect($strSvg)->toContain('<svg');
    expect($strSvg)->not->toContain('itour-qr-logo');
});

test('a branding failure falls back to the plain QR instead of failing', function () {
    Log::spy();
    config(['qr_codes.logo_path' => storage_path('app/does-not-exist/missing-logo.jpg')]);
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'));

    $strSvg = app(QrCodeService::class)->generateSvg($listing, 256, true);

    expect($strSvg)->toContain('<svg');
    expect($strSvg)->not->toContain('itour-qr-logo');
    Log::shouldHaveReceived('warning')->once();
});

test('an invalid brand color also falls back to the plain QR', function () {
    config(['qr_codes.module_color' => 'teal']);
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'));

    $strSvg = app(QrCodeService::class)->generateSvg($listing, 256, true);

    expect($strSvg)->toContain('<svg');
    expect($strSvg)->not->toContain('itour-qr-logo');
});

// --- Printable poster ---

test('the establishment can open its own A4 poster with the current name, municipality, and uuid URL', function () {
    config(['app.url' => 'https://itour.example.gov.ph']);
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'), ['name' => 'Seaside Haven']);

    test()->actingAs($listing->establishmentUser)->get(route('qrCodes.poster', $listing))
        ->assertOk()
        ->assertSee('Scan to register your visit')
        ->assertSee('Seaside Haven')
        ->assertSee('City of Mati, Davao Oriental')
        ->assertSee('itour.example.gov.ph/checkin/'.$listing->uuid)
        ->assertSee('size: A4', false)
        ->assertSee('poster-qr', false);
});

test('the table card layout is served with ?layout=card', function () {
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'));

    test()->actingAs($listing->establishmentUser)->get(route('qrCodes.poster', ['listing' => $listing, 'layout' => 'card']))
        ->assertOk()
        ->assertSee('Cut along the dashed line')
        ->assertSee('card-qr', false);
});

test('the poster follows the same authorization as the QR download', function () {
    $mati = qrEndpointMunicipality('MATI', 'City of Mati');
    $baganga = qrEndpointMunicipality('BAGANGA', 'Baganga');
    $matiListing = qrEndpointListing($mati, ['name' => 'Mati Resort']);
    $otherMatiListing = qrEndpointListing($mati, ['name' => 'Other Mati Resort']);
    $bagangaListing = qrEndpointListing($baganga, ['name' => 'Baganga Resort']);
    $noAccountListing = qrEndpointListing($mati, ['name' => 'No Account Resort'], false);

    $matiLgu = qrEndpointUser(UserRole::Lgu, $mati);

    test()->actingAs($matiListing->establishmentUser)->get(route('qrCodes.poster', $otherMatiListing))->assertForbidden();
    test()->actingAs($matiLgu)->get(route('qrCodes.poster', $matiListing))->assertOk();
    test()->actingAs($matiLgu)->get(route('qrCodes.poster', $bagangaListing))->assertForbidden();
    test()->actingAs(qrEndpointUser(UserRole::PtoAdministrator))->get(route('qrCodes.poster', $bagangaListing))->assertOk();
    test()->actingAs(qrEndpointUser(UserRole::PtoAdministrator))->get(route('qrCodes.poster', $noAccountListing))->assertNotFound();
});

// --- QR on/off switch (Phase 4) ---

test('an establishment can turn its own QR check-in off and on, each change audit-logged with old and new values', function () {
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'));
    $owner = $listing->establishmentUser;
    $strUuid = $listing->uuid;

    test()->actingAs($owner)->patch(route('qrCodes.updateStatus', $listing), ['is_enabled' => '0'])->assertRedirect();

    expect($listing->fresh()->lst_is_qr_enabled)->toBeFalse();
    $offLog = OperationLog::query()->where('entity_type', 'establishment')->where('entity_id', $listing->id)->latest('id')->first();
    expect($offLog->action)->toBe('update');
    expect($offLog->user_id)->toBe($owner->id);
    expect($offLog->old_values)->toBe(['lst_is_qr_enabled' => true]);
    expect($offLog->new_values)->toBe(['lst_is_qr_enabled' => false]);
    expect($offLog->reason)->toBe('QR check-in switched off');

    // Public page refuses; the establishment's QR page offers to turn it back on.
    test()->get(route('lgu.establishmentQr', ['establishment' => $strUuid]))->assertSee('This establishment is not accepting registrations');
    test()->actingAs($owner)->get(route('establishment.qr'))->assertSee('QR check-in is turned off')->assertSee('Turn QR check-in back on');

    test()->actingAs($owner)->patch(route('qrCodes.updateStatus', $listing), ['is_enabled' => '1'])->assertRedirect();

    expect($listing->fresh()->lst_is_qr_enabled)->toBeTrue();
    expect($listing->fresh()->uuid)->toBe($strUuid);
    $onLog = OperationLog::query()->where('entity_type', 'establishment')->where('entity_id', $listing->id)->latest('id')->first();
    expect($onLog->new_values)->toBe(['lst_is_qr_enabled' => true]);
    test()->get(route('lgu.establishmentQr', ['establishment' => $strUuid]))->assertSee('establishment-qr-form', false);
});

test('repeating the current QR state changes nothing and writes no audit log', function () {
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'));

    test()->actingAs($listing->establishmentUser)->patch(route('qrCodes.updateStatus', $listing), ['is_enabled' => '1'])->assertRedirect();

    expect(OperationLog::query()->where('entity_type', 'establishment')->where('entity_id', $listing->id)->count())->toBe(0);
});

test('the LGU can switch QR check-in for its own municipality only; the PTO and other establishments cannot', function () {
    $mati = qrEndpointMunicipality('MATI', 'City of Mati');
    $baganga = qrEndpointMunicipality('BAGANGA', 'Baganga');
    $matiListing = qrEndpointListing($mati, ['name' => 'Mati Resort']);
    $otherMatiListing = qrEndpointListing($mati, ['name' => 'Other Mati Resort']);
    $bagangaListing = qrEndpointListing($baganga, ['name' => 'Baganga Resort']);

    $matiLgu = qrEndpointUser(UserRole::Lgu, $mati);

    test()->actingAs($matiLgu)->patch(route('qrCodes.updateStatus', $matiListing), ['is_enabled' => '0'])->assertRedirect();
    expect($matiListing->fresh()->lst_is_qr_enabled)->toBeFalse();

    test()->actingAs($matiLgu)->patch(route('qrCodes.updateStatus', $bagangaListing), ['is_enabled' => '0'])->assertForbidden();
    test()->actingAs(qrEndpointUser(UserRole::PtoAdministrator))->patch(route('qrCodes.updateStatus', $bagangaListing), ['is_enabled' => '0'])->assertForbidden();
    test()->actingAs($matiListing->establishmentUser)->patch(route('qrCodes.updateStatus', $otherMatiListing), ['is_enabled' => '0'])->assertForbidden();

    expect($bagangaListing->fresh()->lst_is_qr_enabled)->toBeTrue();
    expect($otherMatiListing->fresh()->lst_is_qr_enabled)->toBeTrue();
});

test('the QR switch rejects a missing or non-boolean value and never applies to destinations', function () {
    $mati = qrEndpointMunicipality('MATI', 'City of Mati');
    $listing = qrEndpointListing($mati);

    test()->actingAs($listing->establishmentUser)->patch(route('qrCodes.updateStatus', $listing), ['is_enabled' => 'maybe'])
        ->assertSessionHasErrors('is_enabled');
    expect($listing->fresh()->lst_is_qr_enabled)->toBeTrue();

    $destination = qrEndpointListing($mati, ['name' => 'Dahican Beach', 'category' => 'destinations', 'status' => 'Active'], false);
    test()->actingAs(qrEndpointUser(UserRole::Lgu, $mati))->patch(route('qrCodes.updateStatus', $destination), ['is_enabled' => '0'])->assertForbidden();
});

// --- Screens wired to the QR endpoints (Phase 5) ---

test('getQrStatus explains why a QR is or is not usable', function () {
    $mati = qrEndpointMunicipality('MATI', 'City of Mati');
    $active = qrEndpointListing($mati, ['name' => 'Active Inn']);
    $switchedOff = qrEndpointListing($mati, ['name' => 'Paused Inn']);
    $switchedOff->forceFill(['lst_is_qr_enabled' => false])->save();
    $paper = qrEndpointListing($mati, ['name' => 'Paper Inn'], false);
    $onlineWithoutAccount = qrEndpointListing($mati, ['name' => 'Online Inn'], false);
    $onlineWithoutAccount->forceFill(['reporting_mode' => ReportingMethod::OnlineItour])->save();
    $suspendedAccount = qrEndpointListing($mati, ['name' => 'Suspended Account Inn']);
    $suspendedAccount->establishmentUser->update(['status' => 'Inactive']);
    // QR does not depend on the destination listing being Published.
    $draft = qrEndpointListing($mati, ['name' => 'Draft Inn', 'status' => 'DRAFT']);
    $suspended = qrEndpointListing($mati, ['name' => 'Suspended Inn', 'status' => 'Suspended']);

    expect($active->fresh()->getQrStatus())->toBe(Listing::QR_STATUS_ACTIVE);
    expect($switchedOff->fresh()->getQrStatus())->toBe(Listing::QR_STATUS_SWITCHED_OFF);
    expect($paper->fresh()->getQrStatus())->toBe(Listing::QR_STATUS_MANUAL_REPORTING);
    expect($onlineWithoutAccount->fresh()->getQrStatus())->toBe(Listing::QR_STATUS_NO_ACCOUNT);
    expect($suspendedAccount->fresh()->getQrStatus())->toBe(Listing::QR_STATUS_NO_ACCOUNT);
    expect($draft->fresh()->getQrStatus())->toBe(Listing::QR_STATUS_ACTIVE);
    expect($suspended->fresh()->getQrStatus())->toBe(Listing::QR_STATUS_NOT_ELIGIBLE);
});

test('the LGU Establishments page shows each own establishment\'s QR status, with view, print, download, and the on/off switch', function () {
    $mati = qrEndpointMunicipality('MATI', 'City of Mati');
    $active = qrEndpointListing($mati, ['name' => 'Active Inn']);
    $switchedOff = qrEndpointListing($mati, ['name' => 'Paused Inn']);
    $switchedOff->forceFill(['lst_is_qr_enabled' => false])->save();
    qrEndpointListing($mati, ['name' => 'Paper Inn'], false);
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_name' => 'City of Mati LGU',
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);

    test()->actingAs($lgu)->get(route('lgu.directory.establishments'))
        ->assertOk()
        ->assertSee('qr-view-'.$active->id, false)
        ->assertSee(route('qrCodes.show', $active), false)
        ->assertSee(route('qrCodes.poster', $active), false)
        ->assertSee(route('qrCodes.download', $active), false)
        ->assertSee(route('qrCodes.updateStatus', $active), false)
        ->assertSee('QR off')
        ->assertSee('Turn back on')
        ->assertSee('Manual/Paper');
});

test('the establishment QR page downloads and prints through the authorized endpoints', function () {
    $listing = qrEndpointListing(qrEndpointMunicipality('MATI', 'City of Mati'));

    test()->actingAs($listing->establishmentUser)->get(route('establishment.qr'))
        ->assertOk()
        ->assertSee(route('qrCodes.download', $listing), false)
        ->assertSee(route('qrCodes.poster', $listing), false)
        ->assertSee('Turn off QR check-in');
});
