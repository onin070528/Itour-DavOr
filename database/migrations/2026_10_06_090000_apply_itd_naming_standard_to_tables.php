<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renames the application tables and columns to the ITD Coding
 * Standards and Guidelines (tables: plural, prefixed "tbl_"; columns:
 * singular, prefixed with a 2-3 letter table abbreviation; primary keys
 * "<prefix>_id", reused as the column name in related tables). It runs after
 * every table-creating migration so each earlier migration keeps working
 * against the original names.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Old table name => [new table name, [old column => new column]].
     *
     * Laravel-owned tables (sessions, cache, jobs, notifications,
     * password_reset_tokens, migrations) are intentionally left alone — the
     * framework reads and writes them by their default names. Columns that
     * already follow the standard are simply not listed.
     *
     * @var array<string, array{0: string, 1: array<string, string>}>
     */
    private const RENAMES = [
        'municipalities' => ['tbl_municipalities', [
            'id' => 'mun_id',
            'name' => 'mun_name',
            'code' => 'mun_code',
            'province' => 'mun_province',
            'created_at' => 'mun_created_at',
            'updated_at' => 'mun_updated_at',
        ]],
        'listings' => ['tbl_listings', [
            'id' => 'lst_id',
            'uuid' => 'lst_uuid',
            'slug' => 'lst_slug',
            'name' => 'lst_name',
            'owner_name' => 'lst_owner_name',
            'category' => 'lst_category',
            'type' => 'lst_type',
            'license_number' => 'lst_license_number',
            'accreditation_status' => 'lst_accreditation_status',
            'category_note' => 'lst_category_note',
            'municipality' => 'lst_municipality',
            'municipality_id' => 'mun_id',
            'barangay' => 'lst_barangay',
            'lat' => 'lst_lat',
            'lng' => 'lst_lng',
            'description' => 'lst_description',
            'rating' => 'lst_rating',
            'tags' => 'lst_tags',
            'image' => 'lst_image',
            'contact_office' => 'lst_contact_office',
            'contact_phone' => 'lst_contact_phone',
            'hours' => 'lst_hours',
            'email' => 'lst_email',
            'website' => 'lst_website',
            'status' => 'lst_status',
            'reporting_mode' => 'lst_reporting_mode',
            'created_at' => 'lst_created_at',
            'updated_at' => 'lst_updated_at',
        ]],
        'users' => ['tbl_users', [
            'id' => 'usr_id',
            'name' => 'usr_name',
            'email' => 'usr_email',
            'email_verified_at' => 'usr_email_verified_at',
            'password' => 'usr_password',
            'remember_token' => 'usr_remember_token',
            'role' => 'usr_role',
            'organization_name' => 'usr_organization_name',
            'organization_subtitle' => 'usr_organization_subtitle',
            'status' => 'usr_status',
            'last_login_at' => 'usr_last_login_at',
            'municipality_id' => 'mun_id',
            'establishment_id' => 'lst_id',
            'created_by' => 'usr_created_by',
            'created_at' => 'usr_created_at',
            'updated_at' => 'usr_updated_at',
        ]],
        'listing_images' => ['tbl_listing_images', [
            'id' => 'lsi_id',
            'listing_id' => 'lst_id',
            'path' => 'lsi_path',
            'caption' => 'lsi_caption',
            'is_primary' => 'lsi_is_primary',
            'sort_order' => 'lsi_sort_order',
            'created_at' => 'lsi_created_at',
            'updated_at' => 'lsi_updated_at',
        ]],
        'arrivals' => ['tbl_arrivals', [
            'id' => 'arr_id',
            'listing_id' => 'lst_id',
            'monthly_arrival_report_id' => 'mar_id',
            'source' => 'arr_source',
            'date' => 'arr_date',
            'visitor_name' => 'arr_visitor_name',
            'visitor_contact' => 'arr_visitor_contact',
            'gender' => 'arr_gender',
            'classification' => 'arr_classification',
            'visit_type' => 'arr_visit_type',
            'remarks' => 'arr_remarks',
            'party_male' => 'arr_party_male',
            'party_female' => 'arr_party_female',
            'party_adults' => 'arr_party_adults',
            'party_children' => 'arr_party_children',
            'party_seniors' => 'arr_party_seniors',
            'party_local' => 'arr_party_local',
            'party_foreign' => 'arr_party_foreign',
            'local_origin_scope' => 'arr_local_origin_scope',
            'local_origin_place' => 'arr_local_origin_place',
            'foreign_country' => 'arr_foreign_country',
            'party_size' => 'arr_party_size',
            'status' => 'arr_status',
            'created_at' => 'arr_created_at',
            'updated_at' => 'arr_updated_at',
        ]],
        'notification_preferences' => ['tbl_notification_preferences', [
            'id' => 'npf_id',
            'user_id' => 'usr_id',
            'key' => 'npf_key',
            'enabled' => 'npf_enabled',
            'created_at' => 'npf_created_at',
            'updated_at' => 'npf_updated_at',
        ]],
        'municipal_reports' => ['tbl_municipal_reports', [
            'id' => 'mrp_id',
            'municipality' => 'mrp_municipality',
            'municipality_id' => 'mun_id',
            'submitted_by' => 'mrp_submitted_by',
            'period_start' => 'mrp_period_start',
            'period_end' => 'mrp_period_end',
            'total_arrivals' => 'mrp_total_arrivals',
            'status' => 'mrp_status',
            'verification_code' => 'mrp_verification_code',
            'revision_number' => 'mrp_revision_number',
            'supersedes_id' => 'mrp_supersedes_id',
            'reviewed_by' => 'mrp_reviewed_by',
            'reviewed_at' => 'mrp_reviewed_at',
            'remarks' => 'mrp_remarks',
            'frozen_snapshot' => 'mrp_frozen_snapshot',
            'created_at' => 'mrp_created_at',
            'updated_at' => 'mrp_updated_at',
        ]],
        'monthly_arrival_reports' => ['tbl_monthly_arrival_reports', [
            'id' => 'mar_id',
            'listing_id' => 'lst_id',
            'municipality_id' => 'mun_id',
            'period_month' => 'mar_period_month',
            'submission_source' => 'mar_submission_source',
            'status' => 'mar_status',
            'party_male' => 'mar_party_male',
            'party_female' => 'mar_party_female',
            'party_adults' => 'mar_party_adults',
            'party_children' => 'mar_party_children',
            'party_seniors' => 'mar_party_seniors',
            'party_local' => 'mar_party_local',
            'party_foreign' => 'mar_party_foreign',
            'total_visitors' => 'mar_total_visitors',
            'submitted_by' => 'mar_submitted_by',
            'submitted_at' => 'mar_submitted_at',
            'verified_by' => 'mar_verified_by',
            'verified_at' => 'mar_verified_at',
            'municipal_report_id' => 'mrp_id',
            'remarks' => 'mar_remarks',
            'created_at' => 'mar_created_at',
            'updated_at' => 'mar_updated_at',
        ]],
        'security_logs' => ['tbl_security_logs', [
            'id' => 'sec_id',
            'event_type' => 'sec_event_type',
            'user_id' => 'usr_id',
            'attempted_email' => 'sec_attempted_email',
            'target_user_id' => 'sec_target_user_id',
            'municipality_id' => 'mun_id',
            'ip_address' => 'sec_ip_address',
            'user_agent' => 'sec_user_agent',
            'details' => 'sec_details',
            'created_at' => 'sec_created_at',
        ]],
        'operation_logs' => ['tbl_operation_logs', [
            'id' => 'opl_id',
            'user_id' => 'usr_id',
            'user_role' => 'opl_user_role',
            'action' => 'opl_action',
            'entity_type' => 'opl_entity_type',
            'entity_id' => 'opl_entity_id',
            'municipality_id' => 'mun_id',
            'establishment_id' => 'lst_id',
            'old_values' => 'opl_old_values',
            'new_values' => 'opl_new_values',
            'reason' => 'opl_reason',
            'ip_address' => 'opl_ip_address',
            'created_at' => 'opl_created_at',
        ]],
        'tblcategories' => ['tbl_categories', []],
        'tblhotlines' => ['tbl_hotlines', []],
        'tblannouncements' => ['tbl_announcements', []],
        'tblestablishment_images' => ['tbl_establishment_images', [
            'listing_id' => 'lst_id',
        ]],
    ];

    /**
     * Rename every table, then its columns. Foreign keys, indexes, and the
     * Postgres CHECK constraint on the users table follow the renamed
     * columns automatically, so no data is touched. Each step is skipped
     * when it has already been applied, so a database that was partly
     * renamed earlier can still migrate.
     */
    public function up(): void
    {
        foreach (self::RENAMES as $oldTable => [$newTable, $columns]) {
            if (Schema::hasTable($oldTable) && ! Schema::hasTable($newTable)) {
                Schema::rename($oldTable, $newTable);
            }

            $this->renameColumns($newTable, $columns);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::RENAMES, true) as $oldTable => [$newTable, $columns]) {
            $this->renameColumns($newTable, array_flip($columns));

            if (Schema::hasTable($newTable) && ! Schema::hasTable($oldTable)) {
                Schema::rename($newTable, $oldTable);
            }
        }
    }

    /**
     * Rename the listed columns on a table, skipping any that are already
     * renamed (or were never created).
     *
     * @param  array<string, string>  $columns  Current name => new name.
     */
    private function renameColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
            foreach ($columns as $currentColumn => $newColumn) {
                if (Schema::hasColumn($table, $currentColumn) && ! Schema::hasColumn($table, $newColumn)) {
                    $blueprint->renameColumn($currentColumn, $newColumn);
                }
            }
        });
    }
};
