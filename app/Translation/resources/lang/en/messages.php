<?php

return [

    // ── The visitor's language picker ────────────────────────────────────────
    'choose_language' => 'Choose a language',
    'more_languages' => 'Another language',
    'search_languages' => 'Search languages',
    'no_language_found' => 'No language matches that.',
    'preparing' => 'Preparing :language…',
    'preparing_note' => 'This event is being written in :language. It only happens once — everyone after you gets it instantly.',
    'preparing_failed' => 'We could not prepare :language just now. Showing the original.',
    'interface_note' => 'The event is in :language. Buttons and labels stay in English.',
    'continue_anyway' => 'Continue in the original',
    'translated_by_ai' => 'Translated automatically',
    'read_in' => 'Read this in',

    // ── The organiser's review screen ────────────────────────────────────────
    'languages' => 'Languages',
    'languages_sub' => 'What this event says in every language it has been read in',
    'source_language' => 'Written in :language',
    'add_language' => 'Add a language',
    'retranslate' => 'Translate again',
    'remove_language' => 'Remove this language',
    'remove_language_confirm' => 'Remove :language? Your own corrections to it are deleted too. The machine translation can be regenerated; your edits cannot.',
    'saved' => 'Saved.',
    'queued' => 'Queued. It will be ready in a moment.',
    'removed' => 'Language removed.',
    'edited_by_you' => 'Edited by you',
    'machine' => 'Automatic',
    'needs_review' => 'Needs review',
    'needs_review_note' => 'The original changed after this was written.',
    'not_translated' => 'Not translated yet',
    'nothing_yet' => 'Nobody has read this event in another language yet.',
    'nothing_yet_note' => 'The moment somebody picks one on the public page, it is written and kept.',
    'complete' => ':done of :total',
    'no_provider' => 'No AI provider is configured. Add one under Admin → AI Providers before translations can be written.',

    // ── Field names, as an organiser would say them ──────────────────────────
    'field_title' => 'Title',
    'field_about' => 'About this event',
    'field_location' => 'Location',
    'field_level' => 'Level',
    'field_prize' => 'Prize',
    'field_cta' => 'Button text',
    'field_ribbon' => 'Ribbon',
    'field_requirement' => 'Requirement',
    'field_division' => 'Division',
    'field_fee' => 'Fee line',


    /*
     |--------------------------------------------------------------------------
     | Which languages the POSTER offers
     |--------------------------------------------------------------------------
     |
     | A different question from which have been WRITTEN. Hiding a language
     | takes it off the reader's picker and keeps every word of it, corrections
     | included — see App\Translation\Contracts\LimitsOfferedLocales.
     */
    'on_the_poster' => 'On the poster',
    'on_the_poster_sub' => 'Which languages readers may choose',
    'offer_every_language' => 'Offer every language',
    'offer_every_language_note' => 'Readers may pick any of the :count languages we translate into.',
    'every_language' => 'All :count languages',
    'n_languages' => ':count languages',
    'save_poster_languages' => 'Save',
    'offered_saved' => 'Poster languages saved.',
    'hiding_keeps_words' => 'Hiding a language keeps its words — including anything you corrected by hand — ready for the moment you offer it again. To throw the words away instead, open that language and remove it.',
    'always' => 'Always',
    'written' => 'Written',
    'not_on_poster' => 'Hidden',
    'not_on_poster_note' => 'This language is not on the poster, so nobody can read it yet. Its words are safe — tap to put it back.',
];
