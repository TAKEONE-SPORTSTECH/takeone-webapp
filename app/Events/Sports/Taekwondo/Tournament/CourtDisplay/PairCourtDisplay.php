<?php

namespace App\Events\Sports\Taekwondo\Tournament\CourtDisplay;

use App\Models\ClubEvent;
use App\Models\EventMatch;
use Illuminate\Console\Command;

/**
 * Pair a hall screen from the terminal.
 *
 * The organiser's real flow is scan-the-QR from the event's court screen; this
 * is the same issuance underneath, reachable before that UI exists — and it
 * stays useful afterwards for bench-testing a Pi without a phone.
 */
class PairCourtDisplay extends Command
{
    protected $signature = 'court:pair
        {event? : The event uuid}
        {court? : The mat, exactly as it appears on the draw (e.g. "Mat 1")}
        {--new : Create an UNPAIRED screen — it shows the QR pairing code, as a fresh Pi does}
        {--label= : A name for this screen, so it can be told apart later}
        {--list : List this event\'s paired screens instead of adding one}
        {--revoke= : Revoke the screen with this id}
        {--prune : Delete screens that enrolled but were never claimed}';

    protected $description = 'Issue (or revoke) a court-display token for a Raspberry Pi hall screen';

    public function handle(): int
    {
        // A fresh Pi has no event and no mat — it stands there showing its code.
        if ($this->option('new')) {
            return $this->unpaired();
        }

        if ($this->option('prune')) {
            return $this->prune();
        }

        $event = ClubEvent::where('uuid', $this->argument('event'))->first();

        if (! $event) {
            $this->error('No event with that uuid.');

            return self::FAILURE;
        }

        if ($id = $this->option('revoke')) {
            return $this->revoke($event, (int) $id);
        }

        if ($this->option('list')) {
            return $this->list($event);
        }

        if (! $this->argument('court')) {
            $this->error('A mat is required — or pass --new for an unpaired screen.');

            return self::FAILURE;
        }

        return $this->pair($event, (string) $this->argument('court'));
    }

    /**
     * Clear out screens that enrolled and were never claimed.
     *
     * Enrolment is open (a fresh Pi has no credential to offer), so this is the
     * other half of that trade: an unclaimed row grants access to nothing, and
     * anything abandoned for a day is swept up rather than accumulating.
     * Deliberately never touches a claimed screen, however long it has been dark
     * — a Pi in a store cupboard between events must come back to its own mat.
     */
    private function prune(): int
    {
        $stale = CourtDisplayDevice::whereNull('claimed_at')
            ->where('created_at', '<', now()->subDay())
            ->get();

        $stale->each->delete();

        $this->info('Pruned '.$stale->count().' unclaimed screen(s).');

        return self::SUCCESS;
    }

    /** A screen that does not yet know what it is — exactly a Pi's first boot. */
    private function unpaired(): int
    {
        ['device' => $device, 'token' => $token] = CourtDisplayDevice::begin($this->option('label') ?: null);

        $this->info('Unpaired screen created (id '.$device->id.').');
        $this->newLine();
        $this->line('  Open this to see the QR pairing screen:');
        $this->line('  '.route('court-display.board', $token));
        $this->newLine();
        $this->line('  Its QR points an organiser at:');
        $this->line('  '.route('court-display.claim', $device->pairing_code));
        $this->line('  Pairing code: '.$device->pairing_code);

        return self::SUCCESS;
    }

    private function pair(ClubEvent $event, string $court): int
    {
        // A typo'd mat name pairs a screen to a board that will never have a
        // bout on it, and the failure only shows up on competition morning.
        $known = EventMatch::where('event_id', $event->id)
            ->whereNotNull('court')->distinct()->pluck('court');

        if ($known->isNotEmpty() && ! $known->contains($court)) {
            $this->warn('This event has no bouts on "'.$court.'". Known mats: '.$known->implode(', '));

            if (! $this->confirm('Pair anyway?', false)) {
                return self::FAILURE;
            }
        }

        ['device' => $device, 'token' => $token] = CourtDisplayDevice::issue(
            $event, $court, null, $this->option('label') ?: null
        );

        $this->info('Screen paired — '.$event->title.' · '.$court);
        $this->newLine();
        $this->line('  '.route('court-display.board', $token));
        $this->newLine();
        $this->comment('This is the only time the token is shown. It is stored hashed;');
        $this->comment('losing it means pairing again (screen id '.$device->id.').');

        return self::SUCCESS;
    }

    private function list(ClubEvent $event): int
    {
        $rows = CourtDisplayDevice::where('event_id', $event->id)->orderBy('court')->get();

        if ($rows->isEmpty()) {
            $this->comment('No screens paired to this event yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'court', 'label', 'token', 'last seen', 'revoked'],
            $rows->map(fn (CourtDisplayDevice $d) => [
                $d->id,
                $d->court,
                $d->label ?: '—',
                // Enough to identify the screen, never enough to use it.
                $d->getAttributes()['token_hint'].'…',
                $d->last_seen_at?->diffForHumans() ?: 'never',
                $d->revoked_at?->toDateTimeString() ?: '—',
            ])->all()
        );

        return self::SUCCESS;
    }

    private function revoke(ClubEvent $event, int $id): int
    {
        $device = CourtDisplayDevice::where('event_id', $event->id)->find($id);

        if (! $device) {
            $this->error('No screen with id '.$id.' on this event.');

            return self::FAILURE;
        }

        $device->revoke();
        $this->info('Screen '.$id.' revoked. It stops resolving immediately.');

        return self::SUCCESS;
    }
}
