<?php

namespace App\Support;

/**
 * Stable, namespace-independent aliases for every model that can land in a
 * polymorphic `*_type` column.
 *
 * WHY THIS EXISTS: without a morph map, Laravel stores the fully-qualified
 * class name in the database — `activity_log.subject_type` held literal
 * `App\Models\Tenant` strings. That silently welds the audit trail to the PHP
 * namespace: move a class into a module folder and every historical row stops
 * resolving. No error, just history that quietly points at nothing.
 *
 * With an alias registered, the DB stores `club` and the namespace is free to
 * change forever. Add an entry here BEFORE moving any model that appears in a
 * morph column.
 *
 * Deliberately non-enforcing (`Relation::morphMap`, not `enforceMorphMap`):
 * a model absent from this list still works, storing its class name as before,
 * so an unmapped model can never throw at runtime.
 */
class MorphMap
{
    /**
     * @return array<string, class-string>
     */
    public static function map(): array
    {
        return [
            // ---- People ----------------------------------------------------
            'user' => \App\Models\User::class,

            // ---- The club and everything it owns ---------------------------
            'club' => \App\Clubs\Models\Tenant::class,
            'club_achievement' => \App\Clubs\Models\ClubAchievement::class,
            'club_activity' => \App\Clubs\Models\ClubActivity::class,
            'club_activity_equipment' => \App\Clubs\Models\ClubActivityEquipment::class,
            'club_affiliation' => \App\Clubs\Models\ClubAffiliation::class,
            'club_bank_account' => \App\Clubs\Models\ClubBankAccount::class,
            'club_facility' => \App\Clubs\Models\ClubFacility::class,
            'club_gallery_image' => \App\Clubs\Models\ClubGalleryImage::class,
            'club_instructor' => \App\Clubs\Models\ClubInstructor::class,
            'club_member_subscription' => \App\Models\ClubMemberSubscription::class,
            'club_message' => \App\Clubs\Models\ClubMessage::class,
            'club_notification' => \App\Clubs\Models\ClubNotification::class,
            'club_package' => \App\Clubs\Models\ClubPackage::class,
            'club_package_activity' => \App\Clubs\Models\ClubPackageActivity::class,
            'club_perk' => \App\Shop\Models\ClubPerk::class,
            'club_product' => \App\Shop\Models\ClubProduct::class,
            'club_product_category' => \App\Shop\Models\ClubProductCategory::class,
            'club_product_variant' => \App\Shop\Models\ClubProductVariant::class,
            'club_recurring_expense' => \App\Clubs\Models\ClubRecurringExpense::class,
            'club_review' => \App\Clubs\Models\ClubReview::class,
            'club_social_link' => \App\Clubs\Models\ClubSocialLink::class,
            'club_timeline_post' => \App\Clubs\Models\ClubTimelinePost::class,
            'club_transaction' => \App\Clubs\Models\ClubTransaction::class,
            'membership' => \App\Models\Membership::class,
            'invoice' => \App\Models\Invoice::class,
            'order' => \App\Shop\Models\Order::class,
            'perk_collection' => \App\Shop\Models\PerkCollection::class,

            // ---- Events (stay in the events module) ------------------------
            'club_event' => \App\Models\ClubEvent::class,
            'club_event_registration' => \App\Models\ClubEventRegistration::class,

            // ---- Member-owned records -------------------------------------
            'duel' => \App\Models\Duel::class,
            'goal' => \App\Models\Goal::class,
            'skill_acquisition' => \App\Models\SkillAcquisition::class,
            'tournament_event' => \App\Models\TournamentEvent::class,
            'user_post' => \App\Models\UserPost::class,

            // ---- Media -----------------------------------------------------
            'media_file' => \App\Models\MediaFile::class,
        ];
    }
}
