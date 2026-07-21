<?php

namespace App\Mcp\Tools;

use App\Models\MemberWorkHistory;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Add work history')]
#[Description('Add a self-managed work / coaching history entry to a member profile. A null end_date means the role is current. Authorization mirrors the app: only a super-admin, the member themselves, or a confirmed guardian may add one (NOT club-admins).')]
class AddWorkHistoryTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'member' => $schema->string()->required()->description('Member uuid or numeric id.'),
            'title' => $schema->string()->required()->description('Role / position title.'),
            'organization' => $schema->string()->required()->description('Organization name.'),
            'employment_type' => $schema->string()->description('One of: Full-time, Part-time, Contract, Freelance, Volunteer, Internship.'),
            'location' => $schema->string()->description('Location.'),
            'start_date' => $schema->string()->required()->description('Start date (YYYY-MM-DD).'),
            'end_date' => $schema->string()->description('End date (YYYY-MM-DD). Omit for a current/ongoing role.'),
            'description' => $schema->string()->description('Optional description of the role.'),
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
            return Response::error('You are not authorized to manage this member\'s work history.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'organization' => 'required|string|max:150',
            'employment_type' => 'nullable|in:Full-time,Part-time,Contract,Freelance,Volunteer,Internship',
            'location' => 'nullable|string|max:150',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:2000',
        ]);

        $work = MemberWorkHistory::create([
            'user_id' => $member->id,
            'title' => $validated['title'],
            'organization' => $validated['organization'],
            'employment_type' => $validated['employment_type'] ?? null,
            'location' => $validated['location'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return Response::json([
            'success' => true,
            'message' => 'Work experience added.',
            'work_id' => $work->id,
            'current' => $work->isCurrent(),
            'member' => $member->full_name ?? $member->name,
        ]);
    }
}
