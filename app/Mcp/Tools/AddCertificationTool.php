<?php

namespace App\Mcp\Tools;

use App\Models\MemberCertification;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Add certification')]
#[Description('Add a self-managed certification / qualification to a member profile. Authorization mirrors the app: only a super-admin, the member themselves, or a confirmed guardian may add one (NOT club-admins).')]
class AddCertificationTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'member' => $schema->string()->required()->description('Member uuid or numeric id.'),
            'title' => $schema->string()->required()->description('Certification name.'),
            'issuer' => $schema->string()->description('Issuing organization.'),
            'issue_date' => $schema->string()->description('Issue date (YYYY-MM-DD).'),
            'expiry_date' => $schema->string()->description('Expiry date (YYYY-MM-DD), if any.'),
            'credential_id' => $schema->string()->description('Credential / license number.'),
            'credential_url' => $schema->string()->description('Public verification link (http/https only).'),
            'notes' => $schema->string()->description('Optional notes.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $ref = (string) $request->get('member');

        $member = User::query()
            ->where('uuid', $ref)
            ->orWhere('id', is_numeric($ref) ? (int) $ref : 0)
            ->first();

        if (! $member) {
            return Response::error('Member not found.');
        }

        if (! $this->canEditMemberSelfRecords($user, $member)) {
            return Response::error('You are not authorized to manage this member\'s certifications.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'issuer' => 'nullable|string|max:150',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'credential_id' => 'nullable|string|max:120',
            'credential_url' => 'nullable|url|max:300|starts_with:http://,https://',
            'notes' => 'nullable|string|max:1000',
        ]);

        $cert = MemberCertification::create([
            'user_id' => $member->id,
            'title' => $validated['title'],
            'issuer' => $validated['issuer'] ?? null,
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'credential_id' => $validated['credential_id'] ?? null,
            'credential_url' => $validated['credential_url'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return Response::json([
            'success' => true,
            'message' => 'Certification added.',
            'certification_id' => $cert->id,
            'member' => $member->full_name ?? $member->name,
        ]);
    }
}
