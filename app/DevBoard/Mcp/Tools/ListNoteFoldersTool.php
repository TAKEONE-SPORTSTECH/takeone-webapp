<?php

namespace App\DevBoard\Mcp\Tools;

use App\DevBoard\Models\Folder;
use App\Members\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('List note folders')]
#[Description('List the folders in your TakeOne notebook (uuid, name, parent folder), to file notes or list one folder\'s notes.')]
#[IsReadOnly]
class ListNoteFoldersTool extends NotesTool
{
    protected function work(Request $request, User $user): Response
    {
        $folders = Folder::query()->orderBy('position')->get(['id', 'uuid', 'name', 'parent_id']);
        $uuids = $folders->pluck('uuid', 'id');

        return Response::json([
            'folders' => $folders->map(fn (Folder $f) => [
                'uuid' => $f->uuid,
                'name' => $f->name,
                'parent' => $f->parent_id ? ($uuids[$f->parent_id] ?? null) : null,
            ])->values()->all(),
        ]);
    }
}
