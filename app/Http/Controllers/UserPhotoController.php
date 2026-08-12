<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPhoto;
use App\Models\UserRelationship;
use App\Traits\StoresBase64Images;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The pictures on a member's profile.
 *
 * Every action re-derives the viewer's right to manage this member — the same
 * rule MemberController::uploadPicture uses (self, guardian, or super-admin).
 * Photos are addressed by uuid, never by the auto-increment id, and each lookup
 * is scoped to the member in the URL so a valid uuid from another profile is a
 * 404 rather than a way in.
 */
class UserPhotoController extends Controller
{
    use StoresBase64Images;

    /** A profile is a profile, not an album — enough room to choose from, not to host. */
    private const MAX_PHOTOS = 12;

    /**
     * Add a picture. The bytes are validated and the extension assigned
     * server-side; the folder and filename are generated here, never taken
     * from the request (Upload Storage Structure and File Naming).
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $member = $this->authorizeManage($id);

        $request->validate([
            'image' => ['required', 'string', 'starts_with:data:image/'],
        ]);

        if ($member->photos()->count() >= self::MAX_PHOTOS) {
            return response()->json([
                'success' => false,
                'message' => __('member.photos_limit_reached', ['max' => self::MAX_PHOTOS]),
            ], 422);
        }

        $path = $this->storeBase64Image(
            $request->input('image'),
            'people/'.$member->uuid.'/photos',
            (string) Str::ulid(),
        );

        if ($path === null) {
            return response()->json(['success' => false, 'message' => __('member.photo_invalid')], 422);
        }

        $photo = $member->photos()->create([
            'path' => $path,
            'sort_order' => (int) $member->photos()->max('sort_order') + 1,
        ]);

        // A new picture becomes the face of the profile — that is what the
        // avatar's "add a new picture" means. Older photos stay available.
        $member->update(['profile_picture' => $path]);

        return response()->json([
            'success' => true,
            // `url` + `path` keep the shared cropper widget's own patching working.
            'url' => $photo->url(),
            'path' => $path,
            'photo' => $photo->toSheetArray($path),
        ]);
    }

    /** Promote an existing picture to be the profile's avatar. */
    public function setAvatar(string $id, string $photo): JsonResponse
    {
        $member = $this->authorizeManage($id);
        $record = $this->findPhoto($member, $photo);

        $member->update(['profile_picture' => $record->path]);

        return response()->json([
            'success' => true,
            'url' => $record->url(),
            'message' => __('member.photo_is_now_avatar'),
        ]);
    }

    /**
     * Delete a picture. The trait purges the file before the row; if the deleted
     * one was the avatar, the next remaining picture takes its place so the
     * profile never points at a file that is gone.
     */
    public function destroy(string $id, string $photo): JsonResponse
    {
        $member = $this->authorizeManage($id);
        $record = $this->findPhoto($member, $photo);

        $wasAvatar = $record->path === $member->profile_picture;
        $record->delete();

        $nextAvatar = null;
        if ($wasAvatar) {
            $nextAvatar = $member->photos()->first();
            $member->update(['profile_picture' => $nextAvatar?->path]);
        }

        return response()->json([
            'success' => true,
            'message' => __('member.photo_removed'),
            'avatar_url' => $nextAvatar?->url(),
            'was_avatar' => $wasAvatar,
        ]);
    }

    /**
     * May the signed-in user manage this member's pictures?
     * Mirrors MemberController::uploadPicture — self, guardian, or super-admin.
     */
    private function authorizeManage(string $id): User
    {
        $viewer = Auth::user();
        abort_unless($viewer !== null, 403);

        if (! $viewer->hasRole('super-admin') && (int) $viewer->id !== (int) $id) {
            UserRelationship::where('guardian_user_id', $viewer->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        return User::findOrFail($id);
    }

    /** Scoped to the member in the URL: another profile's uuid is simply not found. */
    private function findPhoto(User $member, string $uuid): UserPhoto
    {
        return $member->photos()->where('uuid', $uuid)->firstOrFail();
    }
}
