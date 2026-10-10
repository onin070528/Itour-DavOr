<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Phase 3 — reporting-method adoption: Manual/Paper <-> Online iTOUR, the establishment's one
 *              account (activate / suspend / reactivate, never delete), and QR eligibility.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Mail\WelcomeAccountCreated;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\OperationLog;
use App\Models\SecurityLog;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function adoptionMunicipality(string $strName, string $strCode): Municipality
{
    return Municipality::query()->firstOrCreate(['mun_code' => $strCode], ['mun_name' => $strName]);
}

function adoptionLgu(Municipality $objMunicipality): User
{
    return User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_name' => "{$objMunicipality->mun_name} LGU",
        'usr_organization_subtitle' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
    ]);
}

function adoptionCategory(string $strCategoryName = 'Accommodation'): Category
{
    test()->seed(CategorySeeder::class);

    return Category::query()->where('cat_name', $strCategoryName)->firstOrFail();
}

/**
 * A Manual/Paper establishment with no account — exactly what "Add
 * Establishment" creates. DRAFT by default, to prove QR never waits for
 * the destination listing to be Published.
 *
 * @param  array<string, mixed>  $arrOverrides
 */
function adoptionEstablishment(Municipality $objMunicipality, array $arrOverrides = []): Listing
{
    $objCategory = adoptionCategory();

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug("{$objMunicipality->mun_name}-adoption-inn-".Str::random(6)),
        'lst_name' => "{$objMunicipality->mun_name} Adoption Inn",
        'lst_category' => $objCategory->legacySlug(),
        'cat_id' => $objCategory->cat_id,
        'lst_type' => 'Hotel',
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_owner_name' => 'Juan Dela Cruz',
        'lst_email' => 'owner@adoption-inn.test',
        'lst_status' => 'DRAFT',
    ], $arrOverrides));
}

/**
 * An Online iTOUR establishment with an active account, as an earlier
 * activation leaves it.
 *
 * @return array{0: Listing, 1: User}
 */
function adoptionOnlineEstablishment(Municipality $objMunicipality, array $arrOverrides = []): array
{
    $objListing = adoptionEstablishment($objMunicipality, $arrOverrides);
    $objListing->forceFill(['lst_reporting_mode' => ReportingMethod::OnlineItour])->save();
    $objAccount = User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_name' => $objListing->lst_name,
        'usr_organization_subtitle' => "{$objListing->lst_barangay}, {$objListing->lst_municipality}",
        'mun_id' => $objMunicipality->mun_id,
        'lst_id' => $objListing->lst_id,
    ]);

    return [$objListing->fresh(), $objAccount];
}

/**
 * @return array<string, string>
 */
function adoptionAccountPayload(array $arrOverrides = []): array
{
    return array_merge([
        'account_name' => 'Juan Dela Cruz',
        'account_email' => 'frontdesk@adoption-inn.test',
    ], $arrOverrides);
}

// --- Creating an establishment ---

test('registering an establishment creates no account and no QR — it starts on Manual/Paper', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objCategory = adoptionCategory();

    test()->actingAs(adoptionLgu($objMati))->post(route('lgu.directory.establishments.store'), [
        'name' => 'Brand New Inn',
        'cat_id' => $objCategory->cat_id,
        'type' => 'Hotel',
        'barangay' => 'Dahican',
        'email' => 'brand-new-inn@example.test',
    ])->assertSessionHasNoErrors();

    $objListing = Listing::query()->where('lst_name', 'Brand New Inn')->sole();

    expect($objListing->lst_reporting_mode)->toBe(ReportingMethod::ManualPaper);
    expect($objListing->establishmentUser)->toBeNull();
    expect(User::query()->where('usr_email', 'brand-new-inn@example.test')->exists())->toBeFalse();
    expect($objListing->isAcceptingRegistrations())->toBeFalse();
    expect($objListing->getQrStatus())->toBe(Listing::QR_STATUS_MANUAL_REPORTING);
});

test('the details page offers "Activate Online iTOUR account" for a paper establishment with no account', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objListing = adoptionEstablishment($objMati);

    test()->actingAs(adoptionLgu($objMati))->get(route('lgu.directory.establishments.show', $objListing))
        ->assertOk()
        ->assertSee('Manual/Paper')
        ->assertSee('Activate Online iTOUR account')
        ->assertSee(route('lgu.directory.establishments.switchToOnline', $objListing), false)
        ->assertDontSee('Switch to Manual/Paper');
});

// --- Manual/Paper -> Online iTOUR: activation ---

test('activating creates exactly one linked account with a one-time temporary password and the existing welcome email', function () {
    Mail::fake();
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objLgu = adoptionLgu($objMati);
    $objListing = adoptionEstablishment($objMati);

    $objResponse = test()->actingAs($objLgu)->post(route('lgu.directory.establishments.switchToOnline', $objListing), adoptionAccountPayload());

    $objResponse->assertRedirect(route('lgu.directory.establishments.show', $objListing))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('accountCreated');
    $strPassphrase = session('accountCreated')['passphrase'];

    $objAccount = User::query()->where('lst_id', $objListing->lst_id)->sole();
    expect($objAccount->usr_email)->toBe('frontdesk@adoption-inn.test');
    expect($objAccount->usr_role)->toBe(UserRole::Establishment);
    expect($objAccount->mun_id)->toBe($objMati->mun_id);
    expect($objAccount->usr_status)->toBe('Active');
    expect($objAccount->usr_must_change_password)->toBeTrue();
    expect($objAccount->usr_created_by)->toBe($objLgu->usr_id);
    expect(session('accountCreated')['userId'])->toBe($objAccount->usr_id);

    // Never stored or logged in plaintext.
    expect($strPassphrase)->not->toBeEmpty();
    expect(Hash::check($strPassphrase, $objAccount->usr_password))->toBeTrue();
    expect(DB::table('tbl_users')->where('usr_password', $strPassphrase)->exists())->toBeFalse();
    expect(json_encode(OperationLog::query()->get()->toArray()))->not->toContain($strPassphrase);
    expect(json_encode(SecurityLog::query()->get()->toArray()))->not->toContain($strPassphrase);

    Mail::assertSent(WelcomeAccountCreated::class, fn (WelcomeAccountCreated $objMail) => $objMail->hasTo('frontdesk@adoption-inn.test') && $objMail->strPassphrase === $strPassphrase);

    expect($objListing->fresh()->lst_reporting_mode)->toBe(ReportingMethod::OnlineItour);
    $objLog = OperationLog::query()->where('opl_entity_type', 'establishment')->where('opl_entity_id', $objListing->lst_id)->latest('opl_id')->first();
    expect($objLog->opl_action)->toBe('update');
    expect($objLog->opl_reason)->toBe('Switched to Online iTOUR reporting.');
    expect($objLog->opl_new_values)->toBe(['lst_reporting_mode' => 'DIGITAL']);
});

test('the temporary password is shown once on the details page right after activation', function () {
    Mail::fake();
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objLgu = adoptionLgu($objMati);
    $objListing = adoptionEstablishment($objMati);

    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.switchToOnline', $objListing), adoptionAccountPayload());
    $strPassphrase = session('accountCreated')['passphrase'];

    test()->actingAs($objLgu)->get(route('lgu.directory.establishments.show', $objListing))
        ->assertOk()
        ->assertSee('account-created-modal', false)
        ->assertSee($strPassphrase)
        ->assertSee('Switch to Manual/Paper');

    // The flash is gone on the next request.
    test()->actingAs($objLgu)->get(route('lgu.directory.establishments.show', $objListing))
        ->assertOk()
        ->assertDontSee($strPassphrase)
        ->assertDontSee('account-created-modal', false);
});

test('activation ignores forged municipality, establishment, role, and status fields', function () {
    Mail::fake();
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objBaganga = adoptionMunicipality('Baganga', 'BAG');
    $objListing = adoptionEstablishment($objMati);
    $objOtherListing = adoptionEstablishment($objBaganga);

    test()->actingAs(adoptionLgu($objMati))->post(route('lgu.directory.establishments.switchToOnline', $objListing), adoptionAccountPayload([
        'municipality_id' => $objBaganga->mun_id,
        'establishment_id' => $objOtherListing->lst_id,
        'role' => UserRole::PtoAdministrator->value,
        'status' => 'Inactive',
    ]))->assertSessionHasNoErrors();

    $objAccount = User::query()->where('usr_email', 'frontdesk@adoption-inn.test')->sole();
    expect($objAccount->mun_id)->toBe($objMati->mun_id);
    expect($objAccount->lst_id)->toBe($objListing->lst_id);
    expect($objAccount->usr_role)->toBe(UserRole::Establishment);
    expect($objAccount->usr_status)->toBe('Active');
    expect($objOtherListing->fresh()->establishmentUser)->toBeNull();
});

test('activation rejects an email already used by another account and changes nothing', function () {
    Mail::fake();
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objListing = adoptionEstablishment($objMati);
    User::factory()->create(['usr_email' => 'taken@example.test']);

    test()->actingAs(adoptionLgu($objMati))
        ->from(route('lgu.directory.establishments.show', $objListing))
        ->post(route('lgu.directory.establishments.switchToOnline', $objListing), adoptionAccountPayload(['account_email' => 'taken@example.test']))
        ->assertRedirect(route('lgu.directory.establishments.show', $objListing))
        ->assertSessionHasErrors('account_email');

    expect($objListing->fresh()->lst_reporting_mode)->toBe(ReportingMethod::ManualPaper);
    expect($objListing->fresh()->establishmentUser)->toBeNull();
    Mail::assertNothingSent();
});

test('an LGU cannot switch another municipality\'s establishment either way (403 and a security log)', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objBaganga = adoptionMunicipality('Baganga', 'BAG');
    $objMatiLgu = adoptionLgu($objMati);
    $objPaperListing = adoptionEstablishment($objBaganga);
    [$objOnlineListing, $objAccount] = adoptionOnlineEstablishment($objBaganga, ['lst_name' => 'Baganga Online Inn']);

    test()->actingAs($objMatiLgu)->post(route('lgu.directory.establishments.switchToOnline', $objPaperListing), adoptionAccountPayload())->assertForbidden();
    test()->actingAs($objMatiLgu)->patch(route('lgu.directory.establishments.switchToManual', $objOnlineListing))->assertForbidden();

    expect($objPaperListing->fresh()->lst_reporting_mode)->toBe(ReportingMethod::ManualPaper);
    expect($objPaperListing->fresh()->establishmentUser)->toBeNull();
    expect($objOnlineListing->fresh()->lst_reporting_mode)->toBe(ReportingMethod::OnlineItour);
    expect($objAccount->fresh()->usr_status)->toBe('Active');
    expect(SecurityLog::query()->where('usr_id', $objMatiLgu->usr_id)->where('sec_event_type', 'access_denied')->count())->toBe(2);
});

test('only the LGU role can switch reporting methods', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    [$objListing, $objAccount] = adoptionOnlineEstablishment($objMati);

    test()->actingAs($objAccount)->patch(route('lgu.directory.establishments.switchToManual', $objListing))->assertForbidden();
    test()->actingAs(User::factory()->create(['usr_role' => UserRole::PtoAdministrator]))
        ->patch(route('lgu.directory.establishments.switchToManual', $objListing))->assertForbidden();

    expect($objListing->fresh()->lst_reporting_mode)->toBe(ReportingMethod::OnlineItour);
});

test('a destination cannot be switched (404)', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objDestination = adoptionEstablishment($objMati, ['lst_name' => 'Adoption Falls', 'lst_category' => 'destinations', 'cat_id' => adoptionCategory('Tourist Destinations')->cat_id, 'lst_type' => null, 'lst_status' => 'Active']);

    test()->actingAs(adoptionLgu($objMati))->post(route('lgu.directory.establishments.switchToOnline', $objDestination), adoptionAccountPayload())->assertNotFound();

    expect(User::query()->where('usr_email', 'frontdesk@adoption-inn.test')->exists())->toBeFalse();
});

// --- QR eligibility ---

test('QR works once the account is active — the destination listing does not need to be Published', function () {
    Mail::fake();
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objListing = adoptionEstablishment($objMati);

    test()->get(route('lgu.establishmentQr', $objListing->lst_uuid))->assertSee('This establishment is not accepting registrations');

    test()->actingAs(adoptionLgu($objMati))->post(route('lgu.directory.establishments.switchToOnline', $objListing), adoptionAccountPayload());
    auth()->logout();

    $objFresh = $objListing->fresh();
    expect($objFresh->lst_status)->toBe('DRAFT');
    expect($objFresh->isAcceptingRegistrations())->toBeTrue();
    test()->get(route('lgu.establishmentQr', $objListing->lst_uuid))->assertOk()->assertSee('establishment-qr-form', false);
});

test('QR stays off for an Online establishment that is Suspended or Archived, in a non-QR category, or with QR switched off', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    [$objSuspended] = adoptionOnlineEstablishment($objMati, ['lst_name' => 'Suspended Inn', 'lst_status' => 'Suspended']);
    [$objArchived] = adoptionOnlineEstablishment($objMati, ['lst_name' => 'Archived Inn', 'lst_status' => 'Archived']);
    [$objSwitchedOff] = adoptionOnlineEstablishment($objMati, ['lst_name' => 'Switched Off Inn']);
    $objSwitchedOff->forceFill(['lst_is_qr_enabled' => false])->save();
    [$objNonQr] = adoptionOnlineEstablishment($objMati, ['lst_name' => 'Non-QR Inn']);
    $objNonQr->categoryRecord->update(['cat_is_qr_enabled' => false]);

    expect($objSuspended->isAcceptingRegistrations())->toBeFalse();
    expect($objArchived->isAcceptingRegistrations())->toBeFalse();
    expect($objSwitchedOff->fresh()->isAcceptingRegistrations())->toBeFalse();
    expect($objNonQr->fresh()->isAcceptingRegistrations())->toBeFalse();
});

// --- Online iTOUR -> Manual/Paper ---

test('switching to Manual/Paper suspends the account (never deletes it), stops QR, and keeps history', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objLgu = adoptionLgu($objMati);
    [$objListing, $objAccount] = adoptionOnlineEstablishment($objMati);
    $objListing->arrivals()->create([
        'arr_source' => 'self_checkin', 'arr_date' => now()->toDateString(), 'arr_visitor_name' => 'Earlier Guest',
        'arr_visitor_contact' => '0900', 'arr_party_size' => 1, 'arr_status' => 'Recorded',
    ]);
    expect($objListing->isAcceptingRegistrations())->toBeTrue();

    test()->actingAs($objLgu)->patch(route('lgu.directory.establishments.switchToManual', $objListing))
        ->assertRedirect(route('lgu.directory.establishments.show', $objListing))
        ->assertSessionHasNoErrors();

    $objFresh = $objListing->fresh();
    expect($objFresh->lst_reporting_mode)->toBe(ReportingMethod::ManualPaper);
    expect($objFresh->isAcceptingRegistrations())->toBeFalse();
    expect($objFresh->getQrStatus())->toBe(Listing::QR_STATUS_MANUAL_REPORTING);
    expect($objAccount->fresh())->not->toBeNull();
    expect($objAccount->fresh()->usr_status)->toBe('Inactive');
    expect($objAccount->fresh()->lst_id)->toBe($objListing->lst_id);
    expect($objFresh->arrivals()->count())->toBe(1);

    $objLog = OperationLog::query()->where('opl_entity_type', 'establishment')->where('opl_entity_id', $objListing->lst_id)->latest('opl_id')->first();
    expect($objLog->usr_id)->toBe($objLgu->usr_id);
    expect($objLog->opl_old_values)->toBe(['lst_reporting_mode' => 'DIGITAL']);
    expect($objLog->opl_new_values)->toBe(['lst_reporting_mode' => 'PAPER_LGU']);
    expect($objLog->opl_reason)->toContain('account suspended');
    expect(SecurityLog::query()->where('sec_event_type', 'account_suspended')->where('sec_target_user_id', $objAccount->usr_id)->exists())->toBeTrue();

    auth()->logout();
    test()->get(route('lgu.establishmentQr', $objListing->lst_uuid))->assertSee('This establishment is not accepting registrations');
});

test('switching back to Online iTOUR reuses and reactivates the same account — no duplicate, no new password', function () {
    Mail::fake();
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objLgu = adoptionLgu($objMati);
    [$objListing, $objAccount] = adoptionOnlineEstablishment($objMati);
    $strPasswordHash = $objAccount->usr_password;

    test()->actingAs($objLgu)->patch(route('lgu.directory.establishments.switchToManual', $objListing));
    expect($objAccount->fresh()->usr_status)->toBe('Inactive');

    test()->actingAs($objLgu)->get(route('lgu.directory.establishments.show', $objListing))
        ->assertSee('Switch to Online iTOUR')
        ->assertSee('It will be reactivated with its current password');

    // A new-account payload is ignored when the establishment already has its account.
    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.switchToOnline', $objListing), adoptionAccountPayload(['account_email' => 'second-account@example.test']))
        ->assertRedirect(route('lgu.directory.establishments.show', $objListing))
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('accountCreated');

    expect(User::query()->where('lst_id', $objListing->lst_id)->count())->toBe(1);
    expect(User::query()->where('usr_email', 'second-account@example.test')->exists())->toBeFalse();
    $objFresh = $objAccount->fresh();
    expect($objFresh->usr_id)->toBe($objAccount->usr_id);
    expect($objFresh->usr_status)->toBe('Active');
    expect($objFresh->usr_password)->toBe($strPasswordHash);
    expect($objListing->fresh()->isAcceptingRegistrations())->toBeTrue();
    expect(SecurityLog::query()->where('sec_event_type', 'account_reactivated')->where('sec_target_user_id', $objAccount->usr_id)->exists())->toBeTrue();
    Mail::assertNothingSent();
});

test('repeating a switch that already applies changes nothing', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objLgu = adoptionLgu($objMati);
    $objPaper = adoptionEstablishment($objMati);
    [$objOnline] = adoptionOnlineEstablishment($objMati, ['lst_name' => 'Already Online Inn']);

    test()->actingAs($objLgu)->patch(route('lgu.directory.establishments.switchToManual', $objPaper))->assertSessionHas('toast_tone', 'danger');
    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.switchToOnline', $objOnline))->assertSessionHas('toast_tone', 'danger');

    expect($objPaper->fresh()->lst_reporting_mode)->toBe(ReportingMethod::ManualPaper);
    expect($objOnline->fresh()->lst_reporting_mode)->toBe(ReportingMethod::OnlineItour);
    expect(OperationLog::query()->whereIn('opl_entity_id', [$objPaper->lst_id, $objOnline->lst_id])->where('opl_entity_type', 'establishment')->count())->toBe(0);
});

// --- Establishment Accounts page ---

test('the old Establishment Accounts "Add" endpoint creates nothing and points to the establishment flow', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $intListings = Listing::query()->count();
    $intUsers = User::query()->count();

    test()->actingAs(adoptionLgu($objMati))->post(route('lgu.users.store'), [
        'name' => 'Combined Form Inn',
        'category' => 'accommodation',
        'email' => 'combined@example.test',
    ])->assertRedirect(route('lgu.directory.establishments'))->assertSessionHas('toast');

    expect(Listing::query()->count())->toBe($intListings);
    expect(User::query()->count())->toBe($intUsers + 1);
});

test('an account on a Manual/Paper establishment cannot be re-enabled from Establishment Accounts', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objLgu = adoptionLgu($objMati);
    [$objListing, $objAccount] = adoptionOnlineEstablishment($objMati);
    test()->actingAs($objLgu)->patch(route('lgu.directory.establishments.switchToManual', $objListing));

    test()->actingAs($objLgu)->patch(route('lgu.users.toggleStatus', $objAccount))->assertSessionHas('toast_tone', 'danger');

    expect($objAccount->fresh()->usr_status)->toBe('Inactive');
    expect($objListing->fresh()->isAcceptingRegistrations())->toBeFalse();
});

test('disabling an Online establishment\'s account from Establishment Accounts stops QR', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    [$objListing, $objAccount] = adoptionOnlineEstablishment($objMati);

    test()->actingAs(adoptionLgu($objMati))->patch(route('lgu.users.toggleStatus', $objAccount))->assertSessionHasNoErrors();

    expect($objAccount->fresh()->usr_status)->toBe('Inactive');
    expect($objListing->fresh()->getQrStatus())->toBe(Listing::QR_STATUS_NO_ACCOUNT);
});

// --- Account link guard ---

test('a signed-in non-PTO user cannot relink an establishment account, even through the model', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objBaganga = adoptionMunicipality('Baganga', 'BAG');
    $objLgu = adoptionLgu($objMati);
    [$objListing, $objAccount] = adoptionOnlineEstablishment($objMati);
    $objOtherListing = adoptionEstablishment($objMati, ['lst_name' => 'Other Inn']);

    test()->actingAs($objLgu);

    expect(fn () => $objAccount->update(['lst_id' => $objOtherListing->lst_id]))->toThrow(AuthorizationException::class);
    expect(fn () => $objAccount->fresh()->update(['mun_id' => $objBaganga->mun_id]))->toThrow(AuthorizationException::class);
    expect($objAccount->fresh()->lst_id)->toBe($objListing->lst_id);
    expect($objAccount->fresh()->mun_id)->toBe($objMati->mun_id);
    expect(SecurityLog::query()->where('usr_id', $objLgu->usr_id)->where('sec_event_type', 'access_denied')->count())->toBe(2);

    // The PTO keeps province-wide control.
    test()->actingAs(User::factory()->create(['usr_role' => UserRole::PtoAdministrator]));
    $objAccount->fresh()->update(['mun_id' => $objBaganga->mun_id]);
    expect($objAccount->fresh()->mun_id)->toBe($objBaganga->mun_id);
});

// --- Establishment Profile uses the same Category -> Type source ---

test('the establishment Profile validates the type against the chosen category and keeps the legacy slug in sync', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    [$objListing, $objAccount] = adoptionOnlineEstablishment($objMati);
    $objFood = adoptionCategory('Food & Dining');

    test()->actingAs($objAccount)->get(route('establishment.profile'))
        ->assertOk()
        ->assertSee('name="cat_id"', false)
        ->assertSee('Food &amp; Dining', false)
        ->assertSee('Restobar');

    test()->actingAs($objAccount)->put(route('establishment.profile.update'), ['name' => $objListing->lst_name, 'cat_id' => $objFood->cat_id, 'type' => 'Hotel'])
        ->assertSessionHasErrors('type');
    test()->actingAs($objAccount)->put(route('establishment.profile.update'), ['name' => $objListing->lst_name, 'cat_id' => $objFood->cat_id])
        ->assertSessionHasErrors('type');
    test()->actingAs($objAccount)->put(route('establishment.profile.update'), ['name' => $objListing->lst_name, 'cat_id' => adoptionCategory('Tourist Destinations')->cat_id, 'type' => 'Hotel'])
        ->assertSessionHasErrors('cat_id');
    expect($objListing->fresh()->cat_id)->toBe(adoptionCategory()->cat_id);

    test()->actingAs($objAccount)->put(route('establishment.profile.update'), ['name' => $objListing->lst_name, 'cat_id' => $objFood->cat_id, 'type' => 'Restobar'])
        ->assertSessionHasNoErrors();

    $objFresh = $objListing->fresh();
    expect($objFresh->cat_id)->toBe($objFood->cat_id);
    expect($objFresh->lst_category)->toBe('restaurants');
    expect($objFresh->lst_type)->toBe('Restobar');
});
