<?php

namespace App\Mcp\Tools;

use App\Forms\Models\Form;
use App\Forms\Support\SurveyAnalytics;
use App\Forms\Support\SurveyResponses;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * One form's results — the same numbers /admin/forms/{uuid} draws, and the
 * same proposed decisions. Super-admin only. The visit link is never returned.
 */
#[Title('Get form results')]
#[Description('The analytics of one form: answer count, per-question results (choice counts and percentages, 1–5 scale averages and spread, number medians, Must/Nice/No grid scores, top-N ranking points, latest free-text answers), what stands out, and — for a form with decision settings — proposed decisions in lanes now / next / later / show (score = (Must + 0.4 × Nice) ÷ answers × 100; priority = score × gap ÷ effort). Pass responses=true to also get each answer in full (latest 50). Super-admin only.')]
class GetFormResultsTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'form' => $schema->string()->required()->description('The form uuid (from list_forms).'),
            'responses' => $schema->boolean()->description('Also return the latest 50 answers in full. Default false.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        $uuid = trim((string) $request->get('form', ''));
        $form = $uuid !== '' ? Form::where('uuid', $uuid)->first() : null;
        if (! $form || ! $user->isSuperAdmin()) {
            return Response::error('Form not found.');
        }

        $rows = SurveyResponses::rows($form);
        $stats = SurveyAnalytics::build($form, $rows);
        $out = ['success' => true, 'form' => ['uuid' => $form->uuid, 'title' => $form->title, 'open' => (bool) $form->is_active], 'results' => $stats];
        if ($request->get('responses')) {
            $out['responses'] = SurveyResponses::list($form, $rows, 50);
        }

        return Response::json($out);
    }
}
