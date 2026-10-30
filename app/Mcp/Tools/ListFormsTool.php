<?php

namespace App\Mcp\Tools;

use App\Forms\Models\Form;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/** Every form and questionnaire on the platform (app/Forms) — super-admin only, like /admin/forms. */
#[Title('List forms')]
#[Description('List every form / questionnaire on the platform: uuid, title (English and Arabic), type, open or closed, owner (a club, or the platform), number of questions and pages, number of answers and when the last one came, and its public link. Super-admin only. Use get_form_results with the uuid for the answers and analytics.')]
class ListFormsTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        if (! $user->isSuperAdmin()) {
            return Response::error('Only a platform super-admin may read forms.');
        }

        $forms = Form::query()->with('tenant:id,club_name')->withCount('submissions')->withMax('submissions', 'created_at')
            ->latest('id')->limit(200)->get()
            ->map(fn (Form $f) => [
                'uuid' => $f->uuid,
                'title' => $f->title,
                'title_ar' => $f->settings['title_ar'] ?? null,
                'type' => $f->type,
                'open' => (bool) $f->is_active,
                'owner' => $f->tenant?->club_name ?? 'platform',
                'questions' => count($f->fields()),
                'pages' => count($f->schema['pages'] ?? []),
                'answers' => (int) $f->submissions_count,
                'last_answer_at' => $f->submissions_max_created_at ? \Illuminate\Support\Carbon::parse($f->submissions_max_created_at)->toIso8601String() : null,
                'public_url' => route('forms.show', $f->uuid),
            ])->values();

        return Response::json(['success' => true, 'count' => $forms->count(), 'forms' => $forms]);
    }
}
