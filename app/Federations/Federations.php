<?php

namespace App\Federations;

use App\Federations\Services\FederationMessages;
use App\Members\Models\Conversation;
use App\Members\Models\Message;
use App\Federations\Copilot\CreateFederationSkill;
use App\Support\Modules\AbstractModule;
use App\Support\Modules\Surfaces\ContributesCopilotSkills;
use App\Support\Modules\Surfaces\SealsItsSurface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Support\Modules\Surfaces\ContributesMessengerThreads;
use App\Support\Modules\Surfaces\HostsEvents;

/**
 * Federations — the governing bodies clubs affiliate with.
 *
 * Not a Business: a chain OWNS its clubs and its owner drives their admin
 * panels; a federation owns nothing. A club applies to it, the federation
 * accepts, and from then on the federation SEES that club (its athletes, its
 * events) without gaining any power inside it.
 *
 * First slice: the federation itself and its dashboard. What a federation can
 * DO — licences, sanctioned events, rankings — is still to be decided.
 *
 * Behind config('federations.enabled') — app/Federations/config.php — OFF by default.
 *
 * EVERY file of this module lives in this folder, its config and migrations
 * included (bound by ModuleServiceProvider).
 *
 * Delete test: remove app/Federations/, its tables and the line in
 * config/modules.php — nothing else references it. (Its Messenger threads
 * are reached through the ContributesMessengerThreads surface; with the
 * module gone they are orphaned `type = 'federation'` conversations.)
 */
class Federations extends AbstractModule implements ContributesMessengerThreads, ContributesCopilotSkills, SealsItsSurface, HostsEvents
{
    public function key(): string
    {
        return 'federations';
    }

    // A federation writes to a club's admins in their own Messenger — see
    // FederationMessages. One thread type, owned here.

    public function messengerThreadTypes(): array
    {
        return [FederationMessages::TYPE];
    }

    public function messengerPartner(Conversation $conversation, int $viewerId): array
    {
        return app(FederationMessages::class)->partner($conversation);
    }

    public function messengerSenderName(Conversation $conversation, Message $message): ?string
    {
        return app(FederationMessages::class)->senderName($conversation, $message);
    }

    public function messengerMessage(Conversation $conversation, Message $message, string $action): void
    {
        app(FederationMessages::class)->posted($conversation, $message);
    }

    // A federation HOSTS its own competitions — it is their organiser, not a
    // club (App\Events\Support\EventHost). Nothing here grants power over a
    // club; it answers only for events whose host IS this federation.

    public function eventHostTypes(): array
    {
        return ['federation'];
    }

    public function eventHost(string $type, int $id): ?array
    {
        $f = $type === 'federation' ? \App\Federations\Models\Federation::find($id) : null;
        if (! $f) {
            return null;
        }

        return [
            'name' => (string) $f->name,
            'logo' => $f->logo && $f->isApproved() ? route('federations.logo', $f->uuid).'?v='.substr(md5((string) $f->logo), 0, 8) : null,
            'country' => $f->country ? strtoupper((string) $f->country) : null,
            'currency' => $f->currency ?: null,
            'timezone' => $f->timezone ?: null,
            'lat' => null,
            'lng' => null,
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $f->color) ? $f->color : null,
            'uuid' => $f->uuid,
        ];
    }

    /** A manager of this federation, or of a federation above it. */
    public function managesEventsOf(string $type, int $id, \App\Members\Models\User $user): bool
    {
        if ($type !== 'federation' || ! config('federations.enabled')) {
            return false;
        }
        $f = \App\Federations\Models\Federation::find($id);
        $directory = app(\App\Federations\Services\FederationDirectory::class);
        $access = $f ? $directory->access($user) : null;
        if (! $access || ! ($access['manager'] ?? false) || $f->isSuspended()) {
            return false;
        }

        return collect($directory->subtree($access['root']))->contains(fn ($b) => (int) $b->id === (int) $f->id);
    }

    /** The owners and full-rights staff of this federation and of every body above it. */
    public function eventHostManagers(string $type, int $id): array
    {
        $f = $type === 'federation' ? \App\Federations\Models\Federation::find($id) : null;
        $ids = [];
        for ($depth = 0; $f && $depth <= \App\Federations\Models\Federation::MAX_DEPTH; $depth++) {
            $ids[] = (int) $f->owner_user_id;
            $staff = \App\Federations\Models\FederationStaff::where('federation_id', $f->id)->get(['user_id', 'sports']);
            foreach ($staff as $row) {
                if (array_filter((array) $row->sports) === []) {
                    $ids[] = (int) $row->user_id;
                }
            }
            $f = $f->parent_id ? \App\Federations\Models\Federation::find($f->parent_id) : null;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** The console edited a federation's event: its dashboard record follows (FederationPlatformEvent::pullBack). */
    public function hostedEventChanged(\App\Events\Models\ClubEvent $event): void
    {
        if ($event->host_type === 'federation') {
            app(\App\Federations\Services\FederationPlatformEvent::class)->pullBack($event);
        }
    }

    /** The console decided a club's athlete: the dashboard records it (FederationPlatformEntries::decided). */
    public function hostedEntryDecided(\App\Events\Models\ClubEvent $event, \App\Events\Models\EventPublicEntry $entry): void
    {
        app(\App\Federations\Services\FederationPlatformEntries::class)->decided($entry);
    }

    /**
     * A federation takes its athletes from its CLUBS: the door is the event's
     * club invitation, and whoever runs a club may use it.
     */
    public function hostedEntryDoor(\App\Events\Models\ClubEvent $event, \App\Members\Models\User $user): ?array
    {
        if ($event->host_type !== 'federation' || ! config('federations.enabled')) {
            return null;
        }
        $fe = \App\Federations\Models\FederationEvent::where('club_event_id', $event->id)->first(['uuid']);
        if (! $fe) {
            return null;
        }
        $runsClub = \Illuminate\Support\Facades\DB::table('tenants')->where('owner_user_id', $user->id)->exists()
            || \Illuminate\Support\Facades\DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $user->id)->where('roles.slug', 'club-admin')->whereNotNull('user_roles.tenant_id')->exists();

        return ['url' => route('federations.app.invitation').'?event='.rawurlencode($fe->uuid), 'enters' => $runsClub];
    }

    /** Coach can create one of these — see App\Support\Copilot\CopilotSkill. */
    public function copilotSkills(): array
    {
        return [app(CreateFederationSkill::class)];
    }

    // /federation is a sealed product: errors inside it are answered inside it.

    public function sealsRequest(Request $request): bool
    {
        return config('federations.enabled') && ($request->is('federation') || $request->is('federation/*'));
    }

    public function sealedSignIn(Request $request): ?string
    {
        return $request->is('federation/login*') ? null : route('federations.login');
    }

    public function sealedErrorResponse(Request $request, int $status): Response
    {
        $key = match (true) {
            $status === 419 => 'sealed_expired',
            $status === 403 => 'sealed_forbidden',
            $status === 429 => 'sealed_throttled',
            $status >= 500 => 'sealed_error',
            default => 'sealed_not_found',
        };

        return $this->sealedPage($request, __('federations::messages.'.$key.'_title'), __('federations::messages.'.$key.'_body'),
            $request->user() !== null, $request->user() === null && ! $request->is('federation/app/Club*'), $status);
    }

    /**
     * The sealed page: a message and only doors inside /federation. Public
     * invitation pages (clubs, no account) get no sign-in button — it is not
     * theirs.
     */
    public function sealedPage(Request $request, string $title, string $message, bool $home, bool $signIn, int $status = 200): Response
    {
        $root = $request->user() ? app(Services\FederationDirectory::class)->access($request->user())['root'] ?? null : null;

        return response()->view('federations::auth.sealed', [
            'title' => $title, 'message' => $message, 'signedIn' => $request->user() !== null,
            'home' => $home && $root !== null, 'signIn' => $signIn,
            'favicon' => Support\FederationBrand::favicon($root),
        ], $status)->header('Cache-Control', 'no-store, private');
    }
}
