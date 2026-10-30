<?php

return [
    // The development board (Margin). OFF by default: no route answers and no
    // capture button is drawn until this is switched on.
    'enabled' => (bool) env('DEVBOARD_ENABLED', false),

    // TakeNote, the board as a phone notebook app (/me/takenote). Needs the
    // board on as well. OFF by default.
    'takenote' => (bool) env('TAKENOTE_ENABLED', false),

    // Friends: the owner invites people by email and each gets a notebook of their own (Support\Notebooks).
    'friends' => (bool) env('TAKENOTE_FRIENDS', false),

    // A folder shared with another person: they edit its notes and cards, and work on the board, inside a
    // fence of that folder (Support\Notebooks). Off unless switched on.
    'sharing' => (bool) env('TAKENOTE_SHARING', false),

    // Named connections between notes ("depends on", "part of"…), made on the note map or under a note,
    // like Trilium's relations (TakeNoteRelationController). Off unless switched on.
    'relations' => (bool) env('TAKENOTE_RELATIONS', false),

    // The most one notebook may hold — every picture, voice note, video and file in it, the bin included.
    'quota_mb' => (int) env('TAKENOTE_QUOTA_MB', 1024),

    // TakeNote's own languages — not the platform's (config/locales.php): TakeNote is read in these
    // whatever the rest of TAKEONE offers. Chosen per device (a cookie), else the browser's language.
    'locales' => [
        'en' => ['native' => 'English', 'js' => 'en-GB'],
        'ar' => ['native' => 'العربية', 'js' => 'ar'],
        'pt_BR' => ['native' => 'Português (Brasil)', 'js' => 'pt-BR'],
        'tr' => ['native' => 'Türkçe', 'js' => 'tr'],
        'ja' => ['native' => '日本語', 'js' => 'ja'],
        'zh' => ['native' => '中文（简体）', 'js' => 'zh-CN'],
    ],

    // Lanes a new board starts with. Colours are deep enough to carry white
    // text on a sheet band, and still glow on the dark desk.
    // In order. Renamable and reorderable later.
    'default_lanes' => [
        ['name' => 'Inbox',   'color' => '#7c6bf5'],
        ['name' => 'Planned', 'color' => '#3b82f6'],
        ['name' => 'Doing',   'color' => '#d97706'],
        ['name' => 'Review',  'color' => '#db2777'],
        ['name' => 'Done',    'color' => '#059669'],
    ],

    // Ink colours a board member may be given (their author colour).
    'inks' => ['#8b7cf6', '#f472b6', '#38bdf8', '#f59e0b', '#34d399', '#f87171'],

    // Hard caps on what one write may carry — a crafted payload must not bloat storage.
    'limits' => [
        'title'          => 200,
        'note'           => 20000,
        'note_html'      => 200000,  // a formatted note, as sanitised HTML
        'image_px'       => 2400,    // long side a picture is stored at
        'comment'        => 4000,
        'strokes_bytes'  => 2_000_000,
        'stroke_points'  => 5000,    // one stroke of a drawing
        'ink_points'     => 60000,   // all strokes of one drawing together
        'ink_strokes'    => 3000,
        'image_mb'       => 10,      // the web server takes 10 MB per file
        'media_mb'       => 10,      // a voice note or video, as uploaded (same web-server ceiling)
        'voice_seconds'  => 900,     // a voice note is cut at 15 minutes
        'video_seconds'  => 60,      // a video at one minute
        'file_mb'        => 10,      // a card's attached file (the same web-server ceiling)
        'attachments'    => 60,      // files and links on one card
        'checklist_steps' => 200,    // items in one checklist
    ],
];
