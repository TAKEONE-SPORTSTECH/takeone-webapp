<?php

namespace App\DevBoard\Mcp\Tools;

use App\DevBoard\Services\Folders;
use App\Members\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Update note')]
#[Description('Change a note in your TakeOne notebook: replace its text, or append to the end, from Markdown; and/or move it to a folder. Pass the version from get_note — if somebody saved since, nothing is changed and you are told to read it again.')]
class UpdateNoteTool extends NotesTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'uuid' => $schema->string()->required()->description('The note\'s uuid.'),
            'version' => $schema->integer()->min(0)->required()->description('The version get_note returned.'),
            'markdown' => $schema->string()->description('New text in Markdown. Leave out to change only the folder.'),
            'mode' => $schema->string()->enum(['replace', 'append'])->description('"replace" (default) swaps the whole note; "append" adds the Markdown to the end.'),
            'folder' => $schema->string()->description('Move the note to this folder uuid; send "none" to take it out of any folder.'),
        ];
    }

    protected function work(Request $request, User $user): Response
    {
        $note = $this->findNote($request->get('uuid'));
        if (! $note) {
            return Response::error('No note with that uuid in your notebook.');
        }

        $folder = false; // false: leave it where it is
        $want = $request->get('folder');
        if ($want === 'none') {
            $folder = null;
        } elseif ($want !== null && $want !== '') {
            $folder = $this->findFolder($want);
            if (! $folder) {
                return Response::error('No folder with that uuid in your notebook.');
            }
        }

        $markdown = $request->get('markdown');
        if ($markdown !== null) {
            $markdown = (string) $markdown;
            if (strlen($markdown) > $this->maxMarkdown()) {
                return Response::error('The note is too long.');
            }
            $mode = $request->get('mode', 'replace');
            if (! in_array($mode, ['replace', 'append'], true)) {
                return Response::error('mode must be "replace" or "append".');
            }
            $added = trim($markdown) === '' ? '' : (string) $this->fromMarkdown($markdown);
            $html = $mode === 'append'
                ? ($this->notes()->page($note)?->body ?? '').$added
                : $added;
            if (trim(strip_tags($html, '<img><table>')) === '') {
                return Response::error('That would leave the note empty.');
            }
            if (strlen($html) > $this->maxMarkdown()) {
                return Response::error('The note would be too long.');
            }

            $version = (int) $request->get('version', -1);
            if (! $this->notes()->write($note, $user, $version, $html)) {
                return Response::error('The note was changed since you read it (your version is out of date). Call get_note again and retry.');
            }
        }

        if ($folder !== false) {
            app(Folders::class)->file($note, $folder);
        }

        $note->refresh()->load('folder:id,uuid');

        return Response::json([
            'updated' => true,
            'note' => $this->summary($note),
            'version' => $this->notes()->page($note)?->version ?? 0,
        ]);
    }
}
