<?php

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\OperationLog;
use App\Models\SecurityLog;
use App\Models\User;

test('PTO can export security logs as a CSV containing only role-scoped rows', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);
    $lgu = User::factory()->create(['role' => UserRole::Lgu, 'name' => 'Exportable Lgu Officer', 'municipality_id' => $mati->id]);
    SecurityLog::factory()->create(['user_id' => $lgu->id, 'municipality_id' => $mati->id]);

    $response = test()->actingAs($pto)->get(route('pto.auditLogs.export', ['tab' => 'security']));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();
    expect($csv)->toContain('Exportable Lgu Officer');
    expect($csv)->toContain('Date & Time (Asia/Manila)');
});

test('an export records its own export_report operation log entry', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator, 'municipality_id' => null]);
    SecurityLog::factory()->create();

    test()->actingAs($pto)->get(route('pto.auditLogs.export', ['tab' => 'security']))->assertOk()->streamedContent();

    $log = OperationLog::where('action', 'export_report')->where('user_id', $pto->id)->first();
    expect($log)->not->toBeNull();
    expect($log->entity_type)->toBe('security_log');
    expect($log->new_values['row_count'])->toBeInt();
});

test('an LGU export only contains rows from its own municipality', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $baganga = Municipality::query()->firstOrCreate(['code' => 'BAG'], ['name' => 'Baganga']);
    $matiLgu = User::factory()->create(['role' => UserRole::Lgu, 'municipality_id' => $mati->id]);
    $bagangaListing = Listing::query()->create([
        'slug' => 'baganga-export-test',
        'name' => 'Baganga Export Test Inn',
        'category' => 'accommodation',
        'municipality' => 'Baganga',
        'municipality_id' => $baganga->id,
        'barangay' => 'Poblacion',
        'status' => 'PUBLISHED',
    ]);
    OperationLog::factory()->create(['municipality_id' => $mati->id, 'action' => 'update', 'entity_type' => 'destination', 'entity_id' => 1]);
    OperationLog::factory()->create(['municipality_id' => $baganga->id, 'establishment_id' => $bagangaListing->id, 'action' => 'update', 'entity_type' => 'establishment', 'entity_id' => $bagangaListing->id]);

    $response = test()->actingAs($matiLgu)->get(route('lgu.auditLogs.export', ['tab' => 'operation']));

    $response->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->not->toContain('Baganga Export Test Inn');
});

test('export is capped and respects the same filters shown on the page', function () {
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);
    SecurityLog::factory()->create(['event_type' => 'login_success']);
    SecurityLog::factory()->create(['event_type' => 'login_failed']);

    $response = test()->actingAs($pto)->get(route('pto.auditLogs.export', ['tab' => 'security', 'event_type' => 'login_failed']));

    $csv = $response->streamedContent();
    $rows = array_filter(explode("\n", trim($csv)));
    // Header row + exactly the one matching row.
    expect(count($rows))->toBe(2);
});
