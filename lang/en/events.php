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
];
