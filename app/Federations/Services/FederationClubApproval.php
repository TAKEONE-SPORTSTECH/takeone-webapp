<?php

namespace App\Federations\Services;

use App\Clubs\Models\Tenant;
use App\Clubs\Support\ClubCreationService;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationTransfer;
use App\Members\Models\User;
use App\Support\StoragePath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A club that applied through an invitation becomes a real platform club —
 * only now, when the federation approves it.
 *
 * Approval creates the club through the platform's own door
 * (ClubCreationService), gives the coach an account to run it (invited by
 * email to set their own password), affiliates the club with the federation,
 * and moves everything the application held — athletes, entries, transfers,
 * officials, its personal link — onto the new club. Refusing leaves nothing
 * on the club side.
 */
class FederationClubApproval
{
    public function __construct(private ClubCreationService $clubs) {}

    public function approve(FederationClubApplication $app, ?User $by): FederationClub
    {
        if ($app->status !== FederationClubApplication::STATUS_PENDING) {
            throw new \RuntimeException("That application is {$app->status}, not Pending.");
        }

        $fed = $app->federation;
        $invite = false;

        $link = DB::transaction(function () use ($app, $fed, $by, &$invite) {
            $email = strtolower((string) $app->email);
            $owner = $email !== '' ? User::where('email', $email)->first() : null;
            if (! $owner) {
                $name = $app->coach ?: $app->name;
                $owner = User::create([
                    'full_name' => $name, 'name' => $name, 'email' => $email ?: null,
                    'password' => Hash::make(Str::random(48)), 'is_discoverable' => false,
                ]);
                $invite = $email !== '';
            }

            $country = strtoupper((string) ($fed->country ?: 'BH'));
            $slug = $this->slug($app->name);
            $digits = preg_replace('/\D/', '', (string) $app->phone);

            $tenant = $this->clubs->create([
                'owner_user_id' => $owner->id,
                'club_name' => $app->name,
                'slug' => $slug,
                'email' => $email ?: null,
                'phone' => $digits !== '' ? ['code' => '', 'number' => trim((string) $app->phone)] : null,
                'address' => $app->address,
                'owner_name' => $app->coach,
                'owner_email' => $email ?: null,
                'country' => $country,
                'currency' => $fed->currency ?: 'BHD',
                'timezone' => $fed->timezone ?: 'Asia/Bahrain',
                'status' => 'active',
            ]);

            // The logo moves into the club's own branding folder.
            if ($app->logo && Storage::disk('public')->exists($app->logo)) {
                $target = StoragePath::clubBySlug($slug, 'branding', $country).'/'.basename($app->logo);
                Storage::disk('public')->move($app->logo, $target);
                $tenant->logo = $target;
                $tenant->save();
                $app->logo = null;
            }

            $link = FederationClub::create([
                'federation_id' => $app->federation_id,
                'tenant_id' => $tenant->id,
                'status' => FederationClub::STATUS_APPROVED,
                'decided_at' => now(),
                'decided_by' => $by?->id,
                'officials' => $app->officials,
            ]);
            // The club keeps the personal link it already had.
            $token = $app->invite_token;
            $app->invite_token = null;
            $app->fill(['status' => FederationClubApplication::STATUS_APPROVED, 'tenant_id' => $tenant->id, 'federation_club_id' => $link->id, 'decided_by' => $by?->id, 'decided_at' => now()])->save();
            $link->invite_token = $token;
            $link->save();

            FederationAthleteClub::where('club_application_id', $app->id)->update(['federation_club_id' => $link->id, 'club_application_id' => null]);
            FederationEntry::where('club_application_id', $app->id)->update(['federation_club_id' => $link->id, 'club_application_id' => null]);
            FederationTransfer::where('to_application_id', $app->id)->update(['to_club_id' => $link->id, 'to_application_id' => null]);
            FederationTransfer::where('from_application_id', $app->id)->update(['from_club_id' => $link->id, 'from_application_id' => null]);

            return $link;
        });

        // A test club's owner is not sent a real "set your password" email.
        if ($invite && ! \App\Federations\Support\FederationTestMode::isActive()) {
            rescue(fn () => Password::sendResetLink(['email' => strtolower((string) $app->email)]), report: true);
        }

        return $link;
    }

    /**
     * The application is a club that is ALREADY on the platform: affiliate
     * that club instead of creating a second one, and move everything the
     * application held onto it — the duplicate never exists.
     *
     * Refuses when both sides already hold an entry in the same event (an
     * event has one entry per club); the federation settles that first.
     */
    public function approveAsExisting(FederationClubApplication $app, Tenant $tenant, ?User $by): FederationClub
    {
        if ($app->status !== FederationClubApplication::STATUS_PENDING) {
            throw new \RuntimeException("That application is {$app->status}, not Pending.");
        }

        return DB::transaction(function () use ($app, $tenant, $by) {
            $link = FederationClub::where('federation_id', $app->federation_id)->where('tenant_id', $tenant->id)->lockForUpdate()->first();

            if ($link) {
                $clash = FederationEntry::with('event:id,name')->where('club_application_id', $app->id)
                    ->whereIn('federation_event_id', FederationEntry::where('federation_club_id', $link->id)->pluck('federation_event_id'))
                    ->first();
                if ($clash) {
                    throw new \RuntimeException('Both already have an entry in '.($clash->event?->name ?: 'the same event').'. Withdraw one of them first.');
                }
            }

            $link ??= new FederationClub(['federation_id' => $app->federation_id, 'tenant_id' => $tenant->id]);
            $link->fill([
                'status' => FederationClub::STATUS_APPROVED,
                'decided_at' => now(),
                'decided_by' => $by?->id,
                'officials' => $link->officials ?: $app->officials,
            ]);
            // The club keeps the personal link it already had; if it had none, the application's carries over.
            $token = $app->invite_token;
            $app->invite_token = null;
            $app->fill(['status' => FederationClubApplication::STATUS_APPROVED, 'tenant_id' => $tenant->id, 'decided_by' => $by?->id, 'decided_at' => now()])->save();
            if (! $link->invite_token && $token) {
                $link->invite_token = $token;
            }
            $link->save();
            $app->federation_club_id = $link->id;
            $app->save();

            FederationAthleteClub::where('club_application_id', $app->id)->update(['federation_club_id' => $link->id, 'club_application_id' => null]);
            FederationEntry::where('club_application_id', $app->id)->update(['federation_club_id' => $link->id, 'club_application_id' => null]);
            FederationTransfer::where('to_application_id', $app->id)->update(['to_club_id' => $link->id, 'to_application_id' => null]);
            FederationTransfer::where('from_application_id', $app->id)->update(['from_club_id' => $link->id, 'from_application_id' => null]);

            $this->fillBlanks($tenant, $app);

            return $link;
        });
    }

    /**
     * What the application told us fills the club's EMPTY fields — coach,
     * email, phone, address, logo. Never replaces a value the club already
     * has: the club's record is its owner's.
     *
     * @return array<int, string> the fields that were filled
     */
    public function fillBlanks(Tenant $tenant, FederationClubApplication $app): array
    {
        $filled = [];
        $blank = fn ($v) => trim((string) $v) === '';

        if ($blank($tenant->owner_name) && ! $blank($app->coach)) {
            $tenant->owner_name = trim((string) $app->coach);
            $filled[] = 'coach';
        }
        if ($blank($tenant->email) && ! $blank($app->email)) {
            $tenant->email = strtolower(trim((string) $app->email));
            $filled[] = 'email';
        }
        $phone = is_array($tenant->phone) ? $tenant->phone : [];
        if ($blank($phone['number'] ?? null) && preg_replace('/\D/', '', (string) $app->phone) !== '') {
            $tenant->phone = ['code' => '', 'number' => trim((string) $app->phone)];
            $filled[] = 'phone';
        }
        if ($blank($tenant->getRawOriginal('address')) && ! $blank($app->address)) {
            $tenant->address = trim((string) $app->address);
            $filled[] = 'address';
        }
        if (! $tenant->logo && $app->logo && Storage::disk('public')->exists($app->logo)) {
            $target = StoragePath::clubBySlug($tenant->slug, 'branding', $tenant->country).'/'.basename($app->logo);
            Storage::disk('public')->move($app->logo, $target);
            $tenant->logo = $target;
            $app->logo = null;
            $app->save();
            $filled[] = 'logo';
        }

        if ($filled !== []) {
            $tenant->save();
            activity()->performedOn($tenant)
                ->withProperties(['application' => $app->uuid, 'filled' => $filled])->log('federation.club_blanks_filled');
        }

        return $filled;
    }

    public function reject(FederationClubApplication $app, ?User $by): void
    {
        if ($app->status !== FederationClubApplication::STATUS_PENDING) {
            throw new \RuntimeException("That application is {$app->status}, not Pending.");
        }
        $app->fill(['status' => FederationClubApplication::STATUS_REJECTED, 'invite_token' => null, 'decided_by' => $by?->id, 'decided_at' => now()])->save();
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'club';
        $slug = $base;
        $n = 2;
        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
