<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The edit form's "after photo" input always exists in the DOM (only hidden via
     * CSS when not closing the goal), so a plain progress update still submits it as
     * an empty string. Normalize that to null so `nullable` actually applies.
     */
    protected function prepareForValidation(): void
    {
        if ($this->after_proof === '') {
            $this->merge(['after_proof' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'current_progress_value' => 'required|numeric|min:0',
            'status' => 'required|in:active,completed',
            // Only format-validated here — whether it's REQUIRED (closing the goal for the
            // first time) is a business rule enforced in the controller, since that depends
            // on the goal's current status, which this request can't see.
            'after_proof' => 'nullable|string|starts_with:data:image/',
        ];
    }
}
