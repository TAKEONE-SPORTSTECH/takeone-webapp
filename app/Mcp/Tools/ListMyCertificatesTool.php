<?php

namespace App\Mcp\Tools;

use Ahl\Certificates\Certificates as Engine;
use App\Certificates\Support\CertificatePresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('List my certificates')]
#[Description('The acting user\'s own certificates — issued ones and revoked ones (marked so) — with what each is for, its number, and its public verification link. Drafts are never included.')]
class ListMyCertificatesTool extends BaseTool
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
        if (! config('certificates.enabled')) {
            return Response::error('Certificates are not switched on.');
        }

        $presenter = app(CertificatePresenter::class);

        return Response::json(['certificates' => Engine::model()::query()->forRecipient($user)
            ->where('status', '!=', 'draft')->with('context')->orderByDesc('issued_on')->limit(200)->get()
            ->map(fn ($c) => $presenter->mine($c, 'en'))->values()]);
    }
}
