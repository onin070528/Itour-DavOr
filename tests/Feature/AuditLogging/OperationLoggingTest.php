<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Listing;
use App\Models\MunicipalReport;
use App\Models\OperationLog;
use App\Models\User;

function makePtoForOperationLogs(): User
{
    return User::factory()->create(['role' => UserRole::PtoAdministrator]);
}

function destinationsCategoryFixture(): Category
{
    return Category::query()->firstOrCreate(
        ['cat_name' => 'Tourist Destinations'],
        ['cat_sort_order' => 0, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );
}

test('PTO creating a destination records a create operation log with the resolved municipality', function () {
    makeMunicipalityFixture('Cateel', 'CAT');
    $category = destinationsCategoryFixture();
    $pto = makePtoForOperationLogs();

    test()->actingAs($pto)->post(route('pto.directory.store'), [
        'name' => 'Aliwagwag Falls',
        'cat_id' => $category->cat_id,
        'barangay' => 'Aliwagwag',
        'municipality' => 'Cateel',
    ])->assertSessionHasNoErrors();

    $listing = Listing::query()->where('name', 'Aliwagwag Falls')->first();
    $log = OperationLog::where('entity_type', 'establishment')->where('entity_id', $listing->id)->where('action', 'create')->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($pto->id);
    expect($log->user_role)->toBe('pto_administrator');
    expect($log->municipality_id)->toBe($listing->municipality_id);
    expect($listing->municipality_id)->not->toBeNull();
});

test('PTO updating a destination records an update operation log with only the changed fields', function () {
    makeMunicipalityFixture('Cateel', 'CAT');
    $category = destinationsCategoryFixture();
    $pto = makePtoForOperationLogs();

    test()->actingAs($pto)->post(route('pto.directory.store'), [
        'name' => 'Aliwagwag Falls',
        'cat_id' => $category->cat_id,
        'barangay' => 'Aliwagwag',
        'municipality' => 'Cateel',
    ]);
    $listing = Listing::query()->where('name', 'Aliwagwag Falls')->first();

    test()->actingAs($pto)->put(route('pto.directory.update', $listing), [
        'name' => 'Aliwagwag Falls',
        'cat_id' => $category->cat_id,
        'barangay' => 'New Barangay',
        'municipality' => 'Cateel',
    ])->assertSessionHasNoErrors();

    $log = OperationLog::where('entity_type', 'establishment')->where('entity_id', $listing->id)->where('action', 'update')->first();
    expect($log)->not->toBeNull();
    expect($log->old_values)->toBe(['barangay' => 'Aliwagwag']);
    expect($log->new_values)->toBe(['barangay' => 'New Barangay']);
});

test('suspending a destination records an update operation log with the required reason', function () {
    makeMunicipalityFixture('Cateel', 'CAT');
    $category = destinationsCategoryFixture();
    $pto = makePtoForOperationLogs();

    test()->actingAs($pto)->post(route('pto.directory.store'), [
        'name' => 'Aliwagwag Falls',
        'cat_id' => $category->cat_id,
        'barangay' => 'Aliwagwag',
        'municipality' => 'Cateel',
    ]);
    $listing = Listing::query()->where('name', 'Aliwagwag Falls')->first();

    test()->actingAs($pto)->put(route('pto.directory.updateStatus', $listing), [
        'status' => 'Suspended',
        'reason' => 'Temporarily closed for maintenance.',
    ])->assertSessionHasNoErrors();

    $log = OperationLog::where('entity_type', 'establishment')->where('entity_id', $listing->id)->where('action', 'update')->latest('id')->first();
    expect($log->new_values)->toBe(['status' => 'Suspended']);
    expect($log->reason)->toBe('Temporarily closed for maintenance.');
});

test('LGU can create, update, and archive its own destination, each recording an operation log', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);

    test()->actingAs($lgu)->post(route('lgu.directory.destinations.store'), [
        'name' => 'Dahican Beach',
        'barangay' => 'Dahican',
    ])->assertSessionHasNoErrors();
    $listing = Listing::query()->where('name', 'Dahican Beach')->first();

    // This used to 403 before the municipality_id fix — createDestination()
    // never set it, so authorizeOwnMunicipality() always rejected the LGU
    // that had just created the row.
    test()->actingAs($lgu)->put(route('lgu.directory.destinations.update', $listing), [
        'name' => 'Dahican Beach',
        'barangay' => 'New Barangay',
    ])->assertSessionHasNoErrors();

    test()->actingAs($lgu)->patch(route('lgu.directory.destinations.archive', $listing))->assertSessionHasNoErrors();

    expect(OperationLog::where('entity_type', 'destination')->where('entity_id', $listing->id)->where('action', 'create')->exists())->toBeTrue();
    expect(OperationLog::where('entity_type', 'destination')->where('entity_id', $listing->id)->where('action', 'update')->count())->toBe(2);
    expect(OperationLog::where('entity_type', 'destination')->where('entity_id', $listing->id)->where('municipality_id', $mati->id)->exists())->toBeTrue();
});

test('LGU submitting an establishment to PTO records a submit operation log', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);
    $listing = Listing::query()->create([
        'slug' => 'unverified-inn',
        'name' => 'Unverified Inn',
        'category' => 'accommodation',
        'municipality' => 'City of Mati',
        'municipality_id' => $mati->id,
        'barangay' => 'Poblacion',
        'status' => 'DRAFT',
    ]);

    test()->actingAs($lgu)->patch(route('lgu.directory.establishments.submit', $listing))->assertSessionHasNoErrors();

    $log = OperationLog::where('entity_type', 'establishment')->where('entity_id', $listing->id)->where('action', 'submit')->first();
    expect($log)->not->toBeNull();
    expect($log->establishment_id)->toBe($listing->id);
    expect($log->new_values)->toBe(['status' => 'FOR_PTO_REVIEW']);
});

test('PTO publishing an establishment records a publish operation log', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $pto = makePtoForOperationLogs();
    $listing = Listing::query()->create([
        'slug' => 'for-review-inn',
        'name' => 'For Review Inn',
        'category' => 'accommodation',
        'municipality' => 'City of Mati',
        'municipality_id' => $mati->id,
        'barangay' => 'Poblacion',
        'status' => 'FOR_PTO_REVIEW',
    ]);

    test()->actingAs($pto)->patch(route('pto.directory.publish', $listing))->assertSessionHasNoErrors();

    $log = OperationLog::where('entity_type', 'establishment')->where('entity_id', $listing->id)->where('action', 'publish')->first();
    expect($log)->not->toBeNull();
    expect($log->new_values)->toBe(['status' => 'PUBLISHED']);
    expect($listing->fresh()->status)->toBe('PUBLISHED');
});

test('editing an establishment account records an update operation log with masked email', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = Listing::query()->create([
        'slug' => 'mati-fixture-inn-op-log',
        'name' => 'Mati Fixture Inn',
        'category' => 'accommodation',
        'municipality' => 'City of Mati',
        'municipality_id' => $mati->id,
        'barangay' => 'Poblacion',
        'owner_name' => 'Juan Dela Cruz',
        'contact_phone' => '09171234567',
        'email' => 'old@matifixtureinn.test',
        'status' => 'PUBLISHED',
    ]);
    $establishmentUser = User::factory()->create([
        'role' => UserRole::Establishment,
        'municipality_id' => $mati->id,
        'establishment_id' => $listing->id,
    ]);
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);

    test()->actingAs($lgu)->put(route('lgu.users.update', $establishmentUser), [
        'name' => $listing->owner_name,
        'email' => 'new@matifixtureinn.test',
    ])->assertSessionHasNoErrors();

    $log = OperationLog::where('entity_type', 'user')->where('entity_id', $establishmentUser->id)->where('action', 'update')->first();
    expect($log)->not->toBeNull();
    expect($log->municipality_id)->toBe($mati->id);
    expect($log->establishment_id)->toBe($listing->id);
    expect($log->new_values['email'])->not->toBe('new@matifixtureinn.test');
    expect($log->new_values['email'])->toContain('***@');
    expect(json_encode($log->toArray()))->not->toContain('new@matifixtureinn.test');
});

test('approving a municipal report records an approve operation log with the correct municipality and acting user', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $pto = makePtoForOperationLogs();
    $submitter = User::factory()->create(['role' => UserRole::Lgu, 'organization_subtitle' => 'City of Mati', 'municipality_id' => $mati->id]);
    $report = MunicipalReport::query()->create([
        'municipality' => 'City of Mati',
        'submitted_by' => $submitter->id,
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
        'total_arrivals' => 1000,
        'status' => MunicipalReport::STATUS_SUBMITTED,
    ]);

    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report))->assertRedirect();

    $log = OperationLog::where('entity_type', 'municipal_report')->where('entity_id', $report->id)->where('action', 'approve')->first();
    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($pto->id);
    expect($log->municipality_id)->toBe($mati->id);
});

test('returning a municipal report records a return operation log and requires a reason', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $pto = makePtoForOperationLogs();
    $submitter = User::factory()->create(['role' => UserRole::Lgu, 'organization_subtitle' => 'City of Mati', 'municipality_id' => $mati->id]);
    $report = MunicipalReport::query()->create([
        'municipality' => 'City of Mati',
        'submitted_by' => $submitter->id,
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
        'total_arrivals' => 1000,
        'status' => MunicipalReport::STATUS_SUBMITTED,
    ]);

    // No remarks — the pre-existing validation already requires it; this is
    // the same rule the spec calls "reason REQUIRED for return".
    test()->actingAs($pto)->patch(route('pto.municipalReports.return', $report), [])
        ->assertSessionHasErrors('remarks');
    expect(OperationLog::where('entity_type', 'municipal_report')->where('action', 'return')->exists())->toBeFalse();

    test()->actingAs($pto)->patch(route('pto.municipalReports.return', $report), ['remarks' => 'Please recheck the arrival totals.'])
        ->assertRedirect();

    $log = OperationLog::where('entity_type', 'municipal_report')->where('entity_id', $report->id)->where('action', 'return')->first();
    expect($log)->not->toBeNull();
    expect($log->reason)->toBe('Please recheck the arrival totals.');
});
