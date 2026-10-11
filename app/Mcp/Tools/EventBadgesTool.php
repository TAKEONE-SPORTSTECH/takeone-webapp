<?php

namespace App\Mcp\Tools;

use App\Certificates\Support\CertificateBundles;
use App\Events\Support\EventBadges;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Event officials\' badges')]
#[Description('For an event you manage: every official\'s badge (A6, the event\'s badge design) — name, role, club and whether a photo will print — and the print-shop file: every badge four to an A4 sheet with cut lines, in one PDF. With build=true it starts building that file in the background (or finds it built); the answer says idle / building / ready with the address the event console downloads it from. Badges are drawn from the officials as they stand: nothing is approved or stored per person.')]
class EventBadgesTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'build' => $schema->boolean()->description('Start building the print-shop PDF if it is not built or building yet.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        if (! config('certificates.enabled')) {
            return Response::error('Certificates are not switched on.');
        }
        $v = $request->validate(['event' => 'required|uuid', 'build' => 'sometimes|boolean']);
        $badges = app(EventBadges::class);
        $event = $badges->find($v['event']);
        if (! $event || ! $badges->canManage($event, $user)) {
            return Response::error('Event not found.');
        }
        $locale = $badges->locale($event);
        $store = app(CertificateBundles::class);
        $sheet = ($v['build'] ?? false) ? $store->start($badges, $event, EventBadges::TYPE, $user->id) : $store->state($badges, $event, EventBadges::TYPE);
        if ($v['build'] ?? false) {
            Log::info('badges.bundle_requested', ['event' => $event->uuid, 'by' => $user->id, 'via' => 'mcp']);
        }

        return Response::json([
            'event' => $event->uuid,
            'language' => $locale,
            'badges' => $badges->officials($event)->map(function ($o) use ($badges, $locale) {
                $f = $badges->fields($o, $locale, false);

                return ['name' => $f['name'], 'role' => $f['role'], 'club' => $f['club'], 'has_photo' => $f['photo'] !== null];
            })->values(),
            'print_file' => $sheet + [
                'download_url' => $sheet['state'] === 'ready' ? route('me.certificates.bundle.download', ['context' => EventBadges::ALIAS, 'key' => $event->uuid, 'type' => EventBadges::TYPE]) : null,
            ],
        ]);
    }
}
