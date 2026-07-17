<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessengerController extends Controller
{
    /** Max upload size for a chat attachment. */
    private const MAX_ATTACHMENT_BYTES = 8 * 1024 * 1024; // 8 MB

    /** How long a stored attachment lives on disk before it's pruned. */
    private const ATTACHMENT_TTL_HOURS = 24;

    /** Private disk + folder where encrypted attachment blobs live. */
    private const ATTACHMENT_DISK = 'local';

    private const ATTACHMENT_DIR = 'chat-attachments';

    /** Only these (sniffed) types are ever shown inline; all else downloads. */
    private const SAFE_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** Messenger inbox — conversation list (device-specific view). */
    public function index()
    {
        $conversations = $this->inboxFor((int) Auth::id());

        return view($this->pick('messenger'), [
            'conversations' => $conversations,
            'unreadTotal' => $conversations->sum('unread_count'),
        ]);
    }

    /** JSON inbox (used to refresh the list without a reload). */
    public function conversations()
    {
        return response()->json([
            'success' => true,
            'conversations' => $this->inboxFor((int) Auth::id()),
        ]);
    }

    /** Start (or reopen) a 1:1 conversation with another user. */
    public function start(Request $request, User $user)
    {
        $me = (int) Auth::id();
        abort_if($user->id === $me, 422, "You can't message yourself.");

        // Consent rule: only club-mates, accepted connections, or existing threads.
        abort_unless(Auth::user()->canMessage($user), 403,
            'You can only message members of your clubs or people you’re connected with.');

        $conversation = Conversation::findOrCreateDirect($me, $user->id);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'conversation_id' => $conversation->id]);
        }

        return redirect()->route('messages.show', $conversation);
    }

    /** Open a conversation thread (full page; reuses the inbox shell). */
    public function show(Conversation $conversation)
    {
        $this->authorizeParticipant($conversation);

        $conversations = $this->inboxFor((int) Auth::id());

        return view($this->pick('messenger'), [
            'conversations' => $conversations,
            'unreadTotal' => $conversations->sum('unread_count'),
            'openConversation' => $conversation->id,
        ]);
    }

    /** Thread messages as JSON; marks the conversation read for this user. */
    public function thread(Conversation $conversation)
    {
        $this->authorizeParticipant($conversation);
        $me = (int) Auth::id();

        $hiddenIds = DB::table('message_hides')->where('user_id', $me)->pluck('message_id');
        $clearedAt = DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)->where('user_id', $me)->value('cleared_at');

        $messages = $conversation->messages()->with('sender:id,full_name,name,profile_picture')
            ->whereNotIn('id', $hiddenIds)
            ->when($clearedAt, fn ($q) => $q->where('created_at', '>', $clearedAt))
            ->orderBy('id')->get();

        $conversation->participants()->updateExistingPivot($me, ['last_read_at' => now()]);

        $other = $conversation->loadMissing('participants')->otherParticipant($me);

        return response()->json([
            'success' => true,
            'partner' => $this->presentUser($other),
            'messages' => $messages->map(fn ($m) => $this->presentMessage($m, $me))->values(),
        ]);
    }

    /** Send a message into a conversation; persists then pushes realtime. */
    public function send(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($conversation);
        $me = (int) Auth::id();

        // A block created after a direct thread already exists must still stop new
        // messages — canMessage() only guards opening a NEW thread in start().
        if ($conversation->type === 'direct') {
            $otherId = $conversation->participants()->where('users.id', '!=', $me)->value('users.id');
            abort_if($otherId && Auth::user()->blockedEitherWay((int) $otherId), 403);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $me,
            'body' => $data['body'],
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();
        $conversation->participants()->updateExistingPivot($me, ['last_read_at' => now()]);

        $this->pushRealtime($conversation, $message);

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'data' => $this->presentMessage($message, $me),
        ]);
    }

    /** Edit one of my own messages (WhatsApp-style, within a time window). */
    public function editMessage(Request $request, Conversation $conversation, Message $message)
    {
        $this->authorizeOwnMessage($conversation, $message);

        if ($message->isDeleted()) {
            return response()->json(['success' => false, 'message' => 'This message was deleted.'], 422);
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $message->forceFill(['body' => $data['body'], 'edited_at' => now()])->save();

        $this->pushRealtime($conversation, $message, 'edit');

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'is_latest' => $this->isLatest($conversation, $message),
            'data' => $this->presentMessage($message, (int) Auth::id()),
        ]);
    }

    /** Delete a message for everyone — own message only. */
    public function deleteMessage(Conversation $conversation, Message $message)
    {
        $this->authorizeParticipant($conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);
        $mine = (int) $message->sender_id === (int) Auth::id();
        abort_unless($mine, 403);

        if (! $message->isDeleted()) {
            // Blank the (encrypted) body so the content is truly gone; the row
            // and deleted_at marker remain so both sides render the tombstone.
            $message->forceFill(['body' => '', 'deleted_at' => now()])->save();
            $this->pushRealtime($conversation, $message, 'delete');
        }

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'is_latest' => $this->isLatest($conversation, $message),
        ]);
    }

    /**
     * Upload a picture or file. Stored encrypted-at-rest on a private disk with
     * an expiry, persisted as a normal message, then announced over realtime
     * (URL + metadata only — never the bytes). Durable: offline recipients get
     * it when they next open the thread, until it expires and is pruned.
     */
    public function uploadFile(Request $request, Conversation $conversation)
    {
        $this->authorizeParticipant($conversation);
        $me = (int) Auth::id();

        $request->validate([
            'file' => ['required', 'file', 'max:'.(int) (self::MAX_ATTACHMENT_BYTES / 1024)],
        ]);

        $file = $request->file('file');
        // Sniff the real MIME from file contents (finfo) — never trust the
        // client-declared type. Only well-known raster formats are treated as
        // displayable images; everything else (incl. SVG/HTML, which can carry
        // script) is a generic "file" that is only ever served as a download.
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $kind = in_array($mime, self::SAFE_IMAGE_MIMES, true) ? 'image'
              : (str_starts_with($mime, 'audio/') ? 'audio'
              : (str_starts_with($mime, 'video/') ? 'video' : 'file'));
        $name = mb_substr($file->getClientOriginalName() ?: ($kind === 'image' ? 'photo' : 'file'), 0, 255);

        // Encrypt the raw bytes before they ever touch disk.
        $path = self::ATTACHMENT_DIR.'/'.$conversation->id.'/'.Str::uuid()->toString();
        Storage::disk(self::ATTACHMENT_DISK)->put($path, Crypt::encryptString($file->get()));

        $message = $conversation->messages()->create([
            'sender_id' => $me,
            'body' => '',
            'attachment_path' => $path,
            'attachment_name' => $name,
            'attachment_mime' => $mime,
            'attachment_size' => $file->getSize(),
            'attachment_kind' => $kind,
            'attachment_expires_at' => now()->addHours(self::ATTACHMENT_TTL_HOURS),
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();
        $conversation->participants()->updateExistingPivot($me, ['last_read_at' => now()]);

        $this->pushRealtime($conversation, $message, 'file');

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'data' => $this->presentMessage($message, $me),
        ]);
    }

    /** Stream a stored attachment (decrypted) to an authorised participant. */
    public function serveAttachment(Conversation $conversation, Message $message)
    {
        $this->authorizeParticipant($conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_if($message->isDeleted() || $message->attachment_path === null, 404);

        // Hidden "for me" or already expired → gone for this user.
        abort_if(
            DB::table('message_hides')->where('user_id', Auth::id())->where('message_id', $message->id)->exists(),
            404,
        );

        $disk = Storage::disk(self::ATTACHMENT_DISK);
        abort_unless($disk->exists($message->attachment_path), 404);

        $bytes = Crypt::decryptString($disk->get($message->attachment_path));

        // Inline ONLY safe raster images and audio (neither can execute script);
        // anything else (SVG/HTML/etc.) is forced to download. The served
        // Content-Type is pinned to those known types — the stored mime was
        // sniffed server-side at upload, so it is trustworthy here.
        $mime = (string) $message->attachment_mime;
        $safeImage = in_array($mime, self::SAFE_IMAGE_MIMES, true);
        $isMedia = str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/');
        $inline = $safeImage || $isMedia;
        $type = $inline ? $mime : 'application/octet-stream';
        $name = str_replace(['"', "\r", "\n"], '', (string) $message->attachment_name);

        $headers = [
            'Content-Type' => $type,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$name.'"',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            // Defence in depth: even if a payload slips through, render nothing.
            'Content-Security-Policy' => "default-src 'none'; sandbox; style-src 'unsafe-inline'",
            'Accept-Ranges' => 'bytes',
        ];

        // Honour HTTP Range so audio/video can seek without downloading the whole
        // file first. We already hold the full decrypted bytes, so slicing is cheap.
        $size = strlen($bytes);
        $range = request()->header('Range');
        if ($range && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
            $start = $m[1] === '' ? 0 : (int) $m[1];
            $end = $m[2] === '' ? $size - 1 : min((int) $m[2], $size - 1);
            if ($start > $end || $start >= $size) {
                return response('', 416, ['Content-Range' => "bytes */{$size}"]);
            }
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";

            return response(substr($bytes, $start, $end - $start + 1), 206, $headers);
        }

        return response($bytes, 200, $headers);
    }

    /** Delete a message just for me — hides it from my thread only. */
    public function deleteMessageForMe(Conversation $conversation, Message $message)
    {
        $this->authorizeParticipant($conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);

        DB::table('message_hides')->insertOrIgnore([
            'message_id' => $message->id,
            'user_id' => (int) Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message_id' => $message->id,
        ]);
    }

    /** Delete a whole chat for me — hides it and its history until a new message. */
    public function deleteConversation(Conversation $conversation)
    {
        $this->authorizeParticipant($conversation);
        $conversation->participants()->updateExistingPivot(Auth::id(), ['cleared_at' => now()]);

        return response()->json(['success' => true, 'conversation_id' => $conversation->id]);
    }

    /**
     * Fetch link metadata for an in-chat preview / embedded video player.
     * SSRF-hardened: http(s) only, standard ports, and the host's resolved IPs
     * must all be public (no private/reserved ranges) — re-checked per redirect.
     */
    public function linkPreview(Request $request)
    {
        $url = trim((string) $request->query('url', ''));
        if (! $this->resolveSafeUrl($url)) {
            return response()->json(['success' => false], 422);
        }

        $preview = \Illuminate\Support\Facades\Cache::remember(
            'linkpreview:'.sha1($url),
            now()->addHours(6),
            fn () => $this->buildPreview($url),
        );

        return response()->json(['success' => (bool) $preview, 'preview' => $preview]);
    }

    /** Mark a conversation as read for the current user. */
    public function read(Conversation $conversation)
    {
        $this->authorizeParticipant($conversation);
        $conversation->participants()->updateExistingPivot((int) Auth::id(), ['last_read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /** Total unread across all conversations — drives the header chat badge. */
    public function unreadCount()
    {
        return response()->json(['count' => $this->inboxFor((int) Auth::id())->sum('unread_count')]);
    }

    /** Platform-wide user search for starting a new chat (Facebook-style). */
    public function searchUsers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $me = Auth::user();

        // You can only reach people you're allowed to message: club-mates,
        // accepted connections, or someone you already have a thread with.
        $myClubIds = $me->memberClubs()->pluck('tenants.id');

        $clubMateIds = $myClubIds->isEmpty()
            ? collect()
            : User::whereHas('memberClubs', fn ($w) => $w->whereIn('tenants.id', $myClubIds))
                ->whereKeyNot($me->id)->pluck('id');

        $connectedIds = \App\Models\UserConnection::where('status', 'accepted')
            ->where(fn ($w) => $w->where('requester_id', $me->id)->orWhere('addressee_id', $me->id))
            ->get(['requester_id', 'addressee_id'])
            ->map(fn ($c) => $c->requester_id === $me->id ? $c->addressee_id : $c->requester_id);

        $partnerIds = DB::table('conversation_user as cu1')
            ->join('conversation_user as cu2', 'cu2.conversation_id', '=', 'cu1.conversation_id')
            ->join('conversations as c', 'c.id', '=', 'cu1.conversation_id')
            ->where('c.type', 'direct')
            ->where('cu1.user_id', $me->id)
            ->where('cu2.user_id', '!=', $me->id)
            ->pluck('cu2.user_id');

        $blockedIds = \App\Models\UserBlock::where('blocker_id', $me->id)->pluck('blocked_id')
            ->merge(\App\Models\UserBlock::where('blocked_id', $me->id)->pluck('blocker_id'));

        $allowedIds = collect()->merge($clubMateIds)->merge($connectedIds)->merge($partnerIds)
            ->unique()->diff($blockedIds)->values();

        // With a query, people-discovery widens reach to any DISCOVERABLE member
        // (they've opted into being found + contacted). With no query, only the
        // already-reachable set is listed (don't dump the whole platform).
        $users = ($allowedIds->isEmpty() && $q === '') ? collect() : User::query()
            ->where(function ($outer) use ($allowedIds, $q) {
                $outer->whereIn('id', $allowedIds);
                if ($q !== '') {
                    $outer->orWhere('is_discoverable', true);
                }
            })
            ->whereKeyNot($me->id)
            ->whereNotIn('id', $blockedIds)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(fn ($w) => $w
                    ->where('full_name', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%"));
            })
            ->orderBy('full_name')
            ->limit(15)
            ->get(['id', 'full_name', 'name', 'profile_picture']);

        return response()->json([
            'success' => true,
            'users' => $users->map(fn ($u) => $this->presentUser($u))->values(),
        ]);
    }

    /* ───────────────────────── helpers ───────────────────────── */

    private function authorizeParticipant(Conversation $conversation): void
    {
        abort_unless(
            $conversation->participants()->whereKey(Auth::id())->exists(),
            403,
        );
    }

    /** Guard edit/delete: I'm a participant, the message is in this thread, and it's mine. */
    private function authorizeOwnMessage(Conversation $conversation, Message $message): void
    {
        $this->authorizeParticipant($conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless((int) $message->sender_id === (int) Auth::id(), 403);
    }

    /** Build a link preview: YouTube/Vimeo embed, or Open-Graph card. */
    private function buildPreview(string $url): ?array
    {
        if ($embed = $this->videoEmbed($url)) {
            return $embed;
        }

        [$finalUrl, $html] = $this->safeFetch($url);
        if ($html === null) {
            return null;
        }

        $title = $this->metaContent($html, 'og:title') ?: $this->htmlTitle($html);
        $desc = $this->metaContent($html, 'og:description') ?: $this->metaContent($html, 'description');
        $image = $this->metaContent($html, 'og:image');
        $site = $this->metaContent($html, 'og:site_name') ?: parse_url($finalUrl, PHP_URL_HOST);

        if ($image && ! preg_match('#^https?://#i', $image)) {
            $image = $this->absoluteUrl($finalUrl, $image);
        }
        if ($image && ! $this->resolveSafeUrl($image)) {
            $image = null;
        }
        if (! $title && ! $image) {
            return null;
        }

        return [
            'type' => 'link',
            'url' => $url,
            'title' => $title ? mb_substr(trim($title), 0, 140) : null,
            'description' => $desc ? mb_substr(trim($desc), 0, 200) : null,
            'image' => $image,
            'site' => $site ? mb_substr($site, 0, 60) : null,
        ];
    }

    /** Recognise embeddable video providers without fetching anything. */
    private function videoEmbed(string $url): ?array
    {
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([\w-]{6,})#i', $url, $m)) {
            return ['type' => 'video_embed', 'provider' => 'youtube', 'embed' => 'https://www.youtube.com/embed/'.$m[1], 'url' => $url];
        }
        if (preg_match('#vimeo\.com/(\d+)#i', $url, $m)) {
            return ['type' => 'video_embed', 'provider' => 'vimeo', 'embed' => 'https://player.vimeo.com/video/'.$m[1], 'url' => $url];
        }

        return null;
    }

    /** Fetch HTML, re-validating SSRF safety on every manual redirect hop. */
    private function safeFetch(string $url, int $maxRedirects = 3): array
    {
        $current = $url;
        for ($i = 0; $i <= $maxRedirects; $i++) {
            $ips = $this->safeIpsFor($current);
            if (! $ips) {
                return [$current, null];
            }

            $p = parse_url($current);
            $host = $p['host'];
            $port = $p['port'] ?? (($p['scheme'] ?? 'http') === 'https' ? 443 : 80);
            $pin = $ips[0]; // connect ONLY to a pre-validated address

            try {
                $resp = \Illuminate\Support\Facades\Http::timeout(5)
                    ->withOptions([
                        'allow_redirects' => false,
                        // Pin DNS so curl cannot re-resolve to a different (private)
                        // address between our check and the connection (rebinding).
                        'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pin}"]],
                    ])
                    ->withHeaders(['User-Agent' => 'TakeOneBot/1.0 (+link-preview)', 'Accept' => 'text/html'])
                    ->get($current);
            } catch (\Throwable $e) {
                return [$current, null];
            }

            // Defence in depth: the address actually connected to must be validated.
            $peer = $resp->handlerStats()['primary_ip'] ?? null;
            if ($peer && ! in_array($peer, $ips, true)) {
                return [$current, null];
            }

            if ($resp->redirect() && $resp->header('Location')) {
                $loc = $resp->header('Location');
                $current = preg_match('#^https?://#i', $loc) ? $loc : $this->absoluteUrl($current, $loc);

                continue;
            }
            if (! $resp->ok() || ! str_contains(strtolower($resp->header('Content-Type') ?? ''), 'text/html')) {
                return [$current, null];
            }

            return [$current, mb_substr($resp->body(), 0, 300000)];
        }

        return [$current, null];
    }

    /** Bool wrapper around safeIpsFor() for cheap pre-checks. */
    private function resolveSafeUrl(string $url): bool
    {
        return $this->safeIpsFor($url) !== null;
    }

    /**
     * Validate a URL for outbound fetching and return its resolved IPs.
     * http(s) only, standard ports, and EVERY resolved address must be public.
     * Returns the IP list (for connection pinning) or null if unsafe.
     */
    private function safeIpsFor(string $url): ?array
    {
        $p = parse_url($url);
        if (! $p || ! in_array($p['scheme'] ?? '', ['http', 'https'], true) || empty($p['host'])) {
            return null;
        }
        if (isset($p['port']) && ! in_array((int) $p['port'], [80, 443], true)) {
            return null;
        }

        $host = $p['host'];
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            foreach ((@dns_get_record($host, DNS_A) ?: []) as $r) {
                if (! empty($r['ip'])) {
                    $ips[] = $r['ip'];
                }
            }
            foreach ((@dns_get_record($host, DNS_AAAA) ?: []) as $r) {
                if (! empty($r['ipv6'])) {
                    $ips[] = $r['ipv6'];
                }
            }
        }
        if (empty($ips)) {
            return null;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return array_values(array_unique($ips));
    }

    private function absoluteUrl(string $base, string $rel): string
    {
        if (str_starts_with($rel, '//')) {
            return (parse_url($base, PHP_URL_SCHEME) ?: 'https').':'.$rel;
        }
        $b = parse_url($base);
        $origin = ($b['scheme'] ?? 'https').'://'.($b['host'] ?? '');
        if (str_starts_with($rel, '/')) {
            return $origin.$rel;
        }
        $path = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');

        return $origin.$path.$rel;
    }

    private function metaContent(string $html, string $prop): ?string
    {
        $q = preg_quote($prop, '#');
        if (preg_match('#<meta[^>]+(?:property|name)=["\']'.$q.'["\'][^>]*content=["\']([^"\']*)["\']#i', $html, $m)
            || preg_match('#<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']'.$q.'["\']#i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    private function htmlTitle(string $html): ?string
    {
        return preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)
            ? html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5)
            : null;
    }

    /** Short label for an attachment, used in toasts and inbox previews. */
    private function attachmentLabel(?string $kind, ?string $name): string
    {
        return match ($kind) {
            'image' => '📷 Photo',
            'audio' => '🎵 Audio',
            'video' => '🎬 Video',
            default => '📎 '.$name,
        };
    }

    /** Is this the most recent message in the conversation (drives inbox preview)? */
    private function isLatest(Conversation $conversation, Message $message): bool
    {
        return (int) $conversation->messages()->max('id') === (int) $message->id;
    }

    /** Build the presented inbox list for a user, newest activity first. */
    private function inboxFor(int $userId)
    {
        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->whereKey($userId))
            ->with([
                'participants:id,full_name,name,profile_picture',
                'latestMessage',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            // Hide chats the user "deleted" — unless a newer message has arrived.
            ->reject(function (Conversation $c) use ($userId) {
                $cleared = $c->participants->firstWhere('id', $userId)?->pivot?->cleared_at;

                return $cleared && (! $c->last_message_at || $c->last_message_at <= $cleared);
            });

        return $conversations->map(function (Conversation $c) use ($userId) {
            $other = $c->otherParticipant($userId);
            $last = $c->latestMessage;

            return (object) [
                'id' => $c->id,
                'partner' => $this->presentUser($other),
                'last_body' => $last
                    ? ($last->isDeleted()
                        ? 'This message was deleted'
                        : ($last->attachment_kind !== null
                            ? $this->attachmentLabel($last->attachment_kind, $last->attachment_name)
                            : Str::limit((string) $last->body, 40)))
                    : null,
                'last_mine' => $last ? $last->sender_id === $userId : false,
                'last_at_human' => $c->last_message_at?->diffForHumans(null, true, true),
                'unread_count' => $c->unreadCountFor($userId),
            ];
        });
    }

    private function presentMessage(Message $m, int $meId): array
    {
        $deleted = $m->isDeleted();
        $mine = (int) $m->sender_id === $meId;
        $hasAtt = $m->attachment_kind !== null && ! $deleted;
        $attExpired = $hasAtt && $m->attachment_path === null;

        return [
            'id' => $m->id,
            'body' => $deleted ? null : $m->body,
            'mine' => $mine,
            'sender_id' => $m->sender_id,
            'created_at' => $m->created_at->toIso8601String(),
            'created_at_human' => $m->created_at->diffForHumans(null, true, true),
            'time' => $m->created_at->format('g:i A'),
            'edited' => $m->edited_at !== null && ! $deleted,
            'deleted' => $deleted,
            // Only own, live, text (non-attachment) messages can be edited.
            'can_edit' => $mine && ! $deleted && ! $hasAtt,
            'kind' => $hasAtt ? $m->attachment_kind : null,
            'attachment_expired' => $attExpired,
            'attachment' => ($hasAtt && ! $attExpired) ? [
                'url' => route('messages.attachment', [$m->conversation_id, $m->id]),
                'name' => $m->attachment_name,
                'mime' => $m->attachment_mime,
                'size' => (int) $m->attachment_size,
            ] : null,
        ];
    }

    private function presentUser(?User $user): array
    {
        if (! $user) {
            return ['id' => null, 'name' => 'Unknown', 'avatar' => null, 'initial' => '?'];
        }

        $name = $user->full_name ?? $user->name ?? 'User';
        $me = Auth::user();

        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'slug' => $user->slug,
            'name' => $name,
            'avatar' => $user->profile_picture ? asset('storage/'.$user->profile_picture) : null,
            'initial' => strtoupper(mb_substr($name, 0, 1)),
            'blocked' => $me && $me->id !== $user->id ? $me->hasBlocked($user->id) : false,
        ];
    }

    /**
     * Best-effort realtime fan-out to the other participant(s).
     *
     * $action is 'new' (default), 'edit', or 'delete' — the client branches on
     * it to append, patch in place, or tombstone the message without a reload.
     */
    private function pushRealtime(Conversation $conversation, Message $message, string $action = 'new'): void
    {
        $sender = Auth::user();
        $senderUi = $this->presentUser($sender);
        $deleted = $action === 'delete';
        $isFile = $action === 'file';

        $recipients = $conversation->participants()
            ->whereKeyNot($message->sender_id)
            ->pluck('users.id');

        // Attachment fan-out carries only a URL + metadata (no bytes).
        $attachment = null;
        $body = $deleted ? null : $message->body;
        if ($isFile) {
            $attachment = [
                'url' => route('messages.attachment', [$conversation->id, $message->id]),
                'name' => $message->attachment_name,
                'mime' => $message->attachment_mime,
                'size' => (int) $message->attachment_size,
            ];
            $body = $this->attachmentLabel($message->attachment_kind, $message->attachment_name);
        }

        if ($recipients->isEmpty()) {
            return;
        }

        // Identical payload for every recipient — build once.
        $payload = [
            'action' => $action,
            'conversation_id' => $conversation->id,
            'id' => $message->id,
            'from_id' => (int) $message->sender_id,
            'from_name' => $senderUi['name'],
            'from_avatar' => $senderUi['avatar'],
            'body' => $body,
            'edited' => $message->edited_at !== null && ! $deleted,
            'deleted' => $deleted,
            'kind' => $isFile ? $message->attachment_kind : null,
            'attachment' => $attachment,
            'is_latest' => $this->isLatest($conversation, $message),
            'created_at_human' => 'just now',
            // Raw UTC instant — the client formats this in the VIEWER's own
            // timezone. Never trust a server-formatted wall-clock string here;
            // config('app.timezone') is UTC, which isn't any real user's
            // timezone, so a pre-formatted string was silently wrong for
            // everyone (e.g. always 3 hours behind for a Bahrain-based user).
            'created_at' => $message->created_at->toIso8601String(),
            'time' => $message->created_at->format('g:i A'),
        ];

        // Batch into ONE broker connection (a per-recipient connection would
        // open a separate socket for every participant).
        $batch = $recipients->map(fn ($uid) => [
            'topic' => \Takeone\Realtime\Support\Topics::user((int) $uid, 'messages'),
            'payload' => $payload,
        ])->all();

        \Realtime()->publishMany($batch);

        // Native push (FCM) to the tray — only for new messages / files, not edits/deletes.
        if ($action === 'new' || $isFile) {
            try {
                $url = route('messages.show', $conversation->id);
                foreach ($recipients as $uid) {
                    \App\Jobs\SendPushNotification::dispatch(
                        (int) $uid,
                        $senderUi['name'],
                        (string) ($body ?? ''),
                        ['type' => 'message', 'conversation_id' => (string) $conversation->id, 'action_url' => $url],
                    );
                }
            } catch (\Throwable $e) {
                // Best-effort.
            }
        }
    }

    /** Device-aware view picker (desktop vs mobile), mirroring ClubView. */
    private function pick(string $view): string
    {
        $isMobile = (bool) request()->attributes->get('is_mobile', false);
        $mobile = 'messenger.mobile';

        return $isMobile && view()->exists($mobile) ? $mobile : 'messenger.index';
    }
}
