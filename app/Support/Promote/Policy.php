<?php

namespace App\Support\Promote;

/**
 * What `takeone:promote` copies from the staging database into production, and
 * how it recognises a record production already has.
 *
 * The two databases grew apart: the same id is a DIFFERENT row on each side
 * (member #500 on staging is not member #500 in production), so nothing is
 * copied by id. Every row copied gets a new id, and every reference to it —
 * declared foreign keys, the undeclared ones listed here, "*_by" columns,
 * polymorphic pairs and ids inside JSON — is rewritten to that new id.
 *
 * Nothing in production is ever updated or deleted: a staging row that
 * production already has (same uuid, same person, same club…) is LINKED to
 * it, and only the rows production lacks are added beside it.
 */
final class Policy
{
    /**
     * Never copied, with the reason. Infrastructure, per-server state, device
     * pairings to staging's own address, and housekeeping about staging rows.
     *
     * @var array<string, string>
     */
    public const SKIP = [
        'migrations' => 'schema history — each server keeps its own',
        'cache' => 'cache', 'cache_locks' => 'cache',
        'sessions' => 'logins on staging',
        'jobs' => 'queue', 'job_batches' => 'queue', 'failed_jobs' => 'queue',
        'password_reset_tokens' => 'one-time tokens',
        'personal_access_tokens' => 'API tokens for staging',
        'pending_screens' => 'device pairing codes on staging',
        'bjj_screen_devices' => 'hall screens paired to staging',
        'karate_court_displays' => 'hall screens paired to staging',
        'screen_devices' => 'hall screens paired to staging',
        'platform_settings' => 'production keeps its own settings',
        'ai_providers' => 'production keeps its own AI keys',
        'media_vaults' => 'storage locations are per server',
        'account_merges' => 'records of merges done on staging',
        'duplicate_findings' => 'duplicate watch findings about staging rows',
        'member_duplicate_dismissals' => 'duplicate watch decisions about staging rows',
        'party_shadow_disagreements' => 'diagnostics',
        'play_timeline_points' => 'orphaned (video platform disconnected)',
        'play_timeline_rounds' => 'orphaned (video platform disconnected)',
        'event_rehearsals' => 'dry-run snapshots of staging events',
        'user_section_views' => 'per-device "seen" markers',
        'telescope_entries' => 'debugging', 'telescope_entries_tags' => 'debugging', 'telescope_monitoring' => 'debugging',
        'pulse_entries' => 'monitoring', 'pulse_aggregates' => 'monitoring', 'pulse_values' => 'monitoring',
    ];

    /**
     * References the schema does not declare: table.column => target table.
     * A column ending in _id that is neither declared nor listed here (nor in
     * VALUES) is reported as UNKNOWN, and the report is not clean until it is
     * classified.
     *
     * @var array<string, string>
     */
    public const REFS = [
        'activity_catalog.source_tenant_id' => 'tenants',
        'bjj_mat_states.event_id' => 'club_events',
        'bjj_mat_states.match_id' => 'event_matches',
        'bjj_match_events.event_id' => 'club_events',
        'bjj_match_events.match_id' => 'event_matches',
        'bjj_match_events.reverses_id' => 'bjj_match_events',
        'bjj_match_events.operator_id' => 'users',
        'club_activity_equipment.club_product_id' => 'club_products',
        'club_affiliations.tenant_id' => 'tenants',
        'club_affiliations.verified_by_tenant_id' => 'tenants',
        'club_affiliations.verified_by_user_id' => 'users',
        'club_transactions.recurring_expense_id' => 'club_recurring_expenses',
        'devboard_cards.notebook_id' => 'devboard_notebooks',
        'devboard_folders.notebook_id' => 'devboard_notebooks',
        'devboard_labels.notebook_id' => 'devboard_notebooks',
        'devboard_lanes.notebook_id' => 'devboard_notebooks',
        'event_budget_chats.user_id' => 'users',
        'event_budgets.approved_scenario_id' => 'event_budget_scenarios',
        'event_budgets.copied_from_event_id' => 'club_events',
        'event_cameras.live_stream_id' => 'live_streams',
        'event_clubs.tenant_id' => 'tenants',
        'event_clubs.invited_user_id' => 'users',
        'event_clubs.promoted_tenant_id' => 'tenants',
        'event_exhibition_bouts.linked_match_id' => 'event_matches',
        'event_feature_requests.federation_event_id' => 'federation_events',
        'event_matches.a_side_id' => 'club_event_registrations',
        'event_matches.b_side_id' => 'club_event_registrations',
        'event_officials.representing_tenant_id' => 'tenants',
        'event_officials.event_club_id' => 'event_clubs',
        'event_public_entries.event_id' => 'club_events',
        'event_public_entries.user_id' => 'users',
        'event_public_entries.representing_tenant_id' => 'tenants',
        'event_public_entries.registration_id' => 'club_event_registrations',
        'event_run_slots.group_id' => 'event_run_slots',
        'event_visits.event_id' => 'club_events',
        'event_visits.user_id' => 'users',
        'event_withdrawal_requests.registration_id' => 'club_event_registrations',
        'federation_events.club_event_id' => 'club_events',
        'federation_test_records.federation_event_id' => 'federation_events',
        'member_equipment.club_product_id' => 'club_products',
        'member_equipment.club_product_variant_id' => 'club_product_variants',
        'member_work_history.verified_by_tenant_id' => 'tenants',
        'member_work_history.verified_by_user_id' => 'users',
        'order_items.club_product_variant_id' => 'club_product_variants',
        'party_motion_votes.motion_id' => 'party_motions',
        'party_motion_votes.user_id' => 'users',
        'performance_results.source_category_id' => 'event_categories',
        'rank_promotions.grading_id' => 'gradings',
        'tournament_events.club_event_id' => 'club_events',
        'user_notifications.actor_user_id' => 'users',
    ];

    /**
     * Columns named like a reference that hold a VALUE: copied as they are.
     *
     * @var array<int, string>
     */
    public const VALUES = [
        'club_member_subscriptions.registration_group_id', // a shared string key
        'event_camera_clips.play_video_id',                 // the disconnected video platform's id
        'event_recordings.play_video_id',
        'federation_athletes.takeone_id',                   // "BHR-00001", the athlete's public number
        'federation_athletes.national_id',                  // an ID-card number (encrypted)
        'live_streams.public_id',
        'member_certifications.credential_id',              // a licence number
        'event_matches.winner', 'event_exhibition_bouts.winner', // 'a' / 'b'
    ];

    /**
     * A reference whose target depends on another column of the same row:
     * table.column => [discriminator column, [value => target table]].
     *
     * @var array<string, array{0: string, 1: array<string, string>}>
     */
    public const CONDITIONAL = [
        'certificates.source_id' => ['source', [
            'event_entry' => 'club_event_registrations',
            'event_podium' => 'club_event_registrations',
            'event_official' => 'club_events',
            'event_club_thanks' => 'club_events',
        ]],
    ];

    /**
     * Ids inside JSON: table.column => [key => target table]. The key is
     * matched at any depth.
     *
     * @var array<string, array<string, string>>
     */
    public const JSON = [
        'activity_log.properties' => ['user_id' => 'users', 'owner_user_id' => 'users'],
        'bjj_match_events.payload' => ['match_id' => 'event_matches'],
        'event_match_events.payload' => ['match_id' => 'event_matches'],
        'certificates.details' => ['category_id' => 'event_categories', 'registration_id' => 'club_event_registrations'],
        'club_package_activities.schedule' => ['facility_id' => 'club_facilities'],
        'event_categories.podium' => ['competitor_id' => 'club_event_registrations'],
        'media_files.meta' => ['event_id' => 'club_events'],
    ];

    /**
     * Shared catalogues recognised by a natural key rather than by uuid: the
     * sport "Judo" is the same thing on both servers, created separately.
     *
     * @var array<string, string>
     */
    public const NATURAL_KEYS = [
        'activity_catalog' => 'slug',
    ];

    /**
     * Unique columns DERIVED from something else (a member's address name is
     * made from their name): on a collision with a different record, the copy
     * takes the next free value ("ghassan-yusuf-2") instead of being refused.
     *
     * @var array<int, string>
     */
    public const RENAMEABLE = ['users.slug'];

    /** "*_by" columns hold a member, unless listed here. */
    public const BY_EXCEPTIONS = ['activity_packages.matched_by'];

    /**
     * Columns that hold a file path on the storage disks: copied with the row.
     */
    public const FILE_COLUMN = '/(^|_)(photo|picture|image|logo|cover|path|file|avatar|banner|poster|thumbnail|document|attachment|proof|screenshot|crest|signature|background|media)s?$/';
}
