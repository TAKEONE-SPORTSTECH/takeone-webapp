<?php

namespace App\Events\Support;

/**
 * The doors out of an event's PUBLIC page — Draw, Table, Officials, Gallery,
 * Participants, Results — as `partials.event-doors` draws them.
 *
 * One list, so the public poster (`entry/public/mobile`) and a federation's
 * club invitation built on the same event (`federations::invite.show`) show
 * the same buttons and cannot drift apart. It was written inline in the poster
 * first; this is that block, unchanged, moved where both can call it.
 *
 * A door only exists when there is something behind it: no draw for an event
 * that runs none, no table unless the owning package publishes one, no winners
 * until somebody has won. Gallery, Officials and Participants are always doors
 * — they are places people go looking for before anything is in them.
 */
final class EventDoors
{
    /**
     * @param  array  $e  the PublicEvent payload of the event the doors lead into
     * @return array<string, array<string, string>>
     */
    public static function public(array $e, EventAddress $addr): array
    {
        $section = fn (string $s) => $addr->section($s);
        $doors = [];

        if (! empty($e['divisions']) || ($e['draw']['published'] ?? false)) {
            $drawn = ($e['draw']['published'] ?? false);
            $doors['draw'] = [
                'href' => $section('draw'),
                'icon' => 'bi-diagram-3-fill bracket-icon',
                'label' => __('personal.event_show_tile_draw'),
                'sub' => $drawn
                    ? trans_choice('events::events.public_draw_competitors', $e['draw']['entrants'], ['n' => $e['draw']['entrants']])
                    : __('events::events.public_draw_empty_title'),
            ];
        }

        if (($e['type_public']['key'] ?? null) === 'table') {
            $doors['table'] = [
                'href' => $section('table'),
                'icon' => $e['type_public']['icon'] ?? 'bi-table',
                'label' => $e['type_public']['label'] ?? '',
                'sub' => $e['type_public']['sub'] ?? '',
            ];
        }

        $doors['officials'] = [
            'href' => $section('officials'),
            'icon' => 'bi-person-badge-fill',
            'label' => __('personal.event_show_tile_officials'),
            'sub' => ($e['officials']['count'] ?? 0) > 0
                ? trans_choice('personal.event_manage_officials_count', $e['officials']['count'], ['count' => $e['officials']['count']])
                : __('events::events.public_officials_empty_title'),
        ];

        $doors['gallery'] = [
            'href' => $section('gallery'),
            'icon' => 'bi-camera-reels-fill',
            'label' => __('events::events.bout_gallery_title'),
            'sub' => ($e['gallery']['count'] ?? 0) > 0
                ? trans_choice('events::events.bout_gallery_count', $e['gallery']['count'], ['count' => $e['gallery']['count']])
                : __('events::events.bout_gallery_none'),
        ];

        $doors['participants'] = [
            'href' => $section('participants'),
            'icon' => 'bi-people-fill',
            'label' => __('personal.event_show_tile_participants'),
            'sub' => ($e['participants']['count'] ?? 0) > 0
                ? trans_choice('events::events.public_draw_competitors', $e['participants']['count'], ['n' => $e['participants']['count']])
                : __('events::events.public_participants_empty_title'),
        ];

        if (($e['results']['count'] ?? 0) > 0) {
            $doors['results'] = [
                'href' => $section('results'),
                'icon' => 'bi-trophy-fill',
                'label' => __('personal.event_show_results_medals'),
                'sub' => trans_choice('personal.event_show_results_divisions', $e['results']['count'], ['count' => $e['results']['count']]),
                // Gold, because it is the last thing that happens at an event.
                'style' => 'background: linear-gradient(135deg, #eab308, #78350f);',
            ];
        }

        return $doors;
    }
}
