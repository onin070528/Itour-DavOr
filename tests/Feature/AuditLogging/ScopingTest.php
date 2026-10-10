<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — scoping.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\OperationLog;
use App\Models\SecurityLog;
use App\Models\User;
use App\Support\AuditLogQuery;
use Illuminate\Support\Str;

function makeAuditLogEstablishmentListing(int $municipalityId, string $municipality, string $name): Listing
{
    return Listing::query()->create([
        'lst_slug' => Str::slug($name.'-'.Str::random(6)),
        'lst_name' => $name,
        'lst_category' => 'accommodation',
        'lst_municipality' => $municipality,
        'mun_id' => $municipalityId,
        'lst_barangay' => 'Poblacion',
        'lst_status' => 'PUBLISHED',
    ]);
}

test('PTO sees security and operation logs from every municipality', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $baganga = makeMunicipalityFixture('Baganga', 'BAG');
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $matiLgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);
    $bagangaLgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $baganga->mun_id]);

    SecurityLog::factory()->create(['usr_id' => $matiLgu->usr_id, 'mun_id' => $mati->mun_id]);
    SecurityLog::factory()->create(['usr_id' => $bagangaLgu->usr_id, 'mun_id' => $baganga->mun_id]);
    OperationLog::factory()->create(['usr_id' => $matiLgu->usr_id, 'mun_id' => $mati->mun_id]);
    OperationLog::factory()->create(['usr_id' => $bagangaLgu->usr_id, 'mun_id' => $baganga->mun_id]);

    expect(AuditLogQuery::securityLogs($pto, [])->total())->toBe(2);
    expect(AuditLogQuery::operationLogs($pto, [])->total())->toBe(2);
});

test('LGU cannot see another municipality\'s operation logs, even by tampering with municipality_id', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $baganga = makeMunicipalityFixture('Baganga', 'BAG');
    $matiLgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'usr_organization_subtitle' => 'City of Mati', 'mun_id' => $mati->mun_id]);
    $bagangaLgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $baganga->mun_id]);

    OperationLog::factory()->create(['usr_id' => $matiLgu->usr_id, 'mun_id' => $mati->mun_id]);
    OperationLog::factory()->create(['usr_id' => $bagangaLgu->usr_id, 'mun_id' => $baganga->mun_id]);

    $page = AuditLogQuery::operationLogs($matiLgu, []);
    expect($page->total())->toBe(1);
    expect($page->first()->mun_id)->toBe($mati->mun_id);

    // Role scoping never reads the request's municipality_id — even if the
    // viewer is a PTO-only-shaped filter, passing another municipality's ID
    // must not widen an LGU's results.
    $tampered = AuditLogQuery::operationLogs($matiLgu, ['municipality_id' => $baganga->mun_id]);
    expect($tampered->total())->toBe(1);
    expect($tampered->first()->mun_id)->toBe($mati->mun_id);

    // Also verified at the HTTP layer via the real route/query string.
    test()->actingAs($matiLgu)->get(route('lgu.auditLogs', ['tab' => 'operation', 'municipality_id' => $baganga->mun_id]))
        ->assertOk()
        ->assertDontSee($bagangaLgu->usr_name);
});

test('LGU security logs include its own account and establishment_owner accounts in its municipality only', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $baganga = makeMunicipalityFixture('Baganga', 'BAG');
    $matiLgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);
    $matiEstablishment = User::factory()->create(['usr_role' => UserRole::Establishment, 'mun_id' => $mati->mun_id]);
    $bagangaLgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $baganga->mun_id]);
    $bagangaEstablishment = User::factory()->create(['usr_role' => UserRole::Establishment, 'mun_id' => $baganga->mun_id]);
    // Inactive: only one active LGU account per municipality is allowed.
    $otherMatiLgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id, 'usr_status' => 'Inactive']);

    $ownLog = SecurityLog::factory()->create(['usr_id' => $matiLgu->usr_id]);
    $ownEstablishmentLog = SecurityLog::factory()->create(['usr_id' => $matiEstablishment->usr_id]);
    $otherLguLog = SecurityLog::factory()->create(['usr_id' => $otherMatiLgu->usr_id]);
    $bagangaLguLog = SecurityLog::factory()->create(['usr_id' => $bagangaLgu->usr_id]);
    $bagangaEstablishmentLog = SecurityLog::factory()->create(['usr_id' => $bagangaEstablishment->usr_id]);

    $visibleIds = AuditLogQuery::securityLogs($matiLgu, [])->pluck('sec_id');

    expect($visibleIds)->toContain($ownLog->sec_id, $ownEstablishmentLog->sec_id);
    expect($visibleIds)->not->toContain($otherLguLog->sec_id, $bagangaLguLog->sec_id, $bagangaEstablishmentLog->sec_id);
});

test('Establishment sees only its own security events and its own establishment\'s operation logs', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $ownListing = makeAuditLogEstablishmentListing($mati->mun_id, 'City of Mati', 'Own Inn');
    $otherListing = makeAuditLogEstablishmentListing($mati->mun_id, 'City of Mati', 'Other Inn');
    $owner = User::factory()->create(['usr_role' => UserRole::Establishment, 'mun_id' => $mati->mun_id, 'lst_id' => $ownListing->lst_id]);
    $otherOwner = User::factory()->create(['usr_role' => UserRole::Establishment, 'mun_id' => $mati->mun_id, 'lst_id' => $otherListing->lst_id]);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    $ownSecurityLog = SecurityLog::factory()->create(['usr_id' => $owner->usr_id]);
    $otherOwnerSecurityLog = SecurityLog::factory()->create(['usr_id' => $otherOwner->usr_id]);
    $lguSecurityLog = SecurityLog::factory()->create(['usr_id' => $lgu->usr_id]);

    $ownOperationLog = OperationLog::factory()->create(['lst_id' => $ownListing->lst_id, 'mun_id' => $mati->mun_id]);
    $otherOperationLog = OperationLog::factory()->create(['lst_id' => $otherListing->lst_id, 'mun_id' => $mati->mun_id]);

    $visibleSecurityIds = AuditLogQuery::securityLogs($owner, [])->pluck('sec_id');
    expect($visibleSecurityIds)->toContain($ownSecurityLog->sec_id);
    expect($visibleSecurityIds)->not->toContain($otherOwnerSecurityLog->sec_id, $lguSecurityLog->sec_id);

    $visibleOperationIds = AuditLogQuery::operationLogs($owner, [])->pluck('opl_id');
    expect($visibleOperationIds)->toContain($ownOperationLog->opl_id);
    expect($visibleOperationIds)->not->toContain($otherOperationLog->opl_id);
});

test('Establishment security log results never carry ip_address or user_agent, at the query level', function () {
    $owner = User::factory()->create(['usr_role' => UserRole::Establishment]);
    SecurityLog::factory()->create(['usr_id' => $owner->usr_id, 'sec_ip_address' => '203.0.113.7', 'sec_user_agent' => 'ExampleBrowser/1.0']);

    $row = AuditLogQuery::securityLogs($owner, [])->first();

    expect($row->getAttributes())->not->toHaveKey('ip_address');
    expect($row->getAttributes())->not->toHaveKey('user_agent');
});

test('Establishment activity log page never renders the ip address or user agent in the response', function () {
    $owner = User::factory()->create(['usr_role' => UserRole::Establishment]);
    SecurityLog::factory()->create(['usr_id' => $owner->usr_id, 'sec_ip_address' => '203.0.113.7', 'sec_user_agent' => 'ExampleBrowser/1.0 UniqueMarkerXYZ']);

    test()->actingAs($owner)->get(route('establishment.activityLog'))
        ->assertOk()
        ->assertDontSee('203.0.113.7')
        ->assertDontSee('UniqueMarkerXYZ');
});
