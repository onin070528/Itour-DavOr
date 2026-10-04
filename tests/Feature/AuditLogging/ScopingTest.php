<?php

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
        'slug' => Str::slug($name.'-'.Str::random(6)),
        'name' => $name,
        'category' => 'accommodation',
        'municipality' => $municipality,
        'municipality_id' => $municipalityId,
        'barangay' => 'Poblacion',
        'status' => 'PUBLISHED',
    ]);
}

test('PTO sees security and operation logs from every municipality', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $baganga = makeMunicipalityFixture('Baganga', 'BAG');
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);
    $matiLgu = User::factory()->create(['role' => UserRole::Lgu, 'municipality_id' => $mati->id]);
    $bagangaLgu = User::factory()->create(['role' => UserRole::Lgu, 'municipality_id' => $baganga->id]);

    SecurityLog::factory()->create(['user_id' => $matiLgu->id, 'municipality_id' => $mati->id]);
    SecurityLog::factory()->create(['user_id' => $bagangaLgu->id, 'municipality_id' => $baganga->id]);
    OperationLog::factory()->create(['user_id' => $matiLgu->id, 'municipality_id' => $mati->id]);
    OperationLog::factory()->create(['user_id' => $bagangaLgu->id, 'municipality_id' => $baganga->id]);

    expect(AuditLogQuery::securityLogs($pto, [])->total())->toBe(2);
    expect(AuditLogQuery::operationLogs($pto, [])->total())->toBe(2);
});

test('LGU cannot see another municipality\'s operation logs, even by tampering with municipality_id', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $baganga = makeMunicipalityFixture('Baganga', 'BAG');
    $matiLgu = User::factory()->create(['role' => UserRole::Lgu, 'organization_subtitle' => 'City of Mati', 'municipality_id' => $mati->id]);
    $bagangaLgu = User::factory()->create(['role' => UserRole::Lgu, 'municipality_id' => $baganga->id]);

    OperationLog::factory()->create(['user_id' => $matiLgu->id, 'municipality_id' => $mati->id]);
    OperationLog::factory()->create(['user_id' => $bagangaLgu->id, 'municipality_id' => $baganga->id]);

    $page = AuditLogQuery::operationLogs($matiLgu, []);
    expect($page->total())->toBe(1);
    expect($page->first()->municipality_id)->toBe($mati->id);

    // Role scoping never reads the request's municipality_id — even if the
    // viewer is a PTO-only-shaped filter, passing another municipality's ID
    // must not widen an LGU's results.
    $tampered = AuditLogQuery::operationLogs($matiLgu, ['municipality_id' => $baganga->id]);
    expect($tampered->total())->toBe(1);
    expect($tampered->first()->municipality_id)->toBe($mati->id);

    // Also verified at the HTTP layer via the real route/query string.
    test()->actingAs($matiLgu)->get(route('lgu.auditLogs', ['tab' => 'operation', 'municipality_id' => $baganga->id]))
        ->assertOk()
        ->assertDontSee($bagangaLgu->name);
});

test('LGU security logs include its own account and establishment_owner accounts in its municipality only', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $baganga = makeMunicipalityFixture('Baganga', 'BAG');
    $matiLgu = User::factory()->create(['role' => UserRole::Lgu, 'municipality_id' => $mati->id]);
    $matiEstablishment = User::factory()->create(['role' => UserRole::Establishment, 'municipality_id' => $mati->id]);
    $bagangaLgu = User::factory()->create(['role' => UserRole::Lgu, 'municipality_id' => $baganga->id]);
    $bagangaEstablishment = User::factory()->create(['role' => UserRole::Establishment, 'municipality_id' => $baganga->id]);
    $otherMatiLgu = User::factory()->create(['role' => UserRole::Lgu, 'municipality_id' => $mati->id]);

    $ownLog = SecurityLog::factory()->create(['user_id' => $matiLgu->id]);
    $ownEstablishmentLog = SecurityLog::factory()->create(['user_id' => $matiEstablishment->id]);
    $otherLguLog = SecurityLog::factory()->create(['user_id' => $otherMatiLgu->id]);
    $bagangaLguLog = SecurityLog::factory()->create(['user_id' => $bagangaLgu->id]);
    $bagangaEstablishmentLog = SecurityLog::factory()->create(['user_id' => $bagangaEstablishment->id]);

    $visibleIds = AuditLogQuery::securityLogs($matiLgu, [])->pluck('id');

    expect($visibleIds)->toContain($ownLog->id, $ownEstablishmentLog->id);
    expect($visibleIds)->not->toContain($otherLguLog->id, $bagangaLguLog->id, $bagangaEstablishmentLog->id);
});

test('Establishment sees only its own security events and its own establishment\'s operation logs', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $ownListing = makeAuditLogEstablishmentListing($mati->id, 'City of Mati', 'Own Inn');
    $otherListing = makeAuditLogEstablishmentListing($mati->id, 'City of Mati', 'Other Inn');
    $owner = User::factory()->create(['role' => UserRole::Establishment, 'municipality_id' => $mati->id, 'establishment_id' => $ownListing->id]);
    $otherOwner = User::factory()->create(['role' => UserRole::Establishment, 'municipality_id' => $mati->id, 'establishment_id' => $otherListing->id]);
    $lgu = User::factory()->create(['role' => UserRole::Lgu, 'municipality_id' => $mati->id]);

    $ownSecurityLog = SecurityLog::factory()->create(['user_id' => $owner->id]);
    $otherOwnerSecurityLog = SecurityLog::factory()->create(['user_id' => $otherOwner->id]);
    $lguSecurityLog = SecurityLog::factory()->create(['user_id' => $lgu->id]);

    $ownOperationLog = OperationLog::factory()->create(['establishment_id' => $ownListing->id, 'municipality_id' => $mati->id]);
    $otherOperationLog = OperationLog::factory()->create(['establishment_id' => $otherListing->id, 'municipality_id' => $mati->id]);

    $visibleSecurityIds = AuditLogQuery::securityLogs($owner, [])->pluck('id');
    expect($visibleSecurityIds)->toContain($ownSecurityLog->id);
    expect($visibleSecurityIds)->not->toContain($otherOwnerSecurityLog->id, $lguSecurityLog->id);

    $visibleOperationIds = AuditLogQuery::operationLogs($owner, [])->pluck('id');
    expect($visibleOperationIds)->toContain($ownOperationLog->id);
    expect($visibleOperationIds)->not->toContain($otherOperationLog->id);
});

test('Establishment security log results never carry ip_address or user_agent, at the query level', function () {
    $owner = User::factory()->create(['role' => UserRole::Establishment]);
    SecurityLog::factory()->create(['user_id' => $owner->id, 'ip_address' => '203.0.113.7', 'user_agent' => 'ExampleBrowser/1.0']);

    $row = AuditLogQuery::securityLogs($owner, [])->first();

    expect($row->getAttributes())->not->toHaveKey('ip_address');
    expect($row->getAttributes())->not->toHaveKey('user_agent');
});

test('Establishment activity log page never renders the ip address or user agent in the response', function () {
    $owner = User::factory()->create(['role' => UserRole::Establishment]);
    SecurityLog::factory()->create(['user_id' => $owner->id, 'ip_address' => '203.0.113.7', 'user_agent' => 'ExampleBrowser/1.0 UniqueMarkerXYZ']);

    test()->actingAs($owner)->get(route('establishment.activityLog'))
        ->assertOk()
        ->assertDontSee('203.0.113.7')
        ->assertDontSee('UniqueMarkerXYZ');
});
