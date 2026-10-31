<?php

namespace App\DevBoard\Mcp\Tools;

use App\DevBoard\Services\Folders;
use App\Members\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Create note')]
#[Description('Write a new note in your TakeOne notebook from Markdown (headings, lists, checklists "- [ ]", tables, quotes, code, links). The first line becomes the title. Raw HTML in the Markdown is removed; pictures become links.')]
class CreateNoteTool extends NotesTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'markdown' => $schema->string()->required()->description('The note, in Markdown. Start with a "# Title" line.'),
            'folder' => $schema->string()->description('Optional folder uuid (from list_note_folders) to file the note in.'),
        ];
    }

    protected function work(Request $request, User $user): Response
    {
        $markdown = (string) $request->get('markdown', '');
        if (trim($markdown) === '') {
            return Response::error('The note is empty.');
        }
        if (strlen($markdown) > $this->maxMarkdown()) {
            return Response::error('The note is too long.');
        }

        $folder = null;
        if ($request->get('folder') !== null && $request->get('folder') !== '') {
            $folder = $this->findFolder($request->get('folder'));
            if (! $folder) {
                return Response::error('No folder with that uuid in your notebook.');
            }
        }

        $html = $this->fromMarkdown($markdown);
        if ($html === null) {
            return Response::error('Nothing in that Markdown could be kept as a note.');
        }

        $card = $this->notes()->create($user, $html);
        if ($folder) {
            app(Folders::class)->file($card, $folder);
        }
        $card->refresh()->load('folder:id,uuid');

        return Response::json(['created' => true, 'note' => $this->summary($card)]);
    }
}
