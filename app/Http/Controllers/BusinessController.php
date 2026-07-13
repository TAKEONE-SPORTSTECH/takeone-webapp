<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BusinessController extends Controller
{
    /**
     * Show the "Create a Business" form, or the current status if one exists.
     */
    public function setup(): View
    {
        $business = Auth::user()->ownedBusiness;

        // Mobile and desktop have genuinely different layouts — separate files.
        $isMobile = (bool) request()->attributes->get('is_mobile', false);
        $view = $isMobile && view()->exists('business.mobile.setup') ? 'business.mobile.setup' : 'business.setup';

        return view($view, compact('business'));
    }

    /**
     * Create a new business in the pending state, awaiting super-admin approval.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // One business per user.
        if ($user->ownedBusiness) {
            return redirect()->route('business.setup');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'string', 'max:2048'],
        ]);

        Business::create([
            'owner_user_id' => $user->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'description' => $validated['description'] ?? null,
            'logo' => $validated['logo'] ?? null,
            'status' => Business::STATUS_PENDING,
        ]);

        return redirect()->route('business.setup')
            ->with('success', 'Your business has been submitted and is pending approval.');
    }

    /**
     * Toggle the session view mode between Personal and Business.
     * Only owners of an approved business may enter Business mode.
     */
    public function switchView(Request $request): RedirectResponse
    {
        $mode = $request->input('mode') === 'business' ? 'business' : 'personal';
        $user = Auth::user();

        if ($mode === 'business') {
            if (! $user->hasApprovedBusiness()) {
                return redirect()->route('business.setup');
            }
            session(['view_mode' => 'business']);

            return redirect()->route('business.dashboard');
        }

        session(['view_mode' => 'personal']);
        // On mobile, land inside the personal mobile shell (keeps the chrome + switcher);
        // on desktop keep the existing explore experience.
        if ($request->attributes->get('is_mobile')) {
            return redirect()->route('me.home');
        }

        return redirect()->route('clubs.explore');
    }

    /**
     * Generate a unique slug for the business name.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;
        $i = 2;
        while (Business::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
