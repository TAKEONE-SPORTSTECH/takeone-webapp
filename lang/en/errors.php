<?php

return [
    // Too many requests. Written for the person most likely to see it: a
    // competitor with no account, tapping "enter this competition" from a
    // venue where the whole hall shares one address.
    '429_title' => 'Just a moment',
    '429_body' => 'A lot of people are using this page at once, so we have paused new requests for a few seconds. Nothing has gone wrong and nothing you entered has been lost.',
    '429_retry' => '{1}Try again in :seconds second|[2,*]Try again in :seconds seconds',
    '429_retry_action' => 'Try again',
];
