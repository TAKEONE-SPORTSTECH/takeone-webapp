<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ClubAffiliation;
use App\Models\ClubMemberSubscription;
use App\Models\Invoice;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AffiliationController extends Controller
{
    /**
     * Display the user's affiliations page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();

        // Fetch affiliations with all related data
        $affiliations = $user->clubAffiliations()
            ->with([
                'skillAcquisitions.package',
                'skillAcquisitions.activity',
                'skillAcquisitions.instructor.user',
                'affiliationMedia',
                'subscriptions.package.activities',
                'subscriptions.package.packageActivities.activity',
                'subscriptions.package.packageActivities.instructor.user',
                'subscriptions.transactions',
            ])
            ->orderBy('start_date', 'desc')
            ->get();

        // Get all invoices related to these affiliations
        $affiliationIds = $affiliations->pluck('id');
        $invoices = Invoice::whereIn('club_affiliation_id', $affiliationIds)
            ->orWhere('student_user_id', $user->id)
            ->with(['clubAffiliation.tenant', 'student'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get attendance records for these affiliations
        $attendanceRecords = Attendance::whereIn('club_affiliation_id', $affiliationIds)
            ->where('user_id', $user->id)
            ->orderBy('session_datetime', 'desc')
            ->get();

        // Calculate summary stats
        $totalAffiliations = $affiliations->count();
        $activeAffiliations = $affiliations->whereNull('end_date')->count();
        $inactiveAffiliations = $affiliations->whereNotNull('end_date')->count();
        $totalMembershipDuration = $affiliations->sum('duration_in_months');

        // Count total subscriptions and paid/pending invoices
        $totalSubscriptions = $affiliations->sum(function($affiliation) {
            return $affiliation->subscriptions->count();
        });
        
        $totalPayments = $invoices->where('status', 'paid')->sum('amount');
        $pendingPayments = $invoices->where('status', 'pending')->sum('amount');
        
        // Total attendance
        $totalSessions = $attendanceRecords->count();
        $completedSessions = $attendanceRecords->where('status', 'completed')->count();

        return view('affiliations.index', compact(
            'user',
            'affiliations',
            'invoices',
            'attendanceRecords',
            'totalAffiliations',
            'activeAffiliations',
            'inactiveAffiliations',
            'totalMembershipDuration',
            'totalSubscriptions',
            'totalPayments',
            'pendingPayments',
            'totalSessions',
            'completedSessions'
        ));
    }

    /**
     * Display details for a specific affiliation.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = Auth::user();

        $affiliation = $user->clubAffiliations()
            ->with([
                'skillAcquisitions.package',
                'skillAcquisitions.activity',
                'skillAcquisitions.instructor.user',
                'affiliationMedia',
                'subscriptions.package.activities',
                'subscriptions.package.packageActivities.activity',
                'subscriptions.package.packageActivities.instructor.user',
                'subscriptions.transactions',
                'tenant',
            ])
            ->findOrFail($id);

        // Get invoices for this affiliation
        $invoices = Invoice::where('club_affiliation_id', $id)
            ->orWhere('student_user_id', $user->id)
            ->with(['clubAffiliation.tenant', 'student'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get attendance records
        $attendance = Attendance::where('club_affiliation_id', $id)
            ->where('user_id', $user->id)
            ->orderBy('session_datetime', 'desc')
            ->get();

        // Get transactions from subscriptions
        $transactions = $affiliation->subscriptions->flatMap(function($sub) {
            return $sub->transactions ?? [];
        });

        return response()->json([
            'success' => true,
            'affiliation' => $affiliation,
            'invoices' => $invoices,
            'attendance' => $attendance,
            'transactions' => $transactions,
        ]);
    }
}

