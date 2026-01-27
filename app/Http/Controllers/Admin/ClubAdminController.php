<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\ClubFacility;
use App\Models\ClubInstructor;
use App\Models\ClubActivity;
use App\Models\ClubPackage;
use App\Models\Membership;
use App\Models\ClubGalleryImage;
use App\Models\ClubTransaction;
use App\Models\ClubMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ClubAdminController extends Controller
{
    /**
     * Get club and verify access
     */
    private function getClub($clubId)
    {
        $club = Tenant::findOrFail($clubId);

        // TODO: Add proper authorization check
        // For now, allow super-admin or club owner
        $user = Auth::user();
        if (!$user->isSuperAdmin() && $club->owner_user_id !== $user->id) {
            abort(403, 'Unauthorized access to this club.');
        }

        return $club;
    }

    /**
     * Dashboard overview
     */
    public function dashboard($clubId)
    {
        $club = $this->getClub($clubId);

        $stats = [
            'members' => Membership::where('tenant_id', $clubId)->where('status', 'active')->count(),
            'activities' => ClubActivity::where('tenant_id', $clubId)->count(),
            'packages' => ClubPackage::where('tenant_id', $clubId)->count(),
            'instructors' => ClubInstructor::where('tenant_id', $clubId)->count(),
            'rating' => $club->reviews()->avg('rating') ?? 0,
        ];

        // Monthly financial data for chart
        $monthlyFinancials = $this->getMonthlyFinancials($clubId);

        // Expiring subscriptions (next 30 days)
        $expiringSubscriptions = collect(); // TODO: Implement when subscription model is ready

        return view('admin.club.dashboard', compact('club', 'stats', 'monthlyFinancials', 'expiringSubscriptions'));
    }

    /**
     * Club details
     */
    public function details($clubId)
    {
        $club = $this->getClub($clubId);
        return view('admin.club.details', compact('club'));
    }

    /**
     * Gallery management
     */
    public function gallery($clubId)
    {
        $club = $this->getClub($clubId);
        $images = ClubGalleryImage::where('tenant_id', $clubId)->latest()->get();
        return view('admin.club.gallery', compact('club', 'images'));
    }

    public function uploadGallery(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'images.*' => 'required|image|max:5120',
            'caption' => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('clubs/' . $clubId . '/gallery', 'public');
                ClubGalleryImage::create([
                    'tenant_id' => $clubId,
                    'path' => $path,
                    'caption' => $request->caption,
                ]);
            }
        }

        return back()->with('success', 'Images uploaded successfully.');
    }

    /**
     * Facilities management
     */
    public function facilities($clubId)
    {
        $club = $this->getClub($clubId);
        $facilities = ClubFacility::where('tenant_id', $clubId)->get();
        return view('admin.club.facilities', compact('club', 'facilities'));
    }

    public function storeFacility(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable',
        ]);

        $data = $request->only(['name', 'description']);
        $data['tenant_id'] = $clubId;

        // Handle base64 image from cropper (form mode)
        if ($request->filled('image') && str_starts_with($request->image, 'data:image')) {
            $imageData = $request->image;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = $request->input('image_folder', 'clubs/' . $clubId . '/facilities');
            $filename = $request->input('image_filename', 'facility_' . time());
            $fullPath = $folder . '/' . $filename . '.' . $extension;

            Storage::disk('public')->put($fullPath, $imageBinary);
            $data['image'] = $fullPath;
        }
        // Handle traditional file upload
        elseif ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('clubs/' . $clubId . '/facilities', 'public');
        }

        ClubFacility::create($data);

        return back()->with('success', 'Facility added successfully.');
    }

    /**
     * Instructors management
     */
    public function instructors($clubId)
    {
        $club = $this->getClub($clubId);
        $instructors = ClubInstructor::where('tenant_id', $clubId)->get();
        return view('admin.club.instructors', compact('club', 'instructors'));
    }

    public function storeInstructor(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'name' => 'required|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'photo' => 'nullable',
        ]);

        $data = $request->only(['name', 'specialization', 'bio']);
        $data['tenant_id'] = $clubId;

        // Handle base64 image from cropper (form mode)
        if ($request->filled('photo') && str_starts_with($request->photo, 'data:image')) {
            $imageData = $request->photo;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = $request->input('photo_folder', 'clubs/' . $clubId . '/instructors');
            $filename = $request->input('photo_filename', 'instructor_' . time());
            $fullPath = $folder . '/' . $filename . '.' . $extension;

            Storage::disk('public')->put($fullPath, $imageBinary);
            $data['photo'] = $fullPath;
        }
        // Handle traditional file upload
        elseif ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('clubs/' . $clubId . '/instructors', 'public');
        }

        ClubInstructor::create($data);

        return back()->with('success', 'Instructor added successfully.');
    }

    /**
     * Activities management
     */
    public function activities($clubId)
    {
        $club = $this->getClub($clubId);
        $activities = ClubActivity::where('tenant_id', $clubId)->with('instructor')->get();
        $instructors = ClubInstructor::where('tenant_id', $clubId)->get();
        return view('admin.club.activities', compact('club', 'activities', 'instructors'));
    }

    public function storeActivity(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration' => 'nullable|integer',
            'instructor_id' => 'nullable|exists:club_instructors,id',
        ]);

        $data = $request->only(['name', 'description', 'duration', 'instructor_id']);
        $data['tenant_id'] = $clubId;

        ClubActivity::create($data);

        return back()->with('success', 'Activity added successfully.');
    }

    /**
     * Packages management
     */
    public function packages($clubId)
    {
        $club = $this->getClub($clubId);
        $packages = ClubPackage::where('tenant_id', $clubId)->get();
        return view('admin.club.packages', compact('club', 'packages'));
    }

    public function storePackage(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'is_popular' => 'boolean',
        ]);

        $data = $request->only(['name', 'description', 'price', 'duration_days']);
        $data['tenant_id'] = $clubId;
        $data['is_popular'] = $request->boolean('is_popular');

        ClubPackage::create($data);

        return back()->with('success', 'Package added successfully.');
    }

    /**
     * Members management
     */
    public function members($clubId)
    {
        $club = $this->getClub($clubId);
        $members = Membership::where('tenant_id', $clubId)
            ->with(['user', 'subscription.package'])
            ->paginate(20);
        $packages = ClubPackage::where('tenant_id', $clubId)->get();
        return view('admin.club.members', compact('club', 'members', 'packages'));
    }

    public function storeMember(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        // TODO: Implement member creation logic

        return back()->with('success', 'Member added successfully.');
    }

    /**
     * Roles management
     */
    public function roles($clubId)
    {
        $club = $this->getClub($clubId);
        $staffMembers = collect(); // TODO: Implement staff roles
        return view('admin.club.roles', compact('club', 'staffMembers'));
    }

    public function storeRole(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        // TODO: Implement role assignment

        return back()->with('success', 'Role assigned successfully.');
    }

    /**
     * Financials management
     */
    public function financials($clubId)
    {
        $club = $this->getClub($clubId);
        $transactions = ClubTransaction::where('tenant_id', $clubId)
            ->latest('transaction_date')
            ->paginate(20);

        $summary = [
            'total_income' => ClubTransaction::where('tenant_id', $clubId)
                ->where('type', 'income')
                ->sum('amount'),
            'total_expenses' => ClubTransaction::where('tenant_id', $clubId)
                ->where('type', 'expense')
                ->sum('amount'),
            'net_profit' => 0,
            'pending' => ClubTransaction::where('tenant_id', $clubId)
                ->where('status', 'pending')
                ->sum('amount'),
        ];
        $summary['net_profit'] = $summary['total_income'] - $summary['total_expenses'];

        return view('admin.club.financials', compact('club', 'transactions', 'summary'));
    }

    public function storeIncome(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
        ]);

        ClubTransaction::create([
            'tenant_id' => $clubId,
            'description' => $request->description,
            'amount' => $request->amount,
            'type' => 'income',
            'transaction_date' => $request->transaction_date,
            'status' => 'paid',
        ]);

        return back()->with('success', 'Income recorded successfully.');
    }

    public function storeExpense(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
        ]);

        ClubTransaction::create([
            'tenant_id' => $clubId,
            'description' => $request->description,
            'amount' => $request->amount,
            'type' => 'expense',
            'transaction_date' => $request->transaction_date,
            'status' => 'paid',
        ]);

        return back()->with('success', 'Expense recorded successfully.');
    }

    /**
     * Messages management
     */
    public function messages($clubId)
    {
        $club = $this->getClub($clubId);
        $conversations = collect(); // TODO: Implement messaging
        $members = Membership::where('tenant_id', $clubId)->with('user')->get();
        return view('admin.club.messages', compact('club', 'conversations', 'members'));
    }

    public function sendMessage(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        // TODO: Implement message sending

        return back()->with('success', 'Message sent successfully.');
    }

    /**
     * Analytics
     */
    public function analytics($clubId)
    {
        $club = $this->getClub($clubId);

        $analytics = [
            'new_members' => 0,
            'new_members_change' => 0,
            'retention_rate' => 0,
            'retention_change' => 0,
            'avg_revenue' => 0,
            'total_checkins' => 0,
            'checkins_change' => 0,
            'monthly_members' => array_fill(0, 12, 0),
            'activity_labels' => ['No data'],
            'activity_data' => [100],
            'hourly_checkins' => array_fill(0, 9, 0),
        ];

        $popularPackages = ClubPackage::where('tenant_id', $clubId)
            ->withCount('subscriptions')
            ->orderByDesc('subscriptions_count')
            ->take(5)
            ->get();

        return view('admin.club.analytics', compact('club', 'analytics', 'popularPackages'));
    }

    /**
     * Helper: Get monthly financial data for charts
     */
    private function getMonthlyFinancials($clubId)
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $data = [];

        for ($i = 0; $i < 12; $i++) {
            $data[] = [
                'month' => $months[$i],
                'income' => 0,
                'expenses' => 0,
                'profit' => 0,
            ];
        }

        // TODO: Populate with actual transaction data

        return $data;
    }

    /**
     * Upload facility image via AJAX (cropper).
     */
    public function uploadFacilityImage(Request $request, $clubId, $facilityId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'image' => 'required',
            'folder' => 'required|string',
            'filename' => 'required|string',
        ]);

        try {
            $facility = ClubFacility::where('tenant_id', $clubId)->findOrFail($facilityId);

            // Handle base64 image from cropper
            $imageData = $request->image;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = trim($request->folder, '/');
            $fileName = $request->filename . '.' . $extension;
            $fullPath = $folder . '/' . $fileName;

            // Delete old image if exists
            if ($facility->image && Storage::disk('public')->exists($facility->image)) {
                Storage::disk('public')->delete($facility->image);
            }

            // Store in the public disk
            Storage::disk('public')->put($fullPath, $imageBinary);

            // Update facility's image field
            $facility->update(['image' => $fullPath]);

            return response()->json([
                'success' => true,
                'path' => $fullPath,
                'url' => asset('storage/' . $fullPath)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload instructor photo via AJAX (cropper).
     */
    public function uploadInstructorPhoto(Request $request, $clubId, $instructorId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'image' => 'required',
            'folder' => 'required|string',
            'filename' => 'required|string',
        ]);

        try {
            $instructor = ClubInstructor::where('tenant_id', $clubId)->findOrFail($instructorId);

            // Handle base64 image from cropper
            $imageData = $request->image;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = trim($request->folder, '/');
            $fileName = $request->filename . '.' . $extension;
            $fullPath = $folder . '/' . $fileName;

            // Delete old photo if exists
            if ($instructor->photo && Storage::disk('public')->exists($instructor->photo)) {
                Storage::disk('public')->delete($instructor->photo);
            }

            // Store in the public disk
            Storage::disk('public')->put($fullPath, $imageBinary);

            // Update instructor's photo field
            $instructor->update(['photo' => $fullPath]);

            return response()->json([
                'success' => true,
                'path' => $fullPath,
                'url' => asset('storage/' . $fullPath)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
