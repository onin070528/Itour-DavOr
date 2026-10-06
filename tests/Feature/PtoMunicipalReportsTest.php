<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — pto municipal reports.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Municipality;
use App\Models\MunicipalReport;
use App\Models\User;

function actingAsPtoAdministrator(): User
{
    return User::factory()->create([
        'usr_role' => UserRole::PtoAdministrator,
        'usr_organization_name' => 'Provincial Tourism Office',
        'usr_organization_subtitle' => 'Province of Davao Oriental',
    ]);
}

function makeMunicipalReport(array $overrides = []): MunicipalReport
{
    $municipality = Municipality::query()->firstOrCreate(['mun_code' => 'PTOMATI'], ['mun_name' => 'City of Mati']);
    $submitter = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_name' => 'City of Mati Tourism Office',
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $municipality->mun_id,
    ]);

    return MunicipalReport::query()->create(array_merge([
        'mrp_municipality' => 'City of Mati',
        'mun_id' => $municipality->mun_id,
        'mrp_submitted_by' => $submitter->usr_id,
        'mrp_period_start' => now()->startOfMonth()->toDateString(),
        'mrp_period_end' => now()->endOfMonth()->toDateString(),
        'mrp_total_arrivals' => 1000,
        'mrp_status' => MunicipalReport::STATUS_SUBMITTED,
    ], $overrides));
}

test('the municipal reports list page renders for a PTO administrator', function () {
    makeMunicipalReport();

    $response = test()->actingAs(actingAsPtoAdministrator())->get(route('pto.municipalReports.index'));

    $response->assertOk();
    $response->assertSee('City of Mati');
});

test('the municipal report detail page renders for a PTO administrator', function () {
    $report = makeMunicipalReport();

    $response = test()->actingAs(actingAsPtoAdministrator())->get(route('pto.municipalReports.show', $report));

    $response->assertOk();
    $response->assertSee('City of Mati');
});

test('a PTO administrator can approve a submitted municipal report', function () {
    $report = makeMunicipalReport(['mrp_status' => MunicipalReport::STATUS_SUBMITTED]);
    $pto = actingAsPtoAdministrator();

    $response = test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report));

    $response->assertRedirect();
    $report->refresh();
    expect($report->mrp_status)->toBe(MunicipalReport::STATUS_APPROVED);
    expect($report->mrp_reviewed_by)->toBe($pto->usr_id);
    expect($report->mrp_reviewed_at)->not->toBeNull();
});

test('a PTO administrator can return a municipal report for revision with remarks', function () {
    $report = makeMunicipalReport(['mrp_status' => MunicipalReport::STATUS_SUBMITTED]);
    $pto = actingAsPtoAdministrator();

    $response = test()->actingAs($pto)->patch(route('pto.municipalReports.return', $report), [
        'remarks' => 'Please recheck the August totals.',
    ]);

    $response->assertRedirect();
    $report->refresh();
    expect($report->mrp_status)->toBe(MunicipalReport::STATUS_RETURNED);
    expect($report->mrp_reviewed_by)->toBe($pto->usr_id);
    expect($report->mrp_reviewed_at)->not->toBeNull();
    expect($report->mrp_remarks)->toBe('Please recheck the August totals.');
});

test('returning a municipal report requires remarks', function () {
    $report = makeMunicipalReport(['mrp_status' => MunicipalReport::STATUS_SUBMITTED]);

    $response = test()->actingAs(actingAsPtoAdministrator())->patch(route('pto.municipalReports.return', $report), []);

    $response->assertSessionHasErrors('remarks');
    expect($report->fresh()->mrp_status)->toBe(MunicipalReport::STATUS_SUBMITTED);
});

test('an approved municipal report cannot be approved or returned again', function () {
    $report = makeMunicipalReport(['mrp_status' => MunicipalReport::STATUS_APPROVED]);
    $pto = actingAsPtoAdministrator();

    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report))->assertForbidden();
    test()->actingAs($pto)->patch(route('pto.municipalReports.return', $report), ['remarks' => 'x'])->assertForbidden();
});

test('the LGU Submissions page uses the Not Submitted / For Review / For Clarification / Verified terminology', function () {
    makeMunicipalReport(['mrp_status' => MunicipalReport::STATUS_SUBMITTED]);

    $response = test()->actingAs(actingAsPtoAdministrator())->get(route('pto.municipalReports.index'));

    $response->assertOk();
    $response->assertSee('LGU Submissions');
    $response->assertSee('For Review');
});

test('the LGU Submissions page lists every municipality for the selected period, flagging the ones with no report as Not Submitted', function () {
    Municipality::query()->create(['mun_name' => 'City of Mati', 'mun_code' => 'PTOMATI']);
    Municipality::query()->create(['mun_name' => 'Baganga', 'mun_code' => 'PTOBAG']);

    $response = test()->actingAs(actingAsPtoAdministrator())->get(route('pto.municipalReports.index'));

    $response->assertOk();
    $response->assertSee('City of Mati');
    $response->assertSee('Baganga');
    $response->assertSee('Not Submitted');
});

test('a non-PTO user cannot access municipal reports routes', function () {
    $report = makeMunicipalReport();

    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_name' => 'City of Mati Tourism Office',
        'usr_organization_subtitle' => 'City of Mati',
    ]);
    $establishment = User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_name' => 'Botanika Nature Resort',
        'usr_organization_subtitle' => 'Brgy. Dahican, City of Mati',
    ]);

    test()->actingAs($lgu)->get(route('pto.municipalReports.index'))->assertForbidden();
    test()->actingAs($lgu)->get(route('pto.municipalReports.show', $report))->assertForbidden();
    test()->actingAs($establishment)->get(route('pto.municipalReports.index'))->assertForbidden();
});
