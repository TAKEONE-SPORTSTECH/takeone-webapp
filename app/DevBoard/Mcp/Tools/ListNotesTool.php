<?php

namespace App\DevBoard\Mcp\Tools;

use App\DevBoard\Models\Card;
use App\Members\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('List notes')]
#[Description('List the notes in your TakeOne notebook (/me/notes), newest first, with a short snippet of each. Optionally search the title and text, or limit to one folder. Use get_note to read a note in full.')]
#[IsReadOnly]
class ListNotesTool extends NotesTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Words to look for in a note\'s title or text (case-insensitive).'),
            'folder' => $schema->string()->description('A folder uuid (from list_note_folders) to list only that folder\'s notes.'),
            'page' => $schema->integer()->min(1)->description('1-based page number. Defaults to 1.'),
            'per_page' => $schema->integer()->min(1)->description('Results per page. Capped by the server (default 25).'),
        ];
    }

    protected function work(Request $request, User $user): Response
    {
        $perPage = $this->pageSize($request->get('per_page'));
        $page = max(1, (int) ($request->get('page') ?: 1));
        $q = mb_substr(trim((string) $request->get('query', '')), 0, 200);

        $notes = Card::query()->inNotebook()->with('folder:id,uuid');

        if ($request->get('folder') !== null && $request->get('folder') !== '') {
            $folder = $this->findFolder($request->get('folder'));
            if (! $folder) {
                return Response::error('No folder with that uuid in your notebook.');
            }
            $notes->where('folder_id', $folder->id);
        }

        if ($q !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
            $notes->where(fn ($w) => $w->whereRaw('LOWER(title) LIKE ?', [$like])
                ->orWhereHas('items', fn ($i) => $i->where('type', 'note')->whereRaw('LOWER(body) LIKE ?', [$like])));
        }

        $total = (clone $notes)->count();
        $rows = $notes->latest('updated_at')->forPage($page, $perPage)->get();

        return Response::json([
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'notes' => $rows->map(fn (Card $c) => $this->summary($c))->values()->all(),
        ]);
    }
}
