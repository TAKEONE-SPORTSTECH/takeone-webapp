<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AchievementRequest;
use App\Models\ClubAchievement;
use App\Models\Tenant;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Support\Facades\Storage;

class ClubAchievementController extends Controller
{
    use HandlesClubAuthorization;

    public function achievements(Tenant $club)
    {
        $this->authorizeClub($club);
        $achievements = ClubAchievement::where('tenant_id', $club->id)->orderBy('sort_order')->orderBy('id')->get();
        return view('admin.club.achievements.index', compact('club', 'achievements'));
    }

    public function storeAchievement(AchievementRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $images = $this->saveAchievementBase64Images($request->input('achievement_images_base64', []), $club->id);

        try {
            $chips    = $request->chips    ? json_decode($request->chips, true, 512, JSON_THROW_ON_ERROR)    : null;
            $athletes = $request->athletes ? json_decode($request->athletes, true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (\JsonException) {
            return back()->withErrors(['chips' => 'Invalid data format.']);
        }

        ClubAchievement::create([
            'tenant_id'        => $club->id,
            'title'            => $request->title,
            'short_title'      => $request->short_title,
            'type_icon'        => $request->type_icon,
            'description'      => $request->description,
            'location'         => $request->location,
            'achievement_date' => $request->achievement_date,
            'date_label'       => $request->date_label,
            'medals_gold'      => $request->medals_gold   ?? 0,
            'medals_silver'    => $request->medals_silver ?? 0,
            'medals_bronze'    => $request->medals_bronze ?? 0,
            'bouts_count'      => $request->bouts_count   ?? 0,
            'wins_count'       => $request->wins_count    ?? 0,
            'category'         => $request->category,
            'chips'            => $chips,
            'athletes'         => $athletes,
            'tag'              => $request->tag,
            'tag_icon'         => $request->tag_icon ?: 'bi-trophy',
            'image_path'       => null,
            'images'           => $images ?: null,
            'bg_from'          => $request->bg_from ?: '#f59e0b',
            'bg_to'            => $request->bg_to   ?: '#f97316',
            'status'           => $request->status,
            'sort_order'       => $request->sort_order ?? 0,
        ]);

        return back()->with('success', 'Achievement created successfully.');
    }

    public function updateAchievement(AchievementRequest $request, Tenant $club, ClubAchievement $achievement)
    {
        $this->authorizeClub($club);
        abort_if($achievement->tenant_id !== $club->id, 403);

        $data = $request->only([
            'title', 'short_title', 'type_icon', 'description',
            'location', 'achievement_date', 'date_label',
            'medals_gold', 'medals_silver', 'medals_bronze',
            'bouts_count', 'wins_count', 'category',
            'tag', 'tag_icon', 'bg_from', 'bg_to', 'status', 'sort_order',
        ]);
        try {
            $data['chips']    = $request->chips    ? json_decode($request->chips, true, 512, JSON_THROW_ON_ERROR)    : null;
            $data['athletes'] = $request->athletes ? json_decode($request->athletes, true, 512, JSON_THROW_ON_ERROR) : null;
            $kept             = json_decode($request->input('keep_extra_images', '[]'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return back()->withErrors(['chips' => 'Invalid data format.']);
        }
        $newExtra      = $this->saveAchievementBase64Images($request->input('achievement_images_base64', []), $club->id);
        $data['images'] = array_merge($kept, $newExtra) ?: null;

        if ($achievement->image_path && !in_array($achievement->image_path, $kept)) {
            Storage::disk('public')->delete($achievement->image_path);
            $data['image_path'] = null;
        }

        $achievement->update($data);

        return back()->with('success', 'Achievement updated successfully.');
    }

    public function destroyAchievement(Tenant $club, ClubAchievement $achievement)
    {
        $this->authorizeClub($club);
        abort_if($achievement->tenant_id !== $club->id, 403);

        foreach ($achievement->images ?? [] as $imgPath) {
            Storage::disk('public')->delete($imgPath);
        }
        if ($achievement->image_path) {
            Storage::disk('public')->delete($achievement->image_path);
        }
        $achievement->delete();

        return response()->json(['success' => true, 'message' => 'Achievement deleted successfully.']);
    }

    private function saveAchievementBase64Images(array $base64List, int $clubId): array
    {
        $paths = [];
        foreach ($base64List as $base64) {
            if (!str_starts_with($base64, 'data:image')) continue;
            [$meta, $imageData] = explode(',', $base64, 2);
            preg_match('/image\/(\w+)/', $meta, $m);
            $ext  = $m[1] ?? 'jpg';
            $path = 'clubs/' . $clubId . '/achievements/' . uniqid('ach_') . '.' . $ext;
            Storage::disk('public')->put($path, base64_decode($imageData));
            $paths[] = $path;
        }
        return $paths;
    }
}
