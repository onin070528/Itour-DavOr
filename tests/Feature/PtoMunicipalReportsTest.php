<?php

use App\Enums\UserRole;
use App\Models\MunicipalReport;
use App\Models\User;

function actingAsPtoAdministrator(): User
{
    return User::factory()->create([
        'role' => UserRole::PtoAdministrator,
        'organization_name' => 'Provincial Tourism Office',
        'organization_subtitle' => 'Province of Davao Oriental',
    ]);
}

function makeMunicipalReport(array $overrides = []): MunicipalReport
{
    $submitter = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_name' => 'City of Mati Tourism Office',
        'organization_subtitle' => 'City of Mati',
    ]);

    return MunicipalReport::query()->create(array_merge([
        'municipality' => 'City of Mati',
        'submitted_by' => $submitter->id,
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
        'total_arrivals' => 1000,
        'status' => MunicipalReport::STATUS_SUBMITTED,
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
    $report = makeMunicipalReport(['status' => MunicipalReport::STATUS_SUBMITTED]);
    $pto = actingAsPtoAdministrator();

    $response = test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report));

    $response->assertRedirect();
    $report->refresh();
    expect($report->status)->toBe(MunicipalReport::STATUS_APPROVED);
    expect($report->reviewed_by)->toBe($pto->id);
    expect($report->reviewed_at)->not->toBeNull();
});

test('a PTO administrator can return a municipal report for revision with remarks', function () {
    $report = makeMunicipalReport(['status' => MunicipalReport::STATUS_SUBMITTED]);
    $pto = actingAsPtoAdministrator();

    $response = test()->actingAs($pto)->patch(route('pto.municipalReports.return', $report), [
        'remarks' => 'Please recheck the August totals.',
    ]);

    $response->assertRedirect();
    $report->refresh();
    expect($report->status)->toBe(MunicipalReport::STATUS_RETURNED);
    expect($report->reviewed_by)->toBe($pto->id);
    expect($report->reviewed_at)->not->toBeNull();
    expect($report->remarks)->toBe('Please recheck the August totals.');
});

test('returning a municipal report requires remarks', function () {
    $report = makeMunicipalReport(['status' => MunicipalReport::STATUS_SUBMITTED]);

    $response = test()->actingAs(actingAsPtoAdministrator())->patch(route('pto.municipalReports.return', $report), []);

    $response->assertSessionHasErrors('remarks');
    expect($report->fresh()->status)->toBe(MunicipalReport::STATUS_SUBMITTED);
});

test('an approved municipal report cannot be approved or returned again', function () {
    $report = makeMunicipalReport(['status' => MunicipalReport::STATUS_APPROVED]);
    $pto = actingAsPtoAdministrator();

    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report))->assertForbidden();
    test()->actingAs($pto)->patch(route('pto.municipalReports.return', $report), ['remarks' => 'x'])->assertForbidden();
});

test('a non-PTO user cannot access municipal reports routes', function () {
    $report = makeMunicipalReport();

    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_name' => 'City of Mati Tourism Office',
        'organization_subtitle' => 'City of Mati',
    ]);
    $establishment = User::factory()->create([
        'role' => UserRole::Establishment,
        'organization_name' => 'Botanika Nature Resort',
        'organization_subtitle' => 'Brgy. Dahican, City of Mati',
    ]);

    test()->actingAs($lgu)->get(route('pto.municipalReports.index'))->assertForbidden();
    test()->actingAs($lgu)->get(route('pto.municipalReports.show', $report))->assertForbidden();
    test()->actingAs($establishment)->get(route('pto.municipalReports.index'))->assertForbidden();
});
