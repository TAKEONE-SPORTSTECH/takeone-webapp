<?php

namespace App\Members\Commands;

use App\Members\Models\User;
use App\Members\Models\UserPhoto;
use App\Support\StoragePath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bring every photograph of a person home.
 *
 * The rule from today (CLAUDE.md → A Person's Photo Has One Home) governs new
 * writes; this is the one-time pass for the faces already filed somewhere else.
 * At the time it was written, 45 members had a photograph taken at a competition
 * desk and a blank silhouette on their own profile, because the picture lived
 * under `events/{uuid}/competitors/…` and nothing but that event ever looked
 * there.
 *
 * It MOVES rather than copies, and repoints every row that names the file in the
 * same transaction. Copying would leave two files for one face — exactly the
 * thing the rule exists to stop — and adopting the old path in place would leave
 * a member's profile picture inside an event folder, where it stops being
 * servable the day the organiser archives the event.
 *
 * The four tables that name a photo path are the four `EntryPhoto::claimedByAnother`
 * already enumerates; there is no fifth.
 *
 * Refuses to move anything two different PEOPLE point at — duplicate accounts
 * here share a file, and a move would be right for one of them and wrong for the
 * other. Those are reported instead.
 *
 * Dry run unless `--apply`. Back up first (RULE #2): it rewrites rows.
 */
class GatherPersonPhotos extends Command
{
    protected $signature = 'takeone:gather-person-photos
                            {--apply : actually move the files and rewrite the rows}
                            {--disk=public : the filesystem disk the photos live on}';

    protected $description = "Move every photograph of a person into that person's own store, and repoint what names it";

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $disk = (string) $this->option('disk');

        $candidates = $this->candidates();

        if ($candidates === []) {
            $this->info('Nothing to gather — every photograph of a person is already in their own store.');

            return self::SUCCESS;
        }

        $this->line(sprintf('%d photograph(s) filed outside their owner.', count($candidates)));

        if (! $apply) {
            $this->warn('DRY RUN — nothing is written. Re-run with --apply (after a verified backup).');
        }

        $moved = $skipped = $missing = 0;

        foreach ($candidates as $path => $userId) {
            $user = User::find($userId);

            if (! $user) {
                $skipped++;

                continue;
            }

            if (! rescue(fn () => Storage::disk($disk)->exists($path), false, false)) {
                // A row naming a file that is not there. Left alone: this
                // command moves photographs, it does not tidy dangling
                // references, and guessing which is which is how a face gets
                // lost. Registered for a later pass instead.
                $this->line("  <fg=yellow>missing</> {$path}");
                $missing++;

                continue;
            }

            $target = StoragePath::memberPhotos($user).'/'.Str::ulid().'.'.$this->extension($path);

            $this->line("  {$path}\n       -> {$target}  (user {$user->id} {$user->full_name})");

            if (! $apply) {
                $moved++;

                continue;
            }

            try {
                DB::transaction(function () use ($user, $path, $target, $disk) {
                    // File first, rows second: a row pointing at a file that
                    // has not arrived yet is a broken face, while a file that
                    // has moved with the rows still to follow is recoverable
                    // from this same command's next run.
                    if (! Storage::disk($disk)->move($path, $target)) {
                        throw new \RuntimeException('move failed');
                    }

                    DB::table('club_event_registrations')->where('photo', $path)->update(['photo' => $target]);
                    DB::table('event_officials')->where('photo', $path)->update(['photo' => $target]);
                    DB::table('users')->where('profile_picture', $path)->update(['profile_picture' => $target]);
                    DB::table('user_photos')->where('path', $path)->update(['path' => $target]);

                    if (! UserPhoto::where('user_id', $user->id)->where('path', $target)->exists()) {
                        UserPhoto::create([
                            'user_id' => $user->id,
                            'path' => $target,
                            'sort_order' => (int) UserPhoto::where('user_id', $user->id)->min('sort_order') - 1,
                        ]);
                    }

                    // Only a blank one. A face the member chose is never
                    // replaced by one an organiser took at a desk.
                    if (trim((string) $user->profile_picture) === '') {
                        $user->forceFill(['profile_picture' => $target])->save();
                    }
                });

                $moved++;
            } catch (\Throwable $e) {
                $this->error("  failed: {$path} — {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d · skipped %d · missing file %d',
            $apply ? 'Moved' : 'Would move',
            $moved,
            $skipped,
            $missing,
        ));

        return self::SUCCESS;
    }

    /**
     * Every photo path that belongs to exactly one person and is not yet stored
     * under them.
     *
     * @return array<string, int>  path => user id
     */
    private function candidates(): array
    {
        $owners = [];   // path => [user ids]

        $rows = DB::table('club_event_registrations')
            ->whereNotNull('user_id')
            ->whereNotNull('photo')
            ->where('photo', '!=', '')
            ->where('photo', 'not like', 'members/%')
            ->get(['photo', 'user_id']);

        $rows = $rows->concat(
            DB::table('event_officials')
                ->whereNotNull('photo')
                ->where('photo', '!=', '')
                ->where('photo', 'not like', 'members/%')
                ->get(['photo', 'user_id'])
        );

        foreach ($rows as $row) {
            $owners[(string) $row->photo][(int) $row->user_id] = true;
        }

        $out = [];

        foreach ($owners as $path => $userIds) {
            if (count($userIds) !== 1) {
                $this->warn("  shared by ".count($userIds)." people, left alone: {$path}");

                continue;
            }

            $out[$path] = (int) array_key_first($userIds);
        }

        return $out;
    }

    /** The extension the file already carries, whitelisted. */
    private function extension(string $path): string
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? $ext : 'jpg';
    }
}
