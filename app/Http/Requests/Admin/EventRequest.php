<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:date',
            'start_time' => 'required',
            'end_time' => 'nullable',
            'location' => 'nullable|string|max:255',
            'level' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'max_capacity' => 'nullable|integer|min:1',
            'cancel_within_days' => 'nullable|integer|min:1|max:365',
            'tags' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            // The display line and the price behind it — see App\Events\Support\EventFee.
            'participant_fee' => 'nullable|string|max:40',
            'participant_fee_amount' => 'nullable|numeric|min:0|max:1000000',

            // MULTI-PRICING — the named extras an entrant may tick, and the flat
            // penalty for entering late. Both are ADDITIVE to the base fee above:
            // an event that sends neither behaves exactly as it always has, which
            // is why nothing here is `required` at the top level.
            //
            // `fee_pricing_present` is the form saying "I own these fields". It
            // has to exist because an organiser who removes the LAST option posts
            // no `fee_options` key at all — indistinguishable, without the marker,
            // from a caller that never had the editor on screen. The controller
            // uses it to tell "clear them" from "leave them alone".
            'fee_pricing_present' => 'nullable|boolean',
            'fee_options' => 'nullable|array|max:20',
            'fee_options.*' => 'array',
            // A uuid is the browser saying "this row is that existing option".
            // It is never trusted as authority: the controller looks it up
            // against THIS event's own rows and creates a fresh option when it
            // does not resolve, so a copied uuid cannot reprice another event.
            'fee_options.*.uuid' => 'nullable|string|max:36',
            'fee_options.*.label' => 'required|string|max:80',
            'fee_options.*.amount' => 'required|numeric|min:0|max:1000000',
            'late_fee_amount' => 'nullable|numeric|min:0|max:1000000',
            'late_fee_from' => 'nullable|date',
        ];
    }

    /**
     * Drop the empty option rows before anything is judged.
     *
     * The editor keeps a blank row on screen so there is always somewhere to
     * type, and a half-filled one is easy to leave behind. Refusing the whole
     * form over a row the organiser never meant to add would be a worse answer
     * than quietly ignoring it — so a row with NEITHER a label nor a price is
     * removed here, and anything with one of the two still has to be completed.
     */
    protected function prepareForValidation(): void
    {
        $rows = $this->input('fee_options');

        if (! is_array($rows)) {
            return;
        }

        $kept = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $amount = trim((string) ($row['amount'] ?? ''));

            if ($label === '' && $amount === '') {
                continue;
            }

            $kept[] = $row;
        }

        // Re-indexed, because the browser's indices carry gaps once a middle row
        // is removed and `max:20` counts keys, not rows.
        $this->merge(['fee_options' => array_values($kept)]);
    }
}
