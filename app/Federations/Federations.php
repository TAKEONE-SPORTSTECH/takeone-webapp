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
class Federations extends AbstractModule implements ContributesMessengerThreads, ContributesCopilotSkills, SealsItsSurface
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
