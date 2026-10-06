<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — append only.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Models\OperationLog;
use App\Models\SecurityLog;

test('a security log row cannot be updated', function () {
    $log = SecurityLog::factory()->create();

    expect(fn () => $log->update(['sec_event_type' => 'logout']))
        ->toThrow(LogicException::class);
});

test('a security log row cannot be deleted', function () {
    $log = SecurityLog::factory()->create();

    expect(fn () => $log->delete())->toThrow(LogicException::class);
    expect(SecurityLog::query()->whereKey($log->sec_id)->exists())->toBeTrue();
});

test('an operation log row cannot be updated', function () {
    $log = OperationLog::factory()->create();

    expect(fn () => $log->update(['opl_action' => 'delete']))
        ->toThrow(LogicException::class);
});

test('an operation log row cannot be deleted', function () {
    $log = OperationLog::factory()->create();

    expect(fn () => $log->delete())->toThrow(LogicException::class);
    expect(OperationLog::query()->whereKey($log->opl_id)->exists())->toBeTrue();
});

test('security and operation logs cast their JSON columns to arrays', function () {
    $securityLog = SecurityLog::factory()->create(['sec_details' => ['note' => 'test']]);
    $operationLog = OperationLog::factory()->create([
        'opl_old_values' => ['status' => 'Active'],
        'opl_new_values' => ['status' => 'Inactive'],
    ]);

    expect($securityLog->fresh()->sec_details)->toBe(['note' => 'test']);
    expect($operationLog->fresh()->opl_old_values)->toBe(['status' => 'Active']);
    expect($operationLog->fresh()->opl_new_values)->toBe(['status' => 'Inactive']);
});
