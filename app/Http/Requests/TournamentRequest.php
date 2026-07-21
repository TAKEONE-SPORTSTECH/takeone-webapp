<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The member whose profile this tournament belongs to (route {id}).
        $memberId = (int) $this->route('id');

        return [
            'title' => 'required|string|max:255',
            'type' => 'required|in:championship,tournament,competition,exhibition',
            'sport' => 'required|string|max:100',
            'date' => 'required|date',
            'time' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'participants_count' => 'nullable|integer|min:1',
            // Scope to affiliations the member actually owns — never trust an
            // arbitrary affiliation id (would let a claim name someone else's club).
            'club_affiliation_id' => [
                'nullable',
                Rule::exists('club_affiliations', 'id')->where('member_id', $memberId),
            ],
            'performance_results' => 'nullable|array',
            'performance_results.*.medal_type' => 'nullable|in:special,1st,2nd,3rd',
            'performance_results.*.points' => 'nullable|numeric|min:0',
            'performance_results.*.description' => 'nullable|string|max:500',
            'notes_media' => 'nullable|array',
            'notes_media.*.note_text' => 'nullable|string|max:1000',
            'notes_media.*.media_link' => 'nullable|url',
            // Optional supporting evidence (certificate / medal photo) as a base64
            // data-URI. Stored via StoresBase64Images (real-byte sniff, SVG rejected).
            // Evidence is SUPPORT ONLY — it never verifies a claim on its own.
            'evidence' => 'nullable|string|starts_with:data:image/',
        ];
        // NOTE: verification_status/method/verified_* are deliberately absent —
        // they are set only by AchievementVerificationService, never by the client.
    }
}
