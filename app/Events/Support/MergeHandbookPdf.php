<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Members\Models\User;

/**
 * The officials' handbook on merging small divisions, as an A4 PDF — the same
 * "Fight night" navy and gold as the draw, entry-list and registration-form
 * PDFs, so it sits with them on the organiser's desk.
 *
 * One handbook for every event, whoever hosts it. The rules, the reasoning and
 * the published sources are the same for all; what is particular to THIS event
 * is its last-but-one chapter, "This event", read live from DivisionMerge — the
 * small divisions, the merges the tool would allow and the athletes who stay
 * alone, each with its reason. The limits printed are the limits in force
 * (config('events.division_merge')), never retyped.
 *
 * Text source: Documentation/DIVISION-MERGING.md and the online handbook it
 * links — keep the three in step.
 *
 * Printed through BracketPdf's browser (Shared Stays Shared). The event chapter
 * names divisions only, never a person, but it is still a headless browser per
 * request, so the route holds it to the event's staff and the PDF limiter.
 */
class MergeHandbookPdf
{
    public function __construct(private BracketPdf $pdf, private DivisionMerge $merge) {}

    public static function available(): bool
    {
        return DivisionMerge::enabled() && BracketPdf::available();
    }

    public function html(ClubEvent $event, User $viewer): string
    {
        $host = $event->host();
        $plan = $this->merge->suggest($event);

        return view('events::merge-handbook-pdf', [
            'title' => (string) $event->title,
            'date' => $event->date ? \Illuminate\Support\Carbon::parse($event->date)->format('j M Y') : null,
            'host' => (string) ($host->name ?? ''),
            'hostLogo' => ($logo = $host->logo) ? $this->pdf->dataUri($logo, $viewer, original: true, maxPx: 320) : null,
            'limits' => $plan['limits'],
            'plan' => $plan,
            'printed' => now()->format('j M Y'),
        ])->render();
    }

    public function render(string $html): string
    {
        return $this->pdf->render($html);
    }
}
