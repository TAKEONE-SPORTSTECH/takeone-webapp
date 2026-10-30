<?php

namespace App\Forms\Commands;

use App\Forms\Models\Form;
use App\Members\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Loads a ready-made form from app/Forms/templates/{name}.json as a platform
 * form (tenant NULL) and prints its two links: the public one a club opens,
 * and the visit link that also carries the visitor's private pages.
 *
 * Idempotent: a template already imported is left alone and its links are
 * printed again, unless --new is passed. Writes one row; nothing is changed
 * or deleted.
 */
class ImportFormTemplate extends Command
{
    protected $signature = 'forms:import-template {name : file name in app/Forms/templates, without .json} {--new : import a second copy}';

    protected $description = 'Import a ready-made form (e.g. club-questionnaire) and print its links';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        if (! preg_match('/^[a-z0-9-]{1,60}$/', $name)) {
            $this->error('Template names are lower-case letters, digits and dashes.');

            return self::FAILURE;
        }
        $file = app_path("Forms/templates/{$name}.json");
        if (! is_file($file)) {
            $this->error("No template at {$file}");

            return self::FAILURE;
        }
        $t = json_decode((string) file_get_contents($file), true);
        if (! is_array($t) || empty($t['schema']['pages'])) {
            $this->error('The template is not valid JSON with schema.pages.');

            return self::FAILURE;
        }

        $form = $this->option('new') ? null : Form::whereNull('tenant_id')
            ->where('settings->template', $name)->latest('id')->first();

        if (! $form) {
            $settings = array_merge($t['settings'] ?? [], [
                'template' => $name,
                'title_ar' => $t['title_ar'] ?? null,
                'description_ar' => $t['description_ar'] ?? null,
                'visitToken' => Str::random(40),
            ]);
            $form = Form::create([
                'tenant_id' => null,
                'title' => $t['title'],
                'type' => in_array($t['type'] ?? '', Form::TYPES, true) ? $t['type'] : 'generic',
                'description' => $t['description'] ?? null,
                'schema' => $t['schema'],
                'settings' => $settings,
                'is_active' => true,
                'created_by' => User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->value('id'),
            ]);
            $this->info("Imported \"{$form->title}\".");
        } else {
            $this->line("Already imported as form #{$form->id}; links below.");
            // A template that later learned how to propose decisions hands
            // that to the copy already in use — the only key ever merged.
            if (isset($t['settings']['analysis']) && empty($form->settings['analysis'])) {
                $form->update(['settings' => array_merge($form->settings ?? [], ['analysis' => $t['settings']['analysis']])]);
                $this->info('Added the decision settings from the template.');
            }
        }

        $this->line('Public link (club fills it in): '.route('forms.show', $form->uuid));
        $this->line('Visit link (interviewer, private pages): '.route('forms.show', $form->uuid).'?v='.$form->settings['visitToken']);

        return self::SUCCESS;
    }
}
