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
        \App\Media\Media::class,
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
