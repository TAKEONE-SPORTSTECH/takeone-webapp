<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventRunSlot;
use App\Events\Support\EventAccess;
use App\Events\Support\RunSessions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Running order groups')]
#[Description('Read the GROUPS of an event day\'s running order, or set how one group calls and numbers its bouts on the mat. A group is a heading on the running order: the weight classes listed under it (until the next group) finish all their bouts before the next group starts. Methods: "class" (one class at a time — each weight class runs to its final before the next), "round" (round by round, World Taekwondo: every class\'s earliest round first, the finals last and together), "manual" (the group keeps the bout order set by hand on the bout-order board). Pass divisions (weight-class names) to put those classes into the group — moved there if they are already on the day, added if not. Omit mode and divisions to just read the groups. Organiser only. Bouts are not renumbered once anything has been fought.')]
class SetRunningOrderGroupTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event uuid.'),
            'day' => $schema->integer()->description('Competition day, 1-based. Default 1.'),
            'group' => $schema->string()->description('The group\'s uuid (from a read). Required with mode.'),
            'mode' => $schema->string()->description('"class", "round" or "manual". Omit to read only.'),
            'divisions' => $schema->array()->items($schema->string())->description('Weight-class names to put into the group. Requires group.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        if (! RunSessions::enabled()) {
            return Response::error('Running order groups are not switched on.');
        }

        $validated = $request->validate([
            'event' => 'required|string',
            'day' => 'nullable|integer|min:1|max:60',
            'group' => 'nullable|uuid',
            'mode' => 'nullable|in:'.implode(',', RunSessions::MODES),
            'divisions' => 'nullable|array|max:100',
            'divisions.*' => 'string|max:160',
        ]);

        $event = ClubEvent::where('uuid', $validated['event'])->first();

        if (! $event || ! app(EventAccess::class)->visible($event, $user)) {
            return Response::error('Event not found.');
        }

        if (! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Only the event\'s organiser can change how its groups run.');
        }

        $day = (int) ($validated['day'] ?? 1);

        if (! empty($validated['mode']) || ! empty($validated['divisions'])) {
            $group = ! empty($validated['group'])
                ? EventRunSlot::where('event_id', $event->id)->where('day', $day)
                    ->where('kind', EventRunSlot::KIND_GROUP)->where('uuid', $validated['group'])->first()
                : null;

            if (! $group) {
                return Response::error('No such group on that day.');
            }

            if (! empty($validated['mode'])) {
                RunSessions::set($event, $group, $validated['mode']);
            }

            $order = app(\App\Events\Support\RunOrder::class);
            foreach ($validated['divisions'] ?? [] as $name) {
                $category = \App\Events\Models\EventCategory::where('event_id', $event->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
                if (! $category) {
                    return Response::error('No division called "'.$name.'" in this event.');
                }
                $slot = EventRunSlot::where('event_id', $event->id)->where('day', $day)->where('category_id', $category->id)->first();
                $slot
                    ? $order->intoGroup($event, $day, [$slot->uuid], $group)
                    : $order->addDivisions($event, [$category->id], $day, false, $group);
            }

            RunSessions::renumberIfUnfought($event->fresh());
        }

        return Response::json(['day' => $day, 'groups' => RunSessions::describe($event->fresh(), $day)]);
    }
}
