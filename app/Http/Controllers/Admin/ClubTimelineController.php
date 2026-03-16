<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TimelinePostRequest;
use App\Models\ClubTimelinePost;
use App\Models\Tenant;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Support\Facades\Storage;

class ClubTimelineController extends Controller
{
    use HandlesClubAuthorization;

    public function timeline(Tenant $club)
    {
        $this->authorizeClub($club);
        $posts = ClubTimelinePost::where('tenant_id', $club->id)
            ->withCount(['likes', 'comments'])
            ->orderBy('posted_at', 'desc')
            ->get();

        return view('admin.club.timeline.index', compact('club', 'posts'));
    }

    public function storeTimelinePost(TimelinePostRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('timeline/' . $club->slug, 'public');
        }

        ClubTimelinePost::create([
            'tenant_id'  => $club->id,
            'body'       => $request->body,
            'category'   => $request->category,
            'image_path' => $imagePath,
            'posted_at'  => $request->posted_at,
            'status'     => $request->status,
        ]);

        return back()->with('success', 'Post created successfully.');
    }

    public function updateTimelinePost(TimelinePostRequest $request, Tenant $club, ClubTimelinePost $post)
    {
        $this->authorizeClub($club);
        abort_if($post->tenant_id !== $club->id, 403);

        $data = $request->only(['body', 'category', 'posted_at', 'status']);

        if ($request->hasFile('image')) {
            if ($post->image_path) {
                Storage::disk('public')->delete($post->image_path);
            }
            $data['image_path'] = $request->file('image')->store('timeline/' . $club->slug, 'public');
        }

        if ($request->boolean('remove_image') && $post->image_path) {
            Storage::disk('public')->delete($post->image_path);
            $data['image_path'] = null;
        }

        $post->update($data);

        return back()->with('success', 'Post updated successfully.');
    }

    public function destroyTimelinePost(Tenant $club, ClubTimelinePost $post)
    {
        $this->authorizeClub($club);
        abort_if($post->tenant_id !== $club->id, 403);

        if ($post->image_path) {
            Storage::disk('public')->delete($post->image_path);
        }
        $post->delete();

        return response()->json(['success' => true, 'message' => 'Post deleted successfully.']);
    }
}
