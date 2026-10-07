<?php

namespace App\Mcp\Tools;

use App\Translation\Translations;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * What the translation doctor last found, and what it fixed.
 *
 * Super-admin only — it names AI providers and their servers' state. READ
 * ONLY: `fresh` re-diagnoses in check mode, which probes the translators but
 * resumes nothing, downloads nothing and notifies nobody. Repairs are the
 * scheduled `translate:doctor`'s job.
 */
#[Title('Translation health')]
#[Description('Super-admin only. Whether the platform can translate right now: each AI translator\'s state (ok, unreachable, model missing, refused), every language run that failed, stalled or is running, the translation queue, what the auto-fixer repaired, and what still needs a person. Pass fresh=true to re-check now (diagnosis only, changes nothing); otherwise returns the last scheduled check.')]
class TranslationHealthTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'fresh' => $schema->boolean()
                ->description('Re-check now instead of returning the last scheduled check. Changes nothing.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        if (! $user->isSuperAdmin()) {
            return Response::error('Only a platform super-admin can read translation health.');
        }

        $report = Translations::health((bool) $request->get('fresh'));

        return Response::json([
            'autofix_enabled' => (bool) config('translation.autofix.enabled'),
            'report' => $report,
        ]);
    }
}
