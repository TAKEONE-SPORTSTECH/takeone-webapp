<?php

namespace App\DevBoard\Mcp\Tools;

use App\DevBoard\Mcp\NotesContext;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Folder;
use App\DevBoard\Services\NoteHtml;
use App\DevBoard\Services\TakeNote;
use App\Mcp\Tools\BaseTool;
use App\Members\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Base for the notes tools: resolves the acting user, applies the notebook
 * gates, and runs the tool's body inside that person's notebook only.
 */
abstract class NotesTool extends BaseTool
{
    /** The tool's work, already inside the caller's notebook. */
    abstract protected function work(Request $request, User $user): Response;

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        if ($why = NotesContext::refusal($user)) {
            return Response::error($why);
        }

        return NotesContext::run($user, fn () => $this->work($request, $user));
    }

    protected function notes(): TakeNote
    {
        return app(TakeNote::class);
    }

    /** A live note of the current notebook by uuid, or null — another notebook's uuid looks exactly like a missing one. */
    protected function findNote(?string $uuid): ?Card
    {
        if (! is_string($uuid) || ! preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
            return null;
        }

        return Card::query()->inNotebook()->where('uuid', $uuid)->first();
    }

    protected function findFolder(?string $uuid): ?Folder
    {
        if (! is_string($uuid) || ! preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
            return null;
        }

        return Folder::query()->where('uuid', $uuid)->first();
    }

    /** What a list shows of one note: never the page body. */
    protected function summary(Card $c, ?string $body = null): array
    {
        [, $snippet] = NoteHtml::parts($body ?? $this->notes()->page($c)?->body, 160);

        return [
            'uuid' => $c->uuid,
            'title' => $c->title,
            'folder' => $c->folder?->uuid,
            'updated_at' => $c->updated_at?->toIso8601String(),
            'snippet' => $snippet,
        ];
    }

    /** A note's page as readable text: one line per block, list items as "- ", table cells split by " | ". */
    protected function text(?string $html): string
    {
        if (! $html) {
            return '';
        }
        $t = preg_replace('/<li\b[^>]*>/i', "\n- ", $html);
        $t = preg_replace('/<h([1-6])\b[^>]*>/i', "\n\n", $t);
        $t = preg_replace('/<(\/?(p|div|h[1-6]|ul|ol|blockquote|pre|table|tr)|br|hr)\b[^>]*>/i', "\n", $t);
        $t = preg_replace('/<\/t[dh]>/i', ' | ', $t);
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/[ \t]+\n/", "\n", $t);

        return trim(preg_replace("/\n{3,}/", "\n\n", $t));
    }

    /** Markdown in, a note page out — raw HTML stripped and unsafe links refused (NoteHtml::fromMarkdown). */
    protected function fromMarkdown(string $markdown): ?string
    {
        return NoteHtml::fromMarkdown($markdown);
    }

    protected function maxMarkdown(): int
    {
        return (int) config('devboard.limits.note_html', 200000);
    }
}
