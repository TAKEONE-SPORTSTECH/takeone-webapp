<?php

/*
|--------------------------------------------------------------------------
| Platform modules
|--------------------------------------------------------------------------
| The application is a set of verticals, each owning a top-level folder under
| app/: events, clubs, members, challenges, the shop, personal training, media.
| Everything a vertical needs lives in its folder — models, controllers,
| services, routes, views, translations.
|
| A module is not a plugin to be swapped for another implementation. It is a
| BOUNDARY: everything about the shop is app/Shop/, so it can be read, reviewed,
| changed or deleted as a unit, and a change there cannot reach the club's books
| without crossing a line you can see.
|
| This file is the ONLY place a module is wired in. Adding one = create
| app/<Name>/, implement the Module contract, add one line here, ship its routes
| + views + migrations + MCP tools + tests. Removing one = delete the directory,
| its tables, and its line here.
|
| Order is display order wherever modules are listed as a set.
*/

return [

    'modules' => [
        \App\Clubs\Clubs::class,
        \App\Members\Members::class,
        \App\Events\Events::class,
        \App\Shop\Shop::class,
        \App\Challenges\Challenges::class,
        \App\Trainers\Trainers::class,
        \App\Scoreboard\Scoreboard::class,
        \App\Media\Media::class,
        // The organiser writes once, in their own language; everyone else
        // reads it in theirs. Last in the list because it serves the other
        // modules' content rather than owning a vertical of its own.
        \App\Translation\Translation::class,

        /*
         * Forms a club builds for itself — a waiver, an intake sheet, a
         * questionnaire. Its one surface is /f/{uuid}; app/Forms/Forms.php
         * says why this is a vertical of its own rather than part of the club.
         */
        /*
         * Language, image, speech and transcription models. Reached from the
         * super-admin screens, but Translation and the club Copilot both call
         * it too — see app/Ai/Ai.php for why its door sits at the module root
         * rather than in a Services/ folder.
         */
        \App\Ai\Ai::class,

        \App\Forms\Forms::class,

        /*
         * Certificates: printed, signed, verifiable by QR. The engine is the
         * vendored ahl/laravel-certificates package; what a certificate is FOR
         * (an event's participation and podium, later belt tests and courses)
         * is supplied by the module that owns it — see app/Certificates.
         */
        \App\Certificates\Certificates::class,

        /*
         * One designer for everything printed — certificates, badges, posters.
         * Kinds come from the modules that own them; see app/Printables.
         */
        \App\Printables\Printables::class,

        /*
         * Ranks & grading. Not a club feature: it arrives with an ACTIVITY whose
         * sport package has belts, and hangs off that activity — see
         * app/Ranks/Ranks.php. Off unless RANKS_ENABLED.
         */
        \App\Ranks\Ranks::class,

        /*
         * National rankings: points from every federation-hosted event, by the
         * WKF World Ranking formula — see app/Rankings/Rankings.php. Not the
         * belts above: a rank is worn, a ranking is where you stand. Off unless
         * RANKINGS_ENABLED.
         */
        \App\Rankings\Rankings::class,

        /*
         * Governing bodies clubs affiliate WITH (not a chain that owns them).
         * Every file lives in app/Federations — its config and migrations too.
         */
        \App\Federations\Federations::class,

        /*
         * The new UI shell (top bar, phone header and tabs, Play/Manage) and
         * the screens rebuilt on it. Off unless NEW_UI — see app/Ui/Ui.php.
         */
        \App\Ui\Ui::class,

        /*
         * Parties & relationships: who holds what role in which party, since
         * when, with what share — one dated table for owners, staff, members,
         * presidents, hosts (Documentation/major-change/PLAN.md). Its doors
         * (Relations, Authority) sit at the module root because every other
         * module calls them. PARTIES_ENABLED / PARTIES_SHADOW, both OFF.
         */
        \App\Parties\Parties::class,

        /*
         * One person, one record; one club, one record. The duplicate watch
         * (`duplicates:watch`) and the switches for the guards at every door
         * that creates a person or a club — see app/Duplicates. All OFF.
         */
        \App\Duplicates\Duplicates::class,

        /*
         * Margin, the development board: the owner and their assistant capture
         * ideas (notes, marked-up photos, sketches) and move them through a
         * pipeline. Private to devboard_members — see app/DevBoard. Off unless
         * DEVBOARD_ENABLED.
         */
        \App\DevBoard\DevBoard::class,

        /*
         * The operator's own vertical: the super-admin's room, the global
         * activity directory, platform-wide settings, and the commands that
         * back up, reset a baseline or seed a demo. Last, because it is about
         * the platform rather than about anything on it.
         *
         * The public club browser, which used to share the name
         * PlatformController, now lives in App\Clubs under a name that says
         * what it does.
         */
        \App\Platform\Platform::class,
    ],

    /*
    | The club admin workspace's nav groups, in display order, with the heading
    | each shows.
    |
    | Groups are declared here rather than by the modules because they are a
    | property of the SHELL, not of any one vertical: several modules drop
    | entries into "People", and no single one of them gets to decide whether
    | that heading sits above or below "Overview".
    |
    | Desktop and mobile are separate lists because they are separate
    | navigations, not one navigation at two widths. A group nobody contributes
    | to is not rendered.
    */
    'club_admin_groups' => [

        'desktop' => [
            'overview'   => 'nav.layouts_admin_club_group_overview',
            'people'     => 'nav.layouts_admin_club_group_people',
            'programs'   => 'nav.layouts_admin_club_group_programs',
            'storefront' => 'nav.layouts_admin_club_group_storefront',
            'content'    => 'nav.layouts_admin_club_group_content',
        ],

        'mobile' => [
            'overview'  => 'admin.nav_group_overview',
            'people'    => 'admin.nav_group_people',
            'offerings' => 'admin.nav_group_offerings',
            'store'     => 'admin.nav_group_store',
            'content'   => 'admin.nav_group_content',
            'finance'   => 'admin.nav_group_finance',
            'settings'  => 'admin.nav_group_settings',
        ],
    ],
];
