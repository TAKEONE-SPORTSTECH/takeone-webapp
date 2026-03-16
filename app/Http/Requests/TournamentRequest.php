<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                                => 'required|string|max:255',
            'type'                                 => 'required|in:championship,tournament,competition,exhibition',
            'sport'                                => 'required|string|max:100',
            'date'                                 => 'required|date',
            'time'                                 => 'nullable|date_format:H:i',
            'location'                             => 'nullable|string|max:255',
            'participants_count'                   => 'nullable|integer|min:1',
            'club_affiliation_id'                  => 'nullable|exists:club_affiliations,id',
            'performance_results'                  => 'nullable|array',
            'performance_results.*.medal_type'     => 'nullable|in:special,1st,2nd,3rd',
            'performance_results.*.points'         => 'nullable|numeric|min:0',
            'performance_results.*.description'    => 'nullable|string|max:500',
            'notes_media'                          => 'nullable|array',
            'notes_media.*.note_text'              => 'nullable|string|max:1000',
            'notes_media.*.media_link'             => 'nullable|url',
        ];
    }
}
