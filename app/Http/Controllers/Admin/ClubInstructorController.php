<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInstructorRequest;
use App\Http\Requests\Admin\UpdateInstructorRequest;
use App\Http\Requests\UploadImageRequest;
use App\Models\ClubInstructor;
use App\Models\Tenant;
use App\Models\User;
use App\Traits\HandlesClubAuthorization;
use App\Traits\StoresBase64Images;
use Illuminate\Support\Facades\Storage;

class ClubInstructorController extends Controller
{
    use HandlesClubAuthorization, StoresBase64Images;

    public function instructors(Tenant $club)
    {
        $this->authorizeClub($club);
        $instructors = ClubInstructor::where('tenant_id', $club->id)->with('user')->get();
        return view('admin.club.instructors.index', compact('club', 'instructors'));
    }

    public function storeInstructor(StoreInstructorRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId       = $club->id;
        $creationType = $request->input('creation_type', 'new');

        if ($creationType === 'new') {

            $user = User::create([
                'name'        => $request->name,
                'full_name'   => $request->name,
                'email'       => $request->email,
                'password'    => bcrypt($request->password),
                'mobile'      => ($request->country_code ?? '+973') . $request->phone,
                'gender'      => $request->gender,
                'birthdate'   => $request->birthdate,
                'nationality' => $request->nationality,
            ]);

            if ($request->filled('photo') && str_starts_with($request->input('photo'), 'data:image')) {
                $photoPath = $this->storeBase64Image($request->input('photo'), 'users/' . $user->id, 'profile_' . time());
                $user->update(['profile_picture' => $photoPath]);
            } elseif ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('users/' . $user->id, 'public');
                $user->update(['profile_picture' => $photoPath]);
            }

            $userId          = $user->id;
            $role            = $request->specialty;
            $experienceYears = $request->experience;
            try {
                $skills = $request->skills ? json_decode($request->skills, true, 512, JSON_THROW_ON_ERROR) : [];
            } catch (\JsonException) {
                return back()->withErrors(['skills' => 'Invalid skills format.']);
            }
            $bio             = $request->bio;

        } else {

            $userId          = $request->selected_member_id;
            $role            = $request->specialty_existing;
            $experienceYears = $request->experience_existing;
            try {
                $skills = $request->skills_existing ? json_decode($request->skills_existing, true, 512, JSON_THROW_ON_ERROR) : [];
            } catch (\JsonException) {
                return back()->withErrors(['skills' => 'Invalid skills format.']);
            }
            $bio             = $request->bio_existing;
        }

        User::where('id', $userId)->update([
            'bio'              => $bio ?: null,
            'skills'           => !empty($skills) ? $skills : null,
            'experience_years' => $experienceYears ?: null,
        ]);

        ClubInstructor::create([
            'tenant_id' => $clubId,
            'user_id'   => $userId,
            'role'      => $role,
        ]);

        return back()->with('success', 'Instructor added successfully.');
    }

    public function updateInstructor(UpdateInstructorRequest $request, Tenant $club, ClubInstructor $instructor)
    {
        $this->authorizeClub($club);

        if ($instructor->tenant_id !== $club->id) {
            abort(403);
        }

        $instructor->update(['role' => $request->role]);

        try {
            $skills = $request->skills ? json_decode($request->skills, true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (\JsonException) {
            return back()->withErrors(['skills' => 'Invalid skills format.']);
        }
        $userUpdate = [
            'experience_years' => $request->experience ?: null,
            'skills'           => !empty($skills) ? $skills : null,
            'bio'              => $request->bio ?: null,
        ];
        if ($request->filled('name')) {
            $userUpdate['name']      = $request->name;
            $userUpdate['full_name'] = $request->name;
        }
        $instructor->user->update($userUpdate);

        if ($request->filled('photo') && str_starts_with($request->input('photo'), 'data:image')) {
            $photoPath = $this->storeBase64Image($request->input('photo'), 'users/' . $instructor->user_id, 'profile_' . time());
            $instructor->user->update(['profile_picture' => $photoPath]);
        } elseif ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('users/' . $instructor->user_id, 'public');
            $instructor->user->update(['profile_picture' => $photoPath]);
        }

        return back()->with('success', 'Instructor updated successfully.');
    }

    public function destroyInstructor(Tenant $club, ClubInstructor $instructor)
    {
        $this->authorizeClub($club);

        if ($instructor->tenant_id !== $club->id) {
            abort(403);
        }

        $instructor->delete();

        return response()->json(['success' => true, 'message' => 'Instructor removed from club successfully.']);
    }

    public function uploadInstructorPhoto(UploadImageRequest $request, Tenant $club, $instructorId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        try {
            $instructor = ClubInstructor::where('tenant_id', $clubId)->with('user')->findOrFail($instructorId);

            if (!$instructor->user) {
                return response()->json(['success' => false, 'message' => 'Instructor has no linked user'], 400);
            }

            $imageData    = $request->image;
            $imageParts   = explode(';base64,', $imageData);
            $imageTypeAux = explode('image/', $imageParts[0]);
            $extension    = $imageTypeAux[1];
            $imageBinary  = base64_decode($imageParts[1]);

            $folder   = trim($request->folder, '/');
            $fullPath = $folder . '/' . $request->filename . '.' . $extension;

            if ($instructor->user->profile_picture && Storage::disk('public')->exists($instructor->user->profile_picture)) {
                Storage::disk('public')->delete($instructor->user->profile_picture);
            }

            Storage::disk('public')->put($fullPath, $imageBinary);
            $instructor->user->update(['profile_picture' => $fullPath]);

            return response()->json([
                'success' => true,
                'path'    => $fullPath,
                'url'     => asset('storage/' . $fullPath),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
