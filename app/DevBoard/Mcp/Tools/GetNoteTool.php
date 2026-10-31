<?php

namespace App\DevBoard\Mcp\Tools;

use App\Members\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Get note')]
#[Description('Read one note from your TakeOne notebook in full: its title, folder, the text, the formatted HTML, and its version (pass the version back to update_note so you never overwrite a newer edit).')]
#[IsReadOnly]
class GetNoteTool extends NotesTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'uuid' => $schema->string()->required()->description('The note\'s uuid (from list_notes).'),
        ];
    }

    protected function work(Request $request, User $user): Response
    {
        $note = $this->findNote($request->get('uuid'));
        if (! $note) {
            return Response::error('No note with that uuid in your notebook.');
        }
        $note->loadMissing('folder:id,uuid,name');
        $page = $this->notes()->page($note);

        return Response::json([
            'uuid' => $note->uuid,
            'title' => $note->title,
            'folder' => $note->folder ? ['uuid' => $note->folder->uuid, 'name' => $note->folder->name] : null,
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
            'version' => $page?->version ?? 0,
            'text' => $this->text($page?->body),
            'html' => $page?->body ?? '',
        ]);
    }
}
