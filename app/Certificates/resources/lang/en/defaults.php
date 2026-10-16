<?php

// The wording a new certificate design starts with. Every word here is editable in the
// design room; {tokens} are filled per certificate (see messages.tokens).
return [
    'default' => [
        'title' => 'Certificate',
        'body' => 'for taking part in {event} on {date}.',
    ],
    // An official's badge says what the card is; the rest is the official's own.
    'official_badge' => [
        'title' => 'Official',
        'body' => ' ',
    ],
    // A competitor's badge: the same card, for the people who compete.
    'participant_badge' => [
        'title' => 'Athlete',
        'body' => ' ',
    ],
    // An official's thank-you certificate.
    'official_thanks' => [
        'title' => 'Certificate of Appreciation',
        'body' => 'with thanks for serving as {role} at {event} on {date}, and for helping make it a success.',
    ],
    'participation' => [
        'title' => 'Certificate of Participation',
        'body' => 'competed in the {division} division of the {event} on {date}{representing}.',
    ],
    'podium' => [
        'title' => 'Certificate of Achievement',
        'body' => 'for winning {place} in the {division} division of the {event} on {date}{representing}.',
    ],
];
