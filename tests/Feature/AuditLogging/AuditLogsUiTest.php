<?php

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\OperationLog;
use App\Models\SecurityLog;
use App\Models\User;

test('the Audit Logs sidebar item appears for PTO and links to the PTO route', function () {
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    test()->actingAs($pto)->get(route('pto.dashboard'))
        ->assertOk()
        ->assertSee('Audit Logs')
        ->assertSee(route('pto.auditLogs'));
});

test('the Audit Logs sidebar item appears for LGU and links to the LGU route', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $lgu = User::factory()->create(['role' => UserRole::Lgu, 'organization_subtitle' => 'City of Mati', 'municipality_id' => $mati->id]);

    test()->actingAs($lgu)->get(route('lgu.dashboard'))
        ->assertOk()
        ->assertSee('Audit Logs')
        ->assertSee(route('lgu.auditLogs'));
});

test('the Activity Log sidebar item appears for Establishment and links to its route', function () {
    $est = User::factory()->create(['role' => UserRole::Establishment, 'organization_name' => 'Test Establishment', 'organization_subtitle' => 'Test Coverage']);

    test()->actingAs($est)->get(route('establishment.dashboard'))
        ->assertOk()
        ->assertSee('Activity Log')
        ->assertSee(route('establishment.activityLog'));
});

test('a wrong role hitting another role\'s audit log route directly gets 403', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $lgu = User::factory()->create(['role' => UserRole::Lgu, 'municipality_id' => $mati->id]);
    $est = User::factory()->create(['role' => UserRole::Establishment]);
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    test()->actingAs($lgu)->get(route('pto.auditLogs'))->assertForbidden();
    test()->actingAs($est)->get(route('pto.auditLogs'))->assertForbidden();
    test()->actingAs($est)->get(route('lgu.auditLogs'))->assertForbidden();
    test()->actingAs($pto)->get(route('lgu.auditLogs'))->assertForbidden();
    test()->actingAs($pto)->get(route('establishment.activityLog'))->assertForbidden();
    test()->actingAs($lgu)->get(route('establishment.activityLog'))->assertForbidden();
});

test('an unauthenticated visitor is redirected to login for any audit log route', function () {
    test()->get(route('pto.auditLogs'))->assertRedirect(route('login'));
    test()->get(route('lgu.auditLogs'))->assertRedirect(route('login'));
    test()->get(route('establishment.activityLog'))->assertRedirect(route('login'));
});

test('the municipality filter renders only for PTO', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);
    $lgu = User::factory()->create(['role' => UserRole::Lgu, 'municipality_id' => $mati->id]);
    $est = User::factory()->create(['role' => UserRole::Establishment]);

    test()->actingAs($pto)->get(route('pto.auditLogs'))->assertOk()->assertSee('name="municipality_id"', false);
    test()->actingAs($lgu)->get(route('lgu.auditLogs'))->assertOk()->assertDontSee('name="municipality_id"', false);
    test()->actingAs($est)->get(route('establishment.activityLog'))->assertOk()->assertDontSee('name="municipality_id"', false);
});

test('switching tabs shows the correct columns for each log type', function () {
    // The details modal is one shared, static template present regardless
    // of tab (its "IP Address"/"Action" labels are always in the DOM, just
    // toggled by JS per row) — so these assertions target the actual <th>
    // column headers, not the page's full text.
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);
    SecurityLog::factory()->create(['user_id' => $pto->id]);
    OperationLog::factory()->create(['user_id' => $pto->id]);

    test()->actingAs($pto)->get(route('pto.auditLogs', ['tab' => 'security']))
        ->assertOk()
        ->assertSee('<th class="px-4 py-3">Event</th>', false)
        ->assertSee('<th class="px-4 py-3">IP Address</th>', false)
        ->assertDontSee('<th class="px-4 py-3">Action</th>', false);

    test()->actingAs($pto)->get(route('pto.auditLogs', ['tab' => 'operation']))
        ->assertOk()
        ->assertSee('<th class="px-4 py-3">Action</th>', false)
        ->assertSee('<th class="px-4 py-3">Record</th>', false)
        ->assertDontSee('<th class="px-4 py-3">IP Address</th>', false);
});

test('Establishment view hides the IP Address and browser columns entirely', function () {
    $est = User::factory()->create(['role' => UserRole::Establishment]);

    test()->actingAs($est)->get(route('establishment.activityLog'))
        ->assertOk()
        ->assertDontSee('IP Address')
        ->assertDontSee('Browser');
});

test('Export buttons are hidden for Establishment and shown for PTO/LGU', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);
    $lgu = User::factory()->create(['role' => UserRole::Lgu, 'municipality_id' => $mati->id]);
    $est = User::factory()->create(['role' => UserRole::Establishment]);

    test()->actingAs($pto)->get(route('pto.auditLogs'))->assertOk()->assertSee('Export Excel');
    test()->actingAs($lgu)->get(route('lgu.auditLogs'))->assertOk()->assertSee('Export Excel');
    test()->actingAs($est)->get(route('establishment.activityLog'))->assertOk()->assertDontSee('Export Excel');
});

test('Export routes reject Establishment with 403', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $est = User::factory()->create(['role' => UserRole::Establishment]);

    test()->actingAs($est)->get(route('pto.auditLogs.export', ['tab' => 'security']))->assertForbidden();
    test()->actingAs($est)->get(route('lgu.auditLogs.export', ['tab' => 'security']))->assertForbidden();
});

test('Summary cards are hidden for Establishment', function () {
    $est = User::factory()->create(['role' => UserRole::Establishment]);

    test()->actingAs($est)->get(route('establishment.activityLog'))
        ->assertOk()
        ->assertDontSee('Total events')
        ->assertDontSee('Failed logins');
});

test('the empty state shows a Reset button when no log entries match', function () {
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    test()->actingAs($pto)->get(route('pto.auditLogs', ['search' => 'no-such-entry-xyz']))
        ->assertOk()
        ->assertSee('No log entries match your filters.')
        ->assertSee('Reset filters');
});

test('log text containing HTML/script is escaped in the rendered page', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $listing = Listing::query()->create([
        'slug' => 'xss-test-listing',
        'name' => 'XSS Test Inn',
        'category' => 'accommodation',
        'municipality' => 'City of Mati',
        'municipality_id' => $mati->id,
        'barangay' => 'Poblacion',
        'status' => 'Active',
    ]);
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);
    OperationLog::factory()->create([
        'user_id' => $pto->id,
        'entity_type' => 'establishment',
        'entity_id' => $listing->id,
        'municipality_id' => $mati->id,
        'establishment_id' => $listing->id,
        'new_values' => ['name' => '<script>alert(1)</script>'],
    ]);

    $response = test()->actingAs($pto)->get(route('pto.auditLogs', ['tab' => 'operation']));

    $response->assertOk();
    $response->assertDontSee('<script>alert(1)</script>', false);
    // json_encode() escapes "/" as "\/", so the exact substring is
    // "&lt;script&gt;..." with that escaped slash — what matters is that
    // "<script>" itself never appears unescaped (checked above) and that
    // the escaped opening tag is present, proving it rendered, not silently
    // dropped.
    $response->assertSee('&lt;script&gt;alert(1)', false);
});

test('a security log with a malicious attempted_email-shaped detail is escaped', function () {
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);
    SecurityLog::factory()->create([
        'user_id' => null,
        'attempted_email' => 'hacker@example.test',
        'details' => ['reason' => '<img src=x onerror=alert(1)>'],
    ]);

    $response = test()->actingAs($pto)->get(route('pto.auditLogs'));

    $response->assertOk();
    $response->assertDontSee('<img src=x onerror=alert(1)>', false);
});
