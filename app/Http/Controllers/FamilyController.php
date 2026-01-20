<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRelationship;
use App\Services\FamilyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FamilyController extends Controller
{
    protected $familyService;

    public function __construct(FamilyService $familyService)
    {
        $this->familyService = $familyService;
    }

    /**
     * Display the family dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function dashboard()
    {
        $user = Auth::user();
        $dependents = UserRelationship::where('guardian_user_id', $user->id)
            ->with('dependent')
            ->get()
            ->sortBy(function($relationship) {
                return $relationship->dependent->full_name;
            });
        $familyInvoices = $this->familyService->getFamilyInvoices($user->id);

        return view('family.dashboard', compact('user', 'dependents', 'familyInvoices'));
    }

    /**
     * Display the current user's profile.
     *
     * @return \Illuminate\View\View
     */
    public function profile()
    {
        $user = Auth::user();

        // Pass user directly and a flag to indicate it's the current user's profile
        return view('family.show', [
            'relationship' => (object)[
                'dependent' => $user,
                'relationship_type' => 'self',
                'guardian_user_id' => $user->id,
                'dependent_user_id' => $user->id,
            ]
        ]);
    }

    /**
     * Show the form for creating a new family member.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('family.create');
    }

    /**
     * Store a newly created family member in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'gender' => 'required|in:m,f',
            'birthdate' => 'required|date',
            'blood_type' => 'nullable|string|max:10',
            'nationality' => 'required|string|max:100',
            'relationship_type' => 'required|string|max:50',
            'is_billing_contact' => 'boolean',
        ]);

        $guardian = Auth::user();
        $dependent = $this->familyService->createDependent($guardian, $validated);

        return redirect()->route('family.dashboard')
            ->with('success', 'Family member added successfully.');
    }

    /**
     * Display the specified family member.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $user = Auth::user();
        $relationship = UserRelationship::where('guardian_user_id', $user->id)
            ->where('dependent_user_id', $id)
            ->with('dependent')
            ->firstOrFail();

        return view('family.show', compact('relationship'));
    }

    /**
     * Show the form for editing the specified family member.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $user = Auth::user();
        $relationship = UserRelationship::where('guardian_user_id', $user->id)
            ->where('dependent_user_id', $id)
            ->with('dependent')
            ->firstOrFail();

        return view('family.edit', compact('relationship'));
    }

    /**
     * Update the specified family member in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'gender' => 'required|in:m,f',
            'birthdate' => 'required|date',
            'blood_type' => 'nullable|string|max:10',
            'nationality' => 'required|string|max:100',
            'relationship_type' => 'required|string|max:50',
            'is_billing_contact' => 'boolean',
        ]);

        $user = Auth::user();
        $relationship = UserRelationship::where('guardian_user_id', $user->id)
            ->where('dependent_user_id', $id)
            ->firstOrFail();

        $dependent = User::findOrFail($id);
        $dependent->update([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'gender' => $validated['gender'],
            'birthdate' => $validated['birthdate'],
            'blood_type' => $validated['blood_type'],
            'nationality' => $validated['nationality'],
        ]);

        $relationship->update([
            'relationship_type' => $validated['relationship_type'],
            'is_billing_contact' => $validated['is_billing_contact'] ?? false,
        ]);

        return redirect()->route('family.dashboard')
            ->with('success', 'Family member updated successfully.');
    }

    /**
     * Remove the specified family member from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $relationship = UserRelationship::where('guardian_user_id', $user->id)
            ->where('dependent_user_id', $id)
            ->firstOrFail();

        $relationship->delete();

        return redirect()->route('family.dashboard')
            ->with('success', 'Family member removed successfully.');
    }
}
