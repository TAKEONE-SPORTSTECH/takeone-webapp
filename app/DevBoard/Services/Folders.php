<?php

namespace App\DevBoard\Services;

use App\DevBoard\Models\Card;
use App\DevBoard\Models\Folder;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The notebook's folders: a tree (a folder may sit in another, at most
 * Folder::MAX_DEPTH deep), shared by everyone on the board. Deleting a folder
 * never deletes a note: what was in it moves up a level — its notes and its
 * folders go to its parent (or to no folder at the top).
 */
class Folders
{
    public const COLORS = ['#7c6bf5', '#e0457b', '#e89a1c', '#16a394', '#3b9ae1', '#22a06b', '#c9372c', '#6b6280'];

    public function all(): array
    {
        $ids = Folder::query()->pluck('uuid', 'id');
        // the owner sees which of their folders are shared, and with how many people
        $shared = \App\DevBoard\Support\Notebooks::sharingOn() && \App\DevBoard\Support\Notebooks::fence() === null && \App\DevBoard\Support\Notebooks::currentId()
            ? \App\DevBoard\Models\FolderShare::live()->where('notebook_id', \App\DevBoard\Support\Notebooks::currentId())
                ->selectRaw('folder_id, count(*) as n')->groupBy('folder_id')->pluck('n', 'folder_id')->all()
            : [];

        return Folder::query()->orderBy('position')->get()
            ->map(fn (Folder $f) => $this->payload($f, $ids) + ['shared' => (int) ($shared[$f->id] ?? 0)])->values()->all();
    }

    public function payload(Folder $f, $ids = null): array
    {
        return [
            'uuid' => $f->uuid,
            'name' => $f->name,
            'color' => $f->color,
            'parent' => $f->parent_id ? ($ids[$f->parent_id] ?? Folder::whereKey($f->parent_id)->value('uuid')) : null,
            'position' => $f->position,
        ];
    }

    public function create(User $by, string $name, ?Folder $parent, ?string $color): ?Folder
    {
        if ($parent && $this->depth($parent) >= Folder::MAX_DEPTH) {
            return null;
        }
        $last = Folder::where('parent_id', $parent?->id)->max('position');

        return Folder::create([
            'uuid' => (string) Str::uuid(),
            'parent_id' => $parent?->id,
            'name' => $this->clean($name),
            'color' => in_array($color, self::COLORS, true) ? $color : self::COLORS[Folder::count() % count(self::COLORS)],
            'position' => ($last ?? 0) + 1024,
            'created_by' => $by->id,
        ]);
    }

    /**
     * Rename, recolour, or move under another folder (null = the top). False
     * when the move would put a folder inside itself or too deep.
     *
     * @param  array{name?: string, color?: string, parent?: ?Folder, position?: float}  $data
     */
    public function update(Folder $folder, array $data): bool
    {
        if (array_key_exists('parent', $data)) {
            $parent = $data['parent'];
            if ($parent && ($parent->id === $folder->id || $this->isInside($parent, $folder)
                || $this->depth($parent) + $this->height($folder) > Folder::MAX_DEPTH)) {
                return false;
            }
            $folder->parent_id = $parent?->id;
        }
        if (isset($data['name'])) {
            $folder->name = $this->clean($data['name']);
        }
        if (isset($data['color']) && in_array($data['color'], self::COLORS, true)) {
            $folder->color = $data['color'];
        }
        if (isset($data['position'])) {
            $folder->position = (float) $data['position'];
        }
        $folder->save();

        return true;
    }

    /** The folder goes; what was in it moves up a level. Nothing is deleted with it. */
    public function delete(Folder $folder): void
    {
        DB::transaction(function () use ($folder) {
            Card::withTrashed()->where('folder_id', $folder->id)->update(['folder_id' => $folder->parent_id]);
            Folder::where('parent_id', $folder->id)->update(['parent_id' => $folder->parent_id]);
            $folder->delete();
        });
    }

    /** A note goes into a folder, or into none. */
    public function file(Card $note, ?Folder $folder): void
    {
        $note->forceFill(['folder_id' => $folder?->id])->save();
    }

    /** 1 for a folder at the top, 2 for one inside it, … */
    private function depth(Folder $f): int
    {
        $d = 1;
        while ($f->parent_id && $d <= Folder::MAX_DEPTH + 1) {
            $f = Folder::find($f->parent_id);
            if (! $f) {
                break;
            }
            $d++;
        }

        return $d;
    }

    /** How many levels a folder carries, itself included. */
    private function height(Folder $f, int $guard = 0): int
    {
        if ($guard > Folder::MAX_DEPTH + 1) {
            return $guard;
        }
        $max = 0;
        foreach (Folder::where('parent_id', $f->id)->get() as $c) {
            $max = max($max, $this->height($c, $guard + 1));
        }

        return 1 + $max;
    }

    /** Is $maybe somewhere inside $folder? */
    private function isInside(Folder $maybe, Folder $folder): bool
    {
        $f = $maybe;
        for ($i = 0; $f && $f->parent_id && $i <= Folder::MAX_DEPTH + 1; $i++) {
            if ($f->parent_id === $folder->id) {
                return true;
            }
            $f = Folder::find($f->parent_id);
        }

        return false;
    }

    private function clean(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? '');

        return mb_substr($name !== '' ? $name : 'Folder', 0, 80);
    }
}
