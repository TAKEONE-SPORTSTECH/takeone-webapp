<?php

namespace App\Mcp\Tools;

use Ahl\Certificates\Certificates as Engine;
use Ahl\Certificates\CertificateService;
use App\Certificates\Support\CertificateRealtime;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Revoke a certificate')]
#[Description('Revoke an issued certificate of an event you manage, with a reason. The holder and anyone who scans its QR code then see that it was revoked, and when.')]
class RevokeCertificateTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'certificate' => $schema->string()->required()->description('The certificate\'s uuid.'),
            'reason' => $schema->string()->required()->description('Why it is revoked (3–300 characters).'),
        ];
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

        $v = $request->validate(['certificate' => 'required|uuid', 'reason' => 'required|string|min:3|max:300']);
        $c = Engine::model()::query()->where('uuid', $v['certificate'])->first();
        if (! $c || ! Gate::forUser($user)->allows('revoke', $c)) {
            return Response::error('Certificate not found.');
        }

        app(CertificateService::class)->revoke($c, $user, strip_tags($v['reason']));
        CertificateRealtime::refresh([$c->recipient_id]);

        return Response::json(['certificate' => $c->uuid, 'status' => 'revoked']);
    }
}
