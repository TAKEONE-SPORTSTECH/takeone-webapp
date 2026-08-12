<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * Manage the pictures already on a member's profile.
 *
 * Adding a picture is deliberately NOT exposed here: an upload is raw image
 * bytes, validated and cropped by the app's own uploader, and pushing base64
 * blobs through a tool call would widen the upload surface for no real gain.
 * Read the list with `get_member` (its `photos` field).
 */
#[Title('Manage member photo')]
#[Description('Promote one of a member\'s existing profile pictures to be their avatar, or delete a picture. Authorization mirrors the app: only a super-admin, the member themselves, or a confirmed guardian (NOT club-admins). Uploading a new picture is not available through MCP.')]
class ManageMemberPhotoTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'member' => $schema->string()->required()->description('Member uuid or numeric id.'),
            'photo' => $schema->string()->required()->description('Photo uuid, as returned by get_member.photos[].uuid.'),
            'action' => $schema->string()->required()->description('Either "set_avatar" or "delete".'),
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
            return Response::error('You are not authorized to manage this member\'s pictures.');
        }

        $validated = $request->validate([
            'photo' => 'required|string|uuid',
            'action' => 'required|string|in:set_avatar,delete',
        ]);

        // Scoped to this member — another profile's uuid simply is not found.
        $photo = $member->photos()->where('uuid', $validated['photo'])->first();

        if (! $photo) {
            return Response::error('Photo not found on this member\'s profile.');
        }

        if ($validated['action'] === 'set_avatar') {
            $member->update(['profile_picture' => $photo->path]);

            return Response::json([
                'success' => true,
                'action' => 'set_avatar',
                'photo' => ['uuid' => $photo->uuid, 'url' => $photo->url(), 'is_avatar' => true],
            ]);
        }

        $wasAvatar = $photo->path === $member->profile_picture;
        // The model purges the file before the row (Delete Files Before Records).
        $photo->delete();

        $nextAvatar = null;
        if ($wasAvatar) {
            $nextAvatar = $member->photos()->first();
            $member->update(['profile_picture' => $nextAvatar?->path]);
        }

        return Response::json([
            'success' => true,
            'action' => 'delete',
            'was_avatar' => $wasAvatar,
            'new_avatar' => $nextAvatar ? ['uuid' => $nextAvatar->uuid, 'url' => $nextAvatar->url()] : null,
            'remaining' => $member->photos()->count(),
        ]);
    }
}
