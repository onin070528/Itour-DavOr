<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — operation logging.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Listing;
use App\Models\MunicipalReport;
use App\Models\OperationLog;
use App\Models\SecurityLog;
use App\Models\User;

function makePtoForOperationLogs(): User
{
    return User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
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

    $listing = Listing::query()->where('lst_name', 'Aliwagwag Falls')->first();
    $log = OperationLog::where('opl_entity_type', 'destination')->where('opl_entity_id', $listing->lst_id)->where('opl_action', 'create')->first();

    expect($log)->not->toBeNull();
    expect($log->usr_id)->toBe($pto->usr_id);
    expect($log->opl_user_role)->toBe('pto_administrator');
    expect($log->mun_id)->toBe($listing->mun_id);
    expect($listing->mun_id)->not->toBeNull();
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
    $listing = Listing::query()->where('lst_name', 'Aliwagwag Falls')->first();

    test()->actingAs($pto)->put(route('pto.directory.update', $listing), [
        'name' => 'Aliwagwag Falls',
        'cat_id' => $category->cat_id,
        'barangay' => 'New Barangay',
        'municipality' => 'Cateel',
    ])->assertSessionHasNoErrors();

    $log = OperationLog::where('opl_entity_type', 'destination')->where('opl_entity_id', $listing->lst_id)->where('opl_action', 'update')->first();
    expect($log)->not->toBeNull();
    expect($log->opl_old_values)->toBe(['lst_barangay' => 'Aliwagwag']);
    expect($log->opl_new_values)->toBe(['lst_barangay' => 'New Barangay']);
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
    $listing = Listing::query()->where('lst_name', 'Aliwagwag Falls')->first();

    test()->actingAs($pto)->put(route('pto.directory.updateStatus', $listing), [
        'status' => 'Suspended',
        'reason' => 'Temporarily closed for maintenance.',
    ])->assertSessionHasNoErrors();

    $log = OperationLog::where('opl_entity_type', 'destination')->where('opl_entity_id', $listing->lst_id)->where('opl_action', 'update')->latest('opl_id')->first();
    expect($log->opl_new_values)->toBe(['lst_status' => 'Suspended']);
    expect($log->opl_reason)->toBe('Temporarily closed for maintenance.');
});

test('LGU can create and update its own destination, each recording an operation log, but archiving is PTO-only (403, security-logged)', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    test()->actingAs($lgu)->post(route('lgu.directory.destinations.store'), [
        'name' => 'Dahican Beach',
        'barangay' => 'Brgy. Dahican',
    ])->assertSessionHasNoErrors();
    $listing = Listing::query()->where('lst_name', 'Dahican Beach')->first();

    // This used to 403 before the municipality_id fix — createDestination()
    // never set it, so authorizeOwnMunicipality() always rejected the LGU
    // that had just created the row.
    test()->actingAs($lgu)->put(route('lgu.directory.destinations.update', $listing), [
        'name' => 'Dahican Beach',
        'barangay' => 'Brgy. Badas',
    ])->assertSessionHasNoErrors();

    // Objective 3 (D3): only the PTO archives — a direct LGU request is refused and security-logged.
    test()->actingAs($lgu)->patch(route('lgu.directory.destinations.archive', $listing))->assertForbidden();
    expect($listing->fresh()->lst_status)->toBe('DRAFT');
    expect(SecurityLog::query()->where('usr_id', $lgu->usr_id)->where('sec_event_type', 'access_denied')->exists())->toBeTrue();

    expect(OperationLog::where('opl_entity_type', 'destination')->where('opl_entity_id', $listing->lst_id)->where('opl_action', 'create')->exists())->toBeTrue();
    expect(OperationLog::where('opl_entity_type', 'destination')->where('opl_entity_id', $listing->lst_id)->where('opl_action', 'update')->count())->toBe(1);
    expect(OperationLog::where('opl_entity_type', 'destination')->where('opl_entity_id', $listing->lst_id)->where('mun_id', $mati->mun_id)->exists())->toBeTrue();
});

test('LGU submitting an establishment to PTO records a submit operation log', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);
    $listing = Listing::query()->create([
        'lst_slug' => 'unverified-inn',
        'lst_name' => 'Unverified Inn',
        'lst_category' => 'accommodation',
        'lst_municipality' => 'City of Mati',
        'mun_id' => $mati->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => 'DRAFT',
    ]);

    test()->actingAs($lgu)->patch(route('lgu.directory.establishments.submit', $listing))->assertSessionHasNoErrors();

    $log = OperationLog::where('opl_entity_type', 'establishment')->where('opl_entity_id', $listing->lst_id)->where('opl_action', 'submit')->first();
    expect($log)->not->toBeNull();
    expect($log->lst_id)->toBe($listing->lst_id);
    expect($log->opl_new_values)->toBe(['lst_status' => 'FOR_PTO_REVIEW']);
});

test('PTO publishing an establishment records a publish operation log', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $pto = makePtoForOperationLogs();
    $listing = Listing::query()->create([
        'lst_slug' => 'for-review-inn',
        'lst_name' => 'For Review Inn',
        'lst_category' => 'accommodation',
        'lst_municipality' => 'City of Mati',
        'mun_id' => $mati->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => 'FOR_PTO_REVIEW',
    ]);

    test()->actingAs($pto)->patch(route('pto.directory.publish', $listing))->assertSessionHasNoErrors();

    $log = OperationLog::where('opl_entity_type', 'establishment')->where('opl_entity_id', $listing->lst_id)->where('opl_action', 'publish')->first();
    expect($log)->not->toBeNull();
    expect($log->opl_new_values)->toBe(['lst_status' => 'PUBLISHED']);
    expect($listing->fresh()->lst_status)->toBe('PUBLISHED');
});

test('editing an establishment account records an update operation log with masked email', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = Listing::query()->create([
        'lst_slug' => 'mati-fixture-inn-op-log',
        'lst_name' => 'Mati Fixture Inn',
        'lst_category' => 'accommodation',
        'lst_municipality' => 'City of Mati',
        'mun_id' => $mati->mun_id,
        'lst_barangay' => 'Brgy. Central',
        'lst_owner_name' => 'Juan Dela Cruz',
        'lst_contact_phone' => '09171234567',
        'lst_email' => 'old@matifixtureinn.test',
        'lst_status' => 'PUBLISHED',
    ]);
    $establishmentUser = User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'mun_id' => $mati->mun_id,
        'lst_id' => $listing->lst_id,
    ]);
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    test()->actingAs($lgu)->put(route('lgu.users.update', $establishmentUser), [
        'name' => $listing->lst_owner_name,
        'email' => 'new@matifixtureinn.test',
    ])->assertSessionHasNoErrors();

    $log = OperationLog::where('opl_entity_type', 'user')->where('opl_entity_id', $establishmentUser->usr_id)->where('opl_action', 'update')->first();
    expect($log)->not->toBeNull();
    expect($log->mun_id)->toBe($mati->mun_id);
    expect($log->lst_id)->toBe($listing->lst_id);
    expect($log->opl_new_values['usr_email'])->not->toBe('new@matifixtureinn.test');
    expect($log->opl_new_values['usr_email'])->toContain('***@');
    expect(json_encode($log->toArray()))->not->toContain('new@matifixtureinn.test');
});

test('approving a municipal report records an approve operation log with the correct municipality and acting user', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $pto = makePtoForOperationLogs();
    $submitter = User::factory()->create(['usr_role' => UserRole::Lgu, 'usr_organization_subtitle' => 'City of Mati', 'mun_id' => $mati->mun_id]);
    $report = MunicipalReport::query()->create([
        'mrp_municipality' => 'City of Mati',
        'mrp_submitted_by' => $submitter->usr_id,
        'mrp_period_start' => '2026-08-01',
        'mrp_period_end' => '2026-08-31',
        'mrp_total_arrivals' => 1000,
        'mrp_status' => MunicipalReport::STATUS_SUBMITTED,
    ]);

    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report))->assertRedirect();

    $log = OperationLog::where('opl_entity_type', 'municipal_report')->where('opl_entity_id', $report->mrp_id)->where('opl_action', 'approve')->first();
    expect($log)->not->toBeNull();
    expect($log->usr_id)->toBe($pto->usr_id);
    expect($log->mun_id)->toBe($mati->mun_id);
});

test('returning a municipal report records a return operation log and requires a reason', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $pto = makePtoForOperationLogs();
    $submitter = User::factory()->create(['usr_role' => UserRole::Lgu, 'usr_organization_subtitle' => 'City of Mati', 'mun_id' => $mati->mun_id]);
    $report = MunicipalReport::query()->create([
        'mrp_municipality' => 'City of Mati',
        'mrp_submitted_by' => $submitter->usr_id,
        'mrp_period_start' => '2026-08-01',
        'mrp_period_end' => '2026-08-31',
        'mrp_total_arrivals' => 1000,
        'mrp_status' => MunicipalReport::STATUS_SUBMITTED,
    ]);

    // No remarks — the pre-existing validation already requires it; this is
    // the same rule the spec calls "reason REQUIRED for return".
    test()->actingAs($pto)->patch(route('pto.municipalReports.return', $report), [])
        ->assertSessionHasErrors('remarks');
    expect(OperationLog::where('opl_entity_type', 'municipal_report')->where('opl_action', 'return')->exists())->toBeFalse();

    test()->actingAs($pto)->patch(route('pto.municipalReports.return', $report), ['remarks' => 'Please recheck the arrival totals.'])
        ->assertRedirect();

    $log = OperationLog::where('opl_entity_type', 'municipal_report')->where('opl_entity_id', $report->mrp_id)->where('opl_action', 'return')->first();
    expect($log)->not->toBeNull();
    expect($log->opl_reason)->toBe('Please recheck the arrival totals.');
});
