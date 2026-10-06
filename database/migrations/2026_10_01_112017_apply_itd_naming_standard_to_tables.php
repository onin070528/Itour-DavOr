<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renames the application tables and columns to the ITD Coding
 * Standards and Guidelines (tables: plural, prefixed "tbl_"; columns:
 * singular, prefixed with a 2-3 letter table abbreviation; primary keys
 * "<prefix>_id", reused as the column name in related tables).
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
     * Laravel-owned tables (sessions, cache, jobs, password_reset_tokens,
     * migrations) are intentionally left alone — the framework reads and
     * writes them by their default names.
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
            'slug' => 'lst_slug',
            'name' => 'lst_name',
            'owner_name' => 'lst_owner_name',
            'category' => 'lst_category',
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
            'submitted_by' => 'mrp_submitted_by',
            'period_start' => 'mrp_period_start',
            'period_end' => 'mrp_period_end',
            'total_arrivals' => 'mrp_total_arrivals',
            'status' => 'mrp_status',
            'reviewed_by' => 'mrp_reviewed_by',
            'reviewed_at' => 'mrp_reviewed_at',
            'remarks' => 'mrp_remarks',
            'created_at' => 'mrp_created_at',
            'updated_at' => 'mrp_updated_at',
        ]],
        'audit_logs' => ['tbl_audit_logs', [
            'id' => 'aud_id',
            'user_id' => 'usr_id',
            'action' => 'aud_action',
            'target_type' => 'aud_target_type',
            'target_id' => 'aud_target_id',
            'ip_address' => 'aud_ip_address',
            'user_agent' => 'aud_user_agent',
            'metadata' => 'aud_metadata',
            'created_at' => 'aud_created_at',
        ]],
    ];

    /**
     * Rename every table, then its columns. Foreign keys, indexes, and the
     * Postgres CHECK constraint on the users table follow the renamed
     * columns automatically, so no data is touched.
     */
    public function up(): void
    {
        foreach (self::RENAMES as $oldTable => [$newTable, $columns]) {
            Schema::rename($oldTable, $newTable);

            Schema::table($newTable, function (Blueprint $table) use ($columns) {
                foreach ($columns as $oldColumn => $newColumn) {
                    $table->renameColumn($oldColumn, $newColumn);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::RENAMES, true) as $oldTable => [$newTable, $columns]) {
            Schema::table($newTable, function (Blueprint $table) use ($columns) {
                foreach ($columns as $oldColumn => $newColumn) {
                    $table->renameColumn($newColumn, $oldColumn);
                }
            });

            Schema::rename($newTable, $oldTable);
        }
    }
};
