<?php

namespace App\Forms;

use App\Support\Modules\AbstractModule;

/**
 * Forms a club builds for itself.
 *
 * A questionnaire, a waiver, a sign-up sheet, a health declaration — anything
 * an organiser needs to ask a list of questions and keep the answers, without
 * the platform having to grow a feature per question.
 *
 * Its one public surface is /f/{uuid}: a form is reachable by a link, and the
 * link is the unguessable part (Unpredictable Resource Identifiers). A
 * submission belongs to the form, and to the member when one is signed in.
 *
 * ── Why this is its own module ────────────────────────────────────────────
 *
 * It was three files in three places — App\Forms\Models\Form, a controller in the
 * flat Http\Controllers, and App\Support\FormEngine — and nothing outside
 * those three ever referenced any of them. That is a vertical with no owner
 * rather than a part of the club: a form is scoped to a Tenant the way almost
 * everything on the platform is, which is not the same as belonging to the
 * club's programme, books or people.
 *
 * It passes the delete test cleanly: remove app/Forms/, its two tables and its
 * line in config/modules.php, and nothing else notices.
 */
class Forms extends AbstractModule
{
    public function key(): string
    {
        return 'forms';
    }
}
