<?php

use App\Models\OperationLog;
use App\Models\SecurityLog;

test('a security log row cannot be updated', function () {
    $log = SecurityLog::factory()->create();

    expect(fn () => $log->update(['event_type' => 'logout']))
        ->toThrow(LogicException::class);
});

test('a security log row cannot be deleted', function () {
    $log = SecurityLog::factory()->create();

    expect(fn () => $log->delete())->toThrow(LogicException::class);
    expect(SecurityLog::query()->whereKey($log->id)->exists())->toBeTrue();
});

test('an operation log row cannot be updated', function () {
    $log = OperationLog::factory()->create();

    expect(fn () => $log->update(['action' => 'delete']))
        ->toThrow(LogicException::class);
});

test('an operation log row cannot be deleted', function () {
    $log = OperationLog::factory()->create();

    expect(fn () => $log->delete())->toThrow(LogicException::class);
    expect(OperationLog::query()->whereKey($log->id)->exists())->toBeTrue();
});

test('security and operation logs cast their JSON columns to arrays', function () {
    $securityLog = SecurityLog::factory()->create(['details' => ['note' => 'test']]);
    $operationLog = OperationLog::factory()->create([
        'old_values' => ['status' => 'Active'],
        'new_values' => ['status' => 'Inactive'],
    ]);

    expect($securityLog->fresh()->details)->toBe(['note' => 'test']);
    expect($operationLog->fresh()->old_values)->toBe(['status' => 'Active']);
    expect($operationLog->fresh()->new_values)->toBe(['status' => 'Inactive']);
});
