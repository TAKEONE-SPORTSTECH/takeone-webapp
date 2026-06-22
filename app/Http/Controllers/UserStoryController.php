<?php

namespace App\Http\Controllers;

use App\Models\UserStory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Members' short-lived (24h) stories shown in the personal feed's stories row.
 * Create returns the story shaped for the feed so it can be prepended in place
 * (No Page Reload rule).
 */
class UserStoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'    => ['nullable', 'in:text,image'],
            'caption' => ['nullable', 'string', 'max:280'],
            'color'   => ['nullable', 'string', 'max:16'],
            'icon'    => ['nullable', 'string', 'max:40'],
            'image'   => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:8192'],
        ]);

        $user = Auth::user();
        $type = $request->hasFile('image') ? 'image' : ($data['type'] ?? 'text');
        $caption = trim($data['caption'] ?? '');

        if ($type === 'text' && $caption === '') {
            return response()->json(['success' => false, 'message' => 'Write something for your story.'], 422);
        }

        $path = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('user-stories/' . $user->id, 'public');
        }

        $story = UserStory::create([
            'user_id'    => $user->id,
            'type'       => $type,
            'image_path' => $path,
            'caption'    => $caption !== '' ? $caption : null,
            'color'      => $data['color'] ?? '#7c3aed',
            'icon'       => $data['icon'] ?? 'bi-camera',
            'expires_at' => now()->addDay(),
        ]);

        $card = $story->setRelation('user', $user)->toFeedArray();
        $card['me'] = true;

        return response()->json([
            'success' => true,
            'message' => 'Story added',
            'story'   => $card,
        ]);
    }

    public function destroy(UserStory $story): JsonResponse
    {
        abort_unless($story->user_id === Auth::id(), 403);
        $story->deleteImageFile();
        $story->delete();

        return response()->json(['success' => true, 'message' => 'Story removed']);
    }
}
