<?php

/*
|--------------------------------------------------------------------------
| Event-type packages
|--------------------------------------------------------------------------
| Strings shared by the event SYSTEM itself. A type's own strings live inside
| its package folder (app/Events/Types/<Name>/resources/lang) under the
| event-<key>::messages namespace, so they travel with the package.
*/

return [

    // Shared event-system strings
    'action_unsupported' => 'That action isn’t available for this kind of event.',
    'action_unavailable' => 'That action isn’t available right now.',
    'outcome_saved' => 'Result recorded',
    'results_derived_from_engine' => 'This event’s results come from its own recorded play — they can’t be entered by hand.',
    'banned_by_organiser' => 'You’ve been removed from this event by the organiser.',

    // Registration
    'reg_confirmed' => 'You’re in! See you there 🎉',
    'reg_pay_at_club' => 'Spot reserved · :fee — confirm payment at the club',
    'reg_proof_sent' => 'Spot reserved · payment sent for review',

    // Notification milestones
    'notify_created_title' => 'New event: :title',
    'notify_created_body' => ':club · :date — entries are open',
    'notify_enrolment_open_title' => 'Entries open — :title',
    'notify_enrolment_open_body' => 'Registration is now open. Secure your place.',
    'notify_enrolment_closing_title' => 'Last chance — :title',
    'notify_enrolment_closing_body' => 'Entries close on :date. Register before it shuts.',
    'notify_enrolment_closed_title' => 'Entries closed — :title',
    'notify_enrolment_closed_body' => 'The entry list is final. See you there.',
    'notify_event_day_title' => 'Today: :title',
    'notify_event_day_body' => 'Starts :time at :place. Good luck!',

    // Club / coach entry
    'entry_result' => ':entered entered · :rejected could not be',
    'entry_not_your_athlete' => ':name is not a member of a club you run.',
    'entry_out_of_scope' => 'This event is not open to :name’s club.',
    'entry_banned' => ':name has been barred from this event.',
    'entry_event_ended' => 'This event has ended.',
    'entry_not_open' => 'Entries open on :date.',
    'entry_closed' => 'Entries closed on :date.',
    'entry_full' => 'This event is full.',
    'entry_not_eligible' => ':name is not eligible for this event.',
    'entry_notify_title' => 'You’ve been entered — :title',
    'entry_notify_body' => ':club entered you into this event.',
    'entry_notify_body_division' => ':club entered you into this event · :division',

    // Brackets — the shared zoomable draw. Owned by the event SYSTEM rather
    // than any one sport, because every bracketed type draws the same picture.
    'athlete' => 'Athlete',
    'division_not_found' => 'That division is not part of this event.',
    'bracket_not_drawn' => 'This division has no draw yet.',
    'bracket_slot_invalid' => 'That position cannot be changed.',
    'bracket_competitor_invalid' => 'That competitor is not entered in this division.',
    'bracket_tbd' => 'TBD',
    'bracket_bye' => 'Bye',
    'bracket_bench' => 'Entrants',
    'bracket_bench_empty' => 'Everyone is in the draw.',
    'bracket_no_draw' => 'No draw yet for this division.',
    'bracket_load_failed' => 'Could not load the bracket.',
    'bracket_move_failed' => 'Could not move that competitor.',
    'bracket_locked' => 'Draw final',
    'bracket_arrange' => 'Arrange the draw',
    'bracket_done_arranging' => 'Done arranging',
    'bracket_arrange_hint' => 'Drag a competitor to a slot — or tap one, then tap where they go.',
    'bracket_holding' => 'Holding',
    'bracket_tap_to_place' => 'tap a slot to place them',
    'bracket_clear' => 'Empty this draw',
    'bracket_clear_title' => 'Empty this draw?',
    'bracket_clear_message' => 'Every competitor goes back to the entrants list so you can build the draw by hand. Nothing is lost — you can place them again, or generate a fresh draw.',
    'bracket_clear_confirm' => 'Empty it',
    'bracket_zoom_in' => 'Zoom in',
    'bracket_zoom_out' => 'Zoom out',
    'bracket_fit' => 'Fit to screen',
    'bracket_legend_provisional' => 'Not yet confirmed',
    'bracket_legend_done' => 'Decided',
    'bracket_legend_gestures' => 'Drag to pan · pinch or scroll to zoom',

    // Create/edit form — reasons a save is refused, in the words of the screen
    'validate_enrolment_ends_after_event' => 'Registration must close on or before the event date.',
    'validate_enrolment_ends_before_start' => 'Registration cannot close before it opens.',
    'validate_end_date_before_start' => 'The end date cannot be before the start date.',
    'validate_start_time_format' => 'Pick a start time.',
    'validate_end_time_format' => 'Pick an end time, or leave it empty.',
    'validate_break_before_start' => 'The break cannot start before the event does.',
    'validate_break_end_before_break_start' => 'The break has to end after it starts.',
    'validate_break_after_end' => 'The break has to end before the event does.',
];
