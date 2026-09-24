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

        if ($invite) {
            rescue(fn () => Password::sendResetLink(['email' => strtolower((string) $app->email)]), report: true);
        }

        return $link;
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
