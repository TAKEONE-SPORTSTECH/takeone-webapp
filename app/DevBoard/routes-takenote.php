<?php

/*
| Loaded by routes.php under /me with `me.` names and the member stack — except that
| VerifiedOrLinkFriend stands in for `verified` (a friend who came in through an
| invite link may write before confirming their email). NOT named routes-member.php
| on purpose: the module system gives that file the platform's own `verified`.
|
| Margin, the development board — under /me/margin, names me.margin*.
| Only registered while DEVBOARD_ENABLED; every route also re-checks who may
| use the board (EnsureBoardAccess → BoardAccess::allows). Records are
| addressed by uuid only.
*/

use App\DevBoard\Controllers\BoardController;
use App\DevBoard\Controllers\CardBackController;
use App\DevBoard\Controllers\CardToolsController;
use App\DevBoard\Controllers\NoteController;
use App\DevBoard\Controllers\TakeNoteController;
use App\DevBoard\Controllers\TakeNoteDeskController;
use App\DevBoard\Controllers\TakeNoteFriendsController;
use App\DevBoard\Controllers\TakeNoteRelationController;
use App\DevBoard\Controllers\TakeNoteShareController;
use App\DevBoard\Middleware\EnsureBoardAccess;
use App\DevBoard\Middleware\TakeNoteLocale;
use Illuminate\Support\Facades\Route;

if (! config('devboard.enabled')) {
    return;
}

Route::middleware(EnsureBoardAccess::class)->prefix('margin')->name('margin')->group(function () {
    Route::get('/', [BoardController::class, 'show'])->name('');
    Route::get('/data', [BoardController::class, 'data'])->name('.data')->middleware('throttle:120,1');
    Route::get('/bin', [BoardController::class, 'bin'])->name('.bin')->middleware('throttle:60,1');
    Route::get('/cards/{card:uuid}/notes', [NoteController::class, 'index'])->name('.notes')->middleware('throttle:120,1');
    Route::get('/pictures/{asset:uuid}', [NoteController::class, 'asset'])->name('.pictures.show')->middleware('throttle:600,1');
    Route::post('/cards/{card:uuid}/pictures', [NoteController::class, 'upload'])->name('.pictures.store')->middleware('throttle:30,1');
    Route::get('/media/{asset:uuid}', [NoteController::class, 'media'])->name('.media.show')->middleware('throttle:600,1');
    Route::get('/media/{asset:uuid}/status', [NoteController::class, 'mediaStatus'])->name('.media.status')->middleware('throttle:240,1');
    Route::post('/cards/{card:uuid}/media', [NoteController::class, 'uploadMedia'])->name('.media.store')->middleware('throttle:20,1');
    Route::post('/cards/{card:uuid}/ink', [NoteController::class, 'ink'])->name('.ink.store')->middleware('throttle:30,1');
    Route::post('/markdown', [NoteController::class, 'markdown'])->name('.markdown')->middleware('throttle:30,1');
    Route::get('/video-link', [NoteController::class, 'videoLink'])->name('.video-link')->middleware('throttle:30,1');
    Route::get('/pictures/{asset:uuid}/ink', [NoteController::class, 'inkShow'])->name('.ink.show')->middleware('throttle:120,1');

    Route::middleware('throttle:120,1')->group(function () {
        Route::post('/cards', [BoardController::class, 'store'])->name('.cards.store');
        Route::patch('/cards/{card:uuid}', [BoardController::class, 'update'])->name('.cards.update');
        Route::post('/cards/{card:uuid}/move', [BoardController::class, 'move'])->name('.cards.move');
        Route::delete('/cards/{card:uuid}', [BoardController::class, 'destroy'])->name('.cards.destroy');
        Route::post('/bin/{uuid}/restore', [BoardController::class, 'restore'])->name('.cards.restore')->whereUuid('uuid');
        Route::patch('/lanes/{lane:uuid}', [BoardController::class, 'updateLane'])->name('.lanes.update');
        Route::post('/cards/{card:uuid}/notes', [NoteController::class, 'store'])->name('.notes.store');
        Route::patch('/cards/{card:uuid}/notes/{note}', [NoteController::class, 'update'])->name('.notes.update')->whereUuid('note');
        Route::post('/cards/{card:uuid}/notes/{note}/move', [NoteController::class, 'move'])->name('.notes.move')->whereUuid('note');
        Route::delete('/cards/{card:uuid}/notes/{note}', [NoteController::class, 'destroy'])->name('.notes.destroy')->whereUuid('note');
        Route::post('/cards/{card:uuid}/notes/{note}/restore', [NoteController::class, 'restore'])->name('.notes.restore')->whereUuid('note');
        Route::post('/lanes/{lane:uuid}/move', [BoardController::class, 'moveLane'])->name('.lanes.move');
    });
});

// TakeNote's notes: the same cards, seen as a notebook (the app page itself is in routes.php).
if (config('devboard.takenote')) {
    Route::middleware([EnsureBoardAccess::class, TakeNoteLocale::class])->prefix('takenote')->name('takenote')->group(function () {
        Route::get('/data', [TakeNoteController::class, 'data'])->name('.data')->middleware('throttle:120,1');
        // the language TakeNote is read in on this device; friends (each with a notebook of their own) behind their flag
        Route::post('/locale', [TakeNoteFriendsController::class, 'locale'])->name('.locale')->middleware('throttle:30,1');
        if (config('devboard.friends')) {
            Route::get('/friends', [TakeNoteFriendsController::class, 'index'])->name('.friends')->middleware('throttle:60,1');
            Route::post('/friends', [TakeNoteFriendsController::class, 'store'])->name('.friends.store')->middleware('throttle:20,1');
            Route::post('/friends/link', [TakeNoteFriendsController::class, 'link'])->name('.friends.link')->middleware('throttle:20,1');
            Route::delete('/friends/{notebook:uuid}', [TakeNoteFriendsController::class, 'destroy'])->name('.friends.destroy')->middleware('throttle:30,1');
        }
        // a folder shared with another person, and opening one shared with you (TakeNoteShareController), behind their flag
        if (config('devboard.sharing')) {
            Route::get('/folders/{folder:uuid}/shares', [TakeNoteShareController::class, 'index'])->name('.shares')->middleware('throttle:60,1');
            Route::post('/folders/{folder:uuid}/shares', [TakeNoteShareController::class, 'store'])->name('.shares.store')->middleware('throttle:20,1');
            Route::delete('/folders/{folder:uuid}/shares/{share}', [TakeNoteShareController::class, 'destroy'])->name('.shares.destroy')->whereUuid('share')->middleware('throttle:30,1');
            Route::post('/space', [TakeNoteShareController::class, 'space'])->name('.space')->middleware('throttle:60,1');
        }
        Route::get('/notes/{card:uuid}', [TakeNoteController::class, 'show'])->name('.show')->middleware('throttle:240,1');
        // named connections between two notes (Trilium's relations), behind their flag
        if (config('devboard.relations')) {
            Route::middleware('throttle:120,1')->group(function () {
                Route::post('/relations', [TakeNoteRelationController::class, 'store'])->name('.relations.store');
                Route::patch('/relations/{relation:uuid}', [TakeNoteRelationController::class, 'update'])->name('.relations.update');
                Route::delete('/relations/{relation:uuid}', [TakeNoteRelationController::class, 'destroy'])->name('.relations.destroy');
            });
        }

        Route::middleware('throttle:120,1')->group(function () {
            Route::post('/notes', [TakeNoteController::class, 'store'])->name('.store');
            Route::patch('/notes/{card:uuid}', [TakeNoteController::class, 'update'])->name('.update');
            Route::post('/notes/{card:uuid}/place', [TakeNoteController::class, 'place'])->name('.place');
            Route::delete('/notes/{card:uuid}', [TakeNoteController::class, 'destroy'])->name('.destroy');
            Route::post('/bin/{uuid}/restore', [TakeNoteController::class, 'restore'])->name('.restore')->whereUuid('uuid');
        });

        // The open card (Trello's card back): read it whole, then its small writes. Every child is looked up inside the card.
        Route::get('/cards/{card:uuid}', [CardBackController::class, 'show'])->name('.cards.show')->middleware('throttle:240,1');
        Route::get('/cards/{card:uuid}/files/{attachment}', [CardBackController::class, 'download'])->name('.cards.files.show')->whereUuid('attachment')->middleware('throttle:240,1');
        Route::post('/cards/{card:uuid}/files', [CardBackController::class, 'attachFile'])->name('.cards.files.store')->middleware('throttle:30,1');
        Route::middleware('throttle:120,1')->group(function () {
            Route::post('/notes/{card:uuid}/convert', [CardBackController::class, 'convert'])->name('.convert');
            Route::post('/notes/{card:uuid}/folder', [TakeNoteController::class, 'file'])->name('.file');
            Route::post('/notes/{card:uuid}/pin', [TakeNoteController::class, 'pin'])->name('.pin');
            Route::post('/folders', [TakeNoteController::class, 'storeFolder'])->name('.folders.store');
            Route::patch('/folders/{folder:uuid}', [TakeNoteController::class, 'updateFolder'])->name('.folders.update');
            Route::delete('/folders/{folder:uuid}', [TakeNoteController::class, 'destroyFolder'])->name('.folders.destroy');
            Route::post('/cards/{card:uuid}/links', [CardBackController::class, 'attachLink'])->name('.cards.links.store');
            Route::patch('/cards/{card:uuid}/attachments/{attachment}', [CardBackController::class, 'renameAttachment'])->name('.cards.attachments.update')->whereUuid('attachment');
            Route::delete('/cards/{card:uuid}/attachments/{attachment}', [CardBackController::class, 'removeAttachment'])->name('.cards.attachments.destroy')->whereUuid('attachment');
            Route::post('/cards/{card:uuid}/comments', [CardBackController::class, 'comment'])->name('.cards.comments.store');
            Route::patch('/cards/{card:uuid}/comments/{comment}', [CardBackController::class, 'editComment'])->name('.cards.comments.update')->whereUuid('comment');
            Route::delete('/cards/{card:uuid}/comments/{comment}', [CardBackController::class, 'deleteComment'])->name('.cards.comments.destroy')->whereUuid('comment');
            Route::post('/cards/{card:uuid}/checklists', [CardBackController::class, 'addChecklist'])->name('.cards.checklists.store');
            Route::delete('/cards/{card:uuid}/checklists/{list}', [CardBackController::class, 'removeChecklist'])->name('.cards.checklists.destroy')->whereUuid('list');
            Route::post('/cards/{card:uuid}/checklists/{list}/steps', [CardBackController::class, 'addStep'])->name('.cards.steps.store')->whereUuid('list');
            Route::patch('/cards/{card:uuid}/steps/{step}', [CardBackController::class, 'updateStep'])->name('.cards.steps.update')->whereUuid('step');
            Route::delete('/cards/{card:uuid}/steps/{step}', [CardBackController::class, 'removeStep'])->name('.cards.steps.destroy')->whereUuid('step');
            Route::post('/cards/{card:uuid}/cover', [CardBackController::class, 'cover'])->name('.cards.cover');

            // Trello's card tools: labels, people, dates, cover style, archive, templates, copy, reactions
            Route::post('/labels', [CardToolsController::class, 'storeLabel'])->name('.labels.store');
            Route::patch('/labels/{label:uuid}', [CardToolsController::class, 'updateLabel'])->name('.labels.update');
            Route::delete('/labels/{label:uuid}', [CardToolsController::class, 'destroyLabel'])->name('.labels.destroy');
            Route::post('/cards/{card:uuid}/labels', [CardToolsController::class, 'toggleLabel'])->name('.cards.labels');
            Route::post('/cards/{card:uuid}/members', [CardToolsController::class, 'toggleMember'])->name('.cards.members');
            Route::post('/cards/{card:uuid}/watch', [CardToolsController::class, 'watch'])->name('.cards.watch');
            Route::post('/cards/{card:uuid}/dates', [CardToolsController::class, 'dates'])->name('.cards.dates');
            Route::post('/cards/{card:uuid}/complete', [CardToolsController::class, 'complete'])->name('.cards.complete');
            Route::post('/cards/{card:uuid}/cover-style', [CardToolsController::class, 'coverStyle'])->name('.cards.cover-style');
            Route::post('/cards/{card:uuid}/archive', [CardToolsController::class, 'archive'])->name('.cards.archive');
            Route::post('/cards/{card:uuid}/template', [CardToolsController::class, 'template'])->name('.cards.template');
            Route::post('/cards/{card:uuid}/copy', [CardToolsController::class, 'copy'])->name('.cards.copy');
            Route::patch('/cards/{card:uuid}/steps/{step}/plan', [CardToolsController::class, 'stepPlan'])->name('.cards.steps.plan')->whereUuid('step');
            Route::post('/cards/{card:uuid}/steps/{step}/card', [CardToolsController::class, 'stepToCard'])->name('.cards.steps.card')->whereUuid('step');
            Route::post('/cards/{card:uuid}/comments/{comment}/react', [CardToolsController::class, 'react'])->name('.cards.comments.react')->whereUuid('comment');
        });
        Route::get('/archived', [CardToolsController::class, 'archived'])->name('.archived')->middleware('throttle:60,1');
    });
}

// TakeNote on a desk: the notebook full screen, as a website of its own.
if (config('devboard.takenote')) {
    Route::get('/notes', [TakeNoteDeskController::class, 'show'])->name('notes')
        ->middleware([EnsureBoardAccess::class, TakeNoteLocale::class, 'throttle:60,1']);
}
