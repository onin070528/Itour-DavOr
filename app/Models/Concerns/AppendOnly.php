<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Makes a log model reject update()/delete() at the Eloquent layer.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models\Concerns;

use LogicException;

/**
 * Blocks the two Eloquent instance methods an app controller/service would
 * normally reach for. This is defense-in-depth, not the real guarantee — a
 * query-builder call like `SecurityLog::query()->where(...)->update(...)`
 * bypasses model instance methods entirely and isn't caught here. The actual
 * guarantee is the database grant (INSERT + SELECT only, documented in
 * database/GRANTS.md, not applied automatically).
 */
trait AppendOnly
{
    public function update(array $arrAttributes = [], array $arrOptions = []): bool
    {
        throw new LogicException(static::class.' rows are append-only and cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new LogicException(static::class.' rows are append-only and cannot be deleted.');
    }
}
