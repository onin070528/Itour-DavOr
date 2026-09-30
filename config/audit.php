<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audit Log Retention
    |--------------------------------------------------------------------------
    |
    | How long security_logs and operation_logs rows are meant to be kept.
    | This is documentation only — nothing in the application reads these
    | values or deletes rows automatically. A purge job (a scheduled command
    | deleting rows older than the configured window) is a separate piece of
    | work, not yet built — confirm the exact policy before adding one.
    |
    */

    'security_log_retention_months' => env('AUDIT_SECURITY_LOG_RETENTION_MONTHS', 12),

    'operation_log_retention_months' => env('AUDIT_OPERATION_LOG_RETENTION_MONTHS', 24),

];
