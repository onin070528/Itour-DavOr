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
    return Municipality::query()->firstOrCreate(['code' => $strCode], ['name' => $strName]);
}

function adoptionLgu(Municipality $objMunicipality): User
{
    return User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_name' => "{$objMunicipality->name} LGU",
        'organization_subtitle' => $objMunicipality->name,
        'municipality_id' => $objMunicipality->id,
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
        'slug' => Str::slug("{$objMunicipality->name}-adoption-inn-".Str::random(6)),
        'name' => "{$objMunicipality->name} Adoption Inn",
        'category' => $objCategory->legacySlug(),
        'cat_id' => $objCategory->cat_id,
        'type' => 'Hotel',
        'municipality' => $objMunicipality->name,
        'municipality_id' => $objMunicipality->id,
        'barangay' => 'Poblacion',
        'owner_name' => 'Juan Dela Cruz',
        'email' => 'owner@adoption-inn.test',
        'status' => 'DRAFT',
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
    $objListing->forceFill(['reporting_mode' => ReportingMethod::OnlineItour])->save();
    $objAccount = User::factory()->create([
        'role' => UserRole::Establishment,
        'organization_name' => $objListing->name,
        'organization_subtitle' => "{$objListing->barangay}, {$objListing->municipality}",
        'municipality_id' => $objMunicipality->id,
        'establishment_id' => $objListing->id,
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

    $objListing = Listing::query()->where('name', 'Brand New Inn')->sole();

    expect($objListing->reporting_mode)->toBe(ReportingMethod::ManualPaper);
    expect($objListing->establishmentUser)->toBeNull();
    expect(User::query()->where('email', 'brand-new-inn@example.test')->exists())->toBeFalse();
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

    $objAccount = User::query()->where('establishment_id', $objListing->id)->sole();
    expect($objAccount->email)->toBe('frontdesk@adoption-inn.test');
    expect($objAccount->role)->toBe(UserRole::Establishment);
    expect($objAccount->municipality_id)->toBe($objMati->id);
    expect($objAccount->status)->toBe('Active');
    expect($objAccount->usr_must_change_password)->toBeTrue();
    expect($objAccount->created_by)->toBe($objLgu->id);
    expect(session('accountCreated')['userId'])->toBe($objAccount->id);

    // Never stored or logged in plaintext.
    expect($strPassphrase)->not->toBeEmpty();
    expect(Hash::check($strPassphrase, $objAccount->password))->toBeTrue();
    expect(DB::table('users')->where('password', $strPassphrase)->exists())->toBeFalse();
    expect(json_encode(OperationLog::query()->get()->toArray()))->not->toContain($strPassphrase);
    expect(json_encode(SecurityLog::query()->get()->toArray()))->not->toContain($strPassphrase);

    Mail::assertSent(WelcomeAccountCreated::class, fn (WelcomeAccountCreated $objMail) => $objMail->hasTo('frontdesk@adoption-inn.test') && $objMail->strPassphrase === $strPassphrase);

    expect($objListing->fresh()->reporting_mode)->toBe(ReportingMethod::OnlineItour);
    $objLog = OperationLog::query()->where('entity_type', 'establishment')->where('entity_id', $objListing->id)->latest('id')->first();
    expect($objLog->action)->toBe('update');
    expect($objLog->reason)->toBe('Switched to Online iTOUR reporting.');
    expect($objLog->new_values)->toBe(['reporting_mode' => 'DIGITAL']);
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
        'municipality_id' => $objBaganga->id,
        'establishment_id' => $objOtherListing->id,
        'role' => UserRole::PtoAdministrator->value,
        'status' => 'Inactive',
    ]))->assertSessionHasNoErrors();

    $objAccount = User::query()->where('email', 'frontdesk@adoption-inn.test')->sole();
    expect($objAccount->municipality_id)->toBe($objMati->id);
    expect($objAccount->establishment_id)->toBe($objListing->id);
    expect($objAccount->role)->toBe(UserRole::Establishment);
    expect($objAccount->status)->toBe('Active');
    expect($objOtherListing->fresh()->establishmentUser)->toBeNull();
});

test('activation rejects an email already used by another account and changes nothing', function () {
    Mail::fake();
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objListing = adoptionEstablishment($objMati);
    User::factory()->create(['email' => 'taken@example.test']);

    test()->actingAs(adoptionLgu($objMati))
        ->from(route('lgu.directory.establishments.show', $objListing))
        ->post(route('lgu.directory.establishments.switchToOnline', $objListing), adoptionAccountPayload(['account_email' => 'taken@example.test']))
        ->assertRedirect(route('lgu.directory.establishments.show', $objListing))
        ->assertSessionHasErrors('account_email');

    expect($objListing->fresh()->reporting_mode)->toBe(ReportingMethod::ManualPaper);
    expect($objListing->fresh()->establishmentUser)->toBeNull();
    Mail::assertNothingSent();
});

test('an LGU cannot switch another municipality\'s establishment either way (403 and a security log)', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objBaganga = adoptionMunicipality('Baganga', 'BAG');
    $objMatiLgu = adoptionLgu($objMati);
    $objPaperListing = adoptionEstablishment($objBaganga);
    [$objOnlineListing, $objAccount] = adoptionOnlineEstablishment($objBaganga, ['name' => 'Baganga Online Inn']);

    test()->actingAs($objMatiLgu)->post(route('lgu.directory.establishments.switchToOnline', $objPaperListing), adoptionAccountPayload())->assertForbidden();
    test()->actingAs($objMatiLgu)->patch(route('lgu.directory.establishments.switchToManual', $objOnlineListing))->assertForbidden();

    expect($objPaperListing->fresh()->reporting_mode)->toBe(ReportingMethod::ManualPaper);
    expect($objPaperListing->fresh()->establishmentUser)->toBeNull();
    expect($objOnlineListing->fresh()->reporting_mode)->toBe(ReportingMethod::OnlineItour);
    expect($objAccount->fresh()->status)->toBe('Active');
    expect(SecurityLog::query()->where('user_id', $objMatiLgu->id)->where('event_type', 'access_denied')->count())->toBe(2);
});

test('only the LGU role can switch reporting methods', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    [$objListing, $objAccount] = adoptionOnlineEstablishment($objMati);

    test()->actingAs($objAccount)->patch(route('lgu.directory.establishments.switchToManual', $objListing))->assertForbidden();
    test()->actingAs(User::factory()->create(['role' => UserRole::PtoAdministrator]))
        ->patch(route('lgu.directory.establishments.switchToManual', $objListing))->assertForbidden();

    expect($objListing->fresh()->reporting_mode)->toBe(ReportingMethod::OnlineItour);
});

test('a destination cannot be switched (404)', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objDestination = adoptionEstablishment($objMati, ['name' => 'Adoption Falls', 'category' => 'destinations', 'cat_id' => adoptionCategory('Tourist Destinations')->cat_id, 'type' => null, 'status' => 'Active']);

    test()->actingAs(adoptionLgu($objMati))->post(route('lgu.directory.establishments.switchToOnline', $objDestination), adoptionAccountPayload())->assertNotFound();

    expect(User::query()->where('email', 'frontdesk@adoption-inn.test')->exists())->toBeFalse();
});

// --- QR eligibility ---

test('QR works once the account is active — the destination listing does not need to be Published', function () {
    Mail::fake();
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objListing = adoptionEstablishment($objMati);

    test()->get(route('lgu.establishmentQr', $objListing->uuid))->assertSee('This establishment is not accepting registrations');

    test()->actingAs(adoptionLgu($objMati))->post(route('lgu.directory.establishments.switchToOnline', $objListing), adoptionAccountPayload());
    auth()->logout();

    $objFresh = $objListing->fresh();
    expect($objFresh->status)->toBe('DRAFT');
    expect($objFresh->isAcceptingRegistrations())->toBeTrue();
    test()->get(route('lgu.establishmentQr', $objListing->uuid))->assertOk()->assertSee('establishment-qr-form', false);
});

test('QR stays off for an Online establishment that is Suspended or Archived, in a non-QR category, or with QR switched off', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    [$objSuspended] = adoptionOnlineEstablishment($objMati, ['name' => 'Suspended Inn', 'status' => 'Suspended']);
    [$objArchived] = adoptionOnlineEstablishment($objMati, ['name' => 'Archived Inn', 'status' => 'Archived']);
    [$objSwitchedOff] = adoptionOnlineEstablishment($objMati, ['name' => 'Switched Off Inn']);
    $objSwitchedOff->forceFill(['lst_is_qr_enabled' => false])->save();
    [$objNonQr] = adoptionOnlineEstablishment($objMati, ['name' => 'Non-QR Inn']);
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
        'source' => 'self_checkin', 'date' => now()->toDateString(), 'visitor_name' => 'Earlier Guest',
        'visitor_contact' => '0900', 'party_size' => 1, 'status' => 'Recorded',
    ]);
    expect($objListing->isAcceptingRegistrations())->toBeTrue();

    test()->actingAs($objLgu)->patch(route('lgu.directory.establishments.switchToManual', $objListing))
        ->assertRedirect(route('lgu.directory.establishments.show', $objListing))
        ->assertSessionHasNoErrors();

    $objFresh = $objListing->fresh();
    expect($objFresh->reporting_mode)->toBe(ReportingMethod::ManualPaper);
    expect($objFresh->isAcceptingRegistrations())->toBeFalse();
    expect($objFresh->getQrStatus())->toBe(Listing::QR_STATUS_MANUAL_REPORTING);
    expect($objAccount->fresh())->not->toBeNull();
    expect($objAccount->fresh()->status)->toBe('Inactive');
    expect($objAccount->fresh()->establishment_id)->toBe($objListing->id);
    expect($objFresh->arrivals()->count())->toBe(1);

    $objLog = OperationLog::query()->where('entity_type', 'establishment')->where('entity_id', $objListing->id)->latest('id')->first();
    expect($objLog->user_id)->toBe($objLgu->id);
    expect($objLog->old_values)->toBe(['reporting_mode' => 'DIGITAL']);
    expect($objLog->new_values)->toBe(['reporting_mode' => 'PAPER_LGU']);
    expect($objLog->reason)->toContain('account suspended');
    expect(SecurityLog::query()->where('event_type', 'account_suspended')->where('target_user_id', $objAccount->id)->exists())->toBeTrue();

    auth()->logout();
    test()->get(route('lgu.establishmentQr', $objListing->uuid))->assertSee('This establishment is not accepting registrations');
});

test('switching back to Online iTOUR reuses and reactivates the same account — no duplicate, no new password', function () {
    Mail::fake();
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objLgu = adoptionLgu($objMati);
    [$objListing, $objAccount] = adoptionOnlineEstablishment($objMati);
    $strPasswordHash = $objAccount->password;

    test()->actingAs($objLgu)->patch(route('lgu.directory.establishments.switchToManual', $objListing));
    expect($objAccount->fresh()->status)->toBe('Inactive');

    test()->actingAs($objLgu)->get(route('lgu.directory.establishments.show', $objListing))
        ->assertSee('Switch to Online iTOUR')
        ->assertSee('It will be reactivated with its current password');

    // A new-account payload is ignored when the establishment already has its account.
    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.switchToOnline', $objListing), adoptionAccountPayload(['account_email' => 'second-account@example.test']))
        ->assertRedirect(route('lgu.directory.establishments.show', $objListing))
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('accountCreated');

    expect(User::query()->where('establishment_id', $objListing->id)->count())->toBe(1);
    expect(User::query()->where('email', 'second-account@example.test')->exists())->toBeFalse();
    $objFresh = $objAccount->fresh();
    expect($objFresh->id)->toBe($objAccount->id);
    expect($objFresh->status)->toBe('Active');
    expect($objFresh->password)->toBe($strPasswordHash);
    expect($objListing->fresh()->isAcceptingRegistrations())->toBeTrue();
    expect(SecurityLog::query()->where('event_type', 'account_reactivated')->where('target_user_id', $objAccount->id)->exists())->toBeTrue();
    Mail::assertNothingSent();
});

test('repeating a switch that already applies changes nothing', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objLgu = adoptionLgu($objMati);
    $objPaper = adoptionEstablishment($objMati);
    [$objOnline] = adoptionOnlineEstablishment($objMati, ['name' => 'Already Online Inn']);

    test()->actingAs($objLgu)->patch(route('lgu.directory.establishments.switchToManual', $objPaper))->assertSessionHas('toast_tone', 'danger');
    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.switchToOnline', $objOnline))->assertSessionHas('toast_tone', 'danger');

    expect($objPaper->fresh()->reporting_mode)->toBe(ReportingMethod::ManualPaper);
    expect($objOnline->fresh()->reporting_mode)->toBe(ReportingMethod::OnlineItour);
    expect(OperationLog::query()->whereIn('entity_id', [$objPaper->id, $objOnline->id])->where('entity_type', 'establishment')->count())->toBe(0);
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

    expect($objAccount->fresh()->status)->toBe('Inactive');
    expect($objListing->fresh()->isAcceptingRegistrations())->toBeFalse();
});

test('disabling an Online establishment\'s account from Establishment Accounts stops QR', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    [$objListing, $objAccount] = adoptionOnlineEstablishment($objMati);

    test()->actingAs(adoptionLgu($objMati))->patch(route('lgu.users.toggleStatus', $objAccount))->assertSessionHasNoErrors();

    expect($objAccount->fresh()->status)->toBe('Inactive');
    expect($objListing->fresh()->getQrStatus())->toBe(Listing::QR_STATUS_NO_ACCOUNT);
});

// --- Account link guard ---

test('a signed-in non-PTO user cannot relink an establishment account, even through the model', function () {
    $objMati = adoptionMunicipality('City of Mati', 'MATI');
    $objBaganga = adoptionMunicipality('Baganga', 'BAG');
    $objLgu = adoptionLgu($objMati);
    [$objListing, $objAccount] = adoptionOnlineEstablishment($objMati);
    $objOtherListing = adoptionEstablishment($objMati, ['name' => 'Other Inn']);

    test()->actingAs($objLgu);

    expect(fn () => $objAccount->update(['establishment_id' => $objOtherListing->id]))->toThrow(AuthorizationException::class);
    expect(fn () => $objAccount->fresh()->update(['municipality_id' => $objBaganga->id]))->toThrow(AuthorizationException::class);
    expect($objAccount->fresh()->establishment_id)->toBe($objListing->id);
    expect($objAccount->fresh()->municipality_id)->toBe($objMati->id);
    expect(SecurityLog::query()->where('user_id', $objLgu->id)->where('event_type', 'access_denied')->count())->toBe(2);

    // The PTO keeps province-wide control.
    test()->actingAs(User::factory()->create(['role' => UserRole::PtoAdministrator]));
    $objAccount->fresh()->update(['municipality_id' => $objBaganga->id]);
    expect($objAccount->fresh()->municipality_id)->toBe($objBaganga->id);
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

    test()->actingAs($objAccount)->put(route('establishment.profile.update'), ['name' => $objListing->name, 'cat_id' => $objFood->cat_id, 'type' => 'Hotel'])
        ->assertSessionHasErrors('type');
    test()->actingAs($objAccount)->put(route('establishment.profile.update'), ['name' => $objListing->name, 'cat_id' => $objFood->cat_id])
        ->assertSessionHasErrors('type');
    test()->actingAs($objAccount)->put(route('establishment.profile.update'), ['name' => $objListing->name, 'cat_id' => adoptionCategory('Tourist Destinations')->cat_id, 'type' => 'Hotel'])
        ->assertSessionHasErrors('cat_id');
    expect($objListing->fresh()->cat_id)->toBe(adoptionCategory()->cat_id);

    test()->actingAs($objAccount)->put(route('establishment.profile.update'), ['name' => $objListing->name, 'cat_id' => $objFood->cat_id, 'type' => 'Restobar'])
        ->assertSessionHasNoErrors();

    $objFresh = $objListing->fresh();
    expect($objFresh->cat_id)->toBe($objFood->cat_id);
    expect($objFresh->category)->toBe('restaurants');
    expect($objFresh->type)->toBe('Restobar');
});
