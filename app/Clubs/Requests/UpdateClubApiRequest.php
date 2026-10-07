<?php

namespace App\Clubs\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClubApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $club = $this->route('club');
        $clubId = $club?->id ?? $this->route('id');

        // The slug's format is checked only when it CHANGES, so a club whose
        // slug predates the rule can still be saved without renaming it.
        $slug = ['required', 'string', 'max:255', Rule::unique('tenants', 'slug')->ignore($clubId)];
        if (! $club || (string) $this->input('slug') !== (string) $club->slug) {
            $slug = array_merge($slug, StoreClubRequest::SLUG_FORMAT);
        }

        return [
            'owner_user_id' => 'required|exists:users,id',
            'club_name' => 'required|string|max:255',
            'slug' => $slug,
        ] + StoreClubRequest::modalRules();
    }
}
