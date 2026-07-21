<?php

namespace App\Http\Requests;

use App\Models\AchievementVouch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a peer/coach vouch (or dispute) for a member's self-claimed
 * achievement. Eligibility (not self, not family, not a reciprocal ring) is
 * enforced server-side in AchievementVerificationService::canVouch() — this
 * request only shapes the input. No `weight` field: credibility is derived
 * server-side and can never be supplied by the voucher.
 */
class VouchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'stance' => 'nullable|in:'.AchievementVouch::STANCE_VOUCH.','.AchievementVouch::STANCE_DISPUTE,
            'relationship' => 'required|in:coach,official,teammate,other',
            'note' => 'nullable|string|max:500',
        ];
    }
}
