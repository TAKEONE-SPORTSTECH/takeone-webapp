<?php

namespace App\Http\Controllers;

use App\Models\InstructorReview;
use App\Models\ClubInstructor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InstructorReviewController extends Controller
{
    /**
     * Store a new review.
     */
    public function store(Request $request, $instructorId)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $instructor = ClubInstructor::findOrFail($instructorId);
        $user = Auth::user();

        // Check if user already has a review
        $existingReview = InstructorReview::where('instructor_id', $instructorId)
            ->where('reviewer_user_id', $user->id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this instructor. Please edit your existing review.'
            ], 400);
        }

        $review = InstructorReview::create([
            'instructor_id' => $instructorId,
            'reviewer_user_id' => $user->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully',
            'review' => $review->load('reviewer'),
        ]);
    }

    /**
     * Update an existing review.
     */
    public function update(Request $request, $reviewId)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = InstructorReview::findOrFail($reviewId);
        $user = Auth::user();

        // Ensure user owns this review
        if ($review->reviewer_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit your own reviews.'
            ], 403);
        }

        $review->update([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully',
            'review' => $review->fresh()->load('reviewer'),
        ]);
    }

    /**
     * Get reviews for an instructor.
     */
    public function index($instructorId)
    {
        $instructor = ClubInstructor::findOrFail($instructorId);
        $reviews = $instructor->reviews()
            ->with('reviewer')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'reviews' => $reviews,
            'average_rating' => $instructor->average_rating,
            'total_reviews' => $instructor->reviews_count,
        ]);
    }
}
