<?php

namespace Tests\Feature\Forms;

use App\Forms\Models\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormAdminTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    private function admin()
    {
        $u = $this->createUser();
        $this->makeSuperAdmin($u);

        return $u;
    }

    private function questionnaire(): Form
    {
        $this->artisan('forms:import-template', ['name' => 'club-questionnaire'])->assertSuccessful();

        return Form::latest('id')->firstOrFail();
    }

    private function answer(Form $form, array $fields, bool $visit = false): void
    {
        $grid = [];
        for ($i = 1; $i <= 22; $i++) {
            $grid[sprintf('f%02d', $i)] = $i <= 5 ? 'must' : 'no';
        }
        $this->postJson('/f/'.$form->uuid, ['fields' => $fields + ['grid' => $grid], 'visit' => $visit ? $form->settings['visitToken'] : null])->assertOk();
    }

    public function test_only_a_super_admin_reaches_any_of_it(): void
    {
        $form = $this->questionnaire();
        $member = $this->createUser();

        $this->actingAs($member)->get(route('admin.forms.index'))->assertRedirect('/');
        $this->actingAs($member)->get(route('admin.forms.show', $form->uuid))->assertRedirect('/');
        $this->actingAs($member)->get(route('admin.forms.export', $form->uuid))->assertRedirect('/');
        $this->actingAs($member)->postJson(route('admin.forms.toggle', $form->uuid))->assertForbidden();
        $this->actingAs($member)->postJson(route('admin.forms.store'), ['form' => ['title' => 'x']])->assertForbidden();
        $this->actingAs($member)->deleteJson(route('admin.forms.destroy', $form->uuid))->assertForbidden();
        $this->assertTrue($form->fresh()->is_active);
        $this->assertSame(1, Form::count());
    }

    public function test_the_list_and_results_render_on_desktop_and_phone(): void
    {
        $admin = $this->admin();
        $form = $this->questionnaire();
        $this->answer($form, ['q21' => 5, 'q2' => ['karate'], 'speak1' => 'One red list of who owes me']);
        $this->answer($form, ['q21' => 3, 'q2' => ['karate', 'judo'], 'cover_club' => 'Judo Isa Town', 'consent' => true], visit: true);

        foreach ([[], ['User-Agent' => self::PHONE]] as $h) {
            $this->actingAs($admin)->withHeaders($h)->get(route('admin.forms.index'))->assertOk()
                ->assertSee('What does your club really need?');
            $this->actingAs($admin)->withHeaders($h)->get(route('admin.forms.show', $form->uuid))->assertOk()
                ->assertSee('One red list of who owes me')->assertSee('Judo Isa Town')->assertSee('fa-panels', false);
        }
    }

    public function test_the_analytics_count_correctly(): void
    {
        $form = $this->questionnaire();
        $this->answer($form, ['q21' => 5, 'q2' => ['karate'], 'top5' => ['f01', 'f02']]);
        $this->answer($form, ['q21' => 3, 'q2' => ['karate', 'judo'], 'top5' => ['f02']]);

        $stats = \App\Forms\Support\SurveyAnalytics::build($form->fresh(), \App\Forms\Support\SurveyResponses::rows($form));
        $qs = collect($stats['pages'])->flatMap(fn ($p) => $p['questions'])->keyBy('key');

        $this->assertSame(2, $stats['n']);
        $this->assertSame(4.0, (float) $qs['q21']['mean']);
        $this->assertSame(['Karate', 2, 100], [$qs['q2']['rows'][0]['label'], $qs['q2']['rows'][0]['count'], $qs['q2']['rows'][0]['pct']]);
        $this->assertSame('f02', collect($form->fields())->firstWhere('key', 'top5')['rows'][1]['value']);
        $this->assertSame(9, $qs['top5']['rows'][0]['points']); // f02: 4 (2nd) + 5 (1st)
        $this->assertSame(100, $qs['grid']['rows'][0]['score']);

        // the declared decision rules: f05 reminders is partial + small → Now
        $now = collect($stats['decisions']['lanes']['now'])->pluck('value')->all();
        $this->assertContains('f05', $now);
        $this->assertContains('f01', collect($stats['decisions']['lanes']['show'])->pluck('value')->all());
    }

    public function test_example_answers_are_shown_but_never_stored(): void
    {
        $admin = $this->admin();
        $form = $this->questionnaire();

        $this->actingAs($admin)->get(route('admin.forms.show', $form->uuid).'?example=1')->assertOk()
            ->assertSee(__('forms::admin.example_on'))->assertSee('Falcon Kicks');
        $this->assertSame(0, $form->submissions()->count());
    }

    public function test_the_export_is_a_safe_spreadsheet(): void
    {
        $admin = $this->admin();
        $form = $this->questionnaire();
        $this->answer($form, ['q21' => 4, 'speak1' => '=HYPERLINK("http://x")']);

        $csv = $this->actingAs($admin)->get(route('admin.forms.export', $form->uuid))->assertOk()->streamedContent();
        $this->assertStringContainsString('q21', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_a_new_form_is_built_and_sanitised(): void
    {
        $admin = $this->admin();
        $res = $this->actingAs($admin)->postJson(route('admin.forms.store'), ['form' => [
            'title' => '<b>Parents</b> survey', 'title_ar' => 'استبيان الأهل', 'type' => 'survey', 'active' => true, 'interviewer' => true,
            'pages' => [['title' => 'One', 'fields' => [
                ['type' => 'radio', 'label' => 'Happy?', 'label_ar' => 'راضٍ؟', 'options' => [['label' => 'Yes'], ['label' => 'Yes'], ['label' => '']]],
                ['type' => 'file', 'label' => 'Upload'],
                ['type' => 'scale', 'label' => 'Rate', 'key' => '../../etc', 'note' => true],
                ['type' => 'note', 'label' => 'Visitor'],
            ]]],
        ]])->assertOk()->assertJsonPath('success', true);

        $form = Form::where('uuid', $res->json('form.uuid'))->firstOrFail();
        $fields = $form->fields();
        $this->assertSame('Parents survey', $form->title);
        $this->assertTrue($form->isPaged());
        $this->assertSame(40, strlen($form->settings['visitToken']));
        $this->assertCount(3, $fields, 'the file question is dropped');
        $this->assertSame(['yes', 'yes-2'], array_column($fields[0]['options'], 'value'));
        $this->assertMatchesRegularExpression('/^q_[a-z0-9]{6}$/', $fields[1]['key']);
        $this->assertTrue($fields[2]['private'], 'a visitor note is always private');

        // keys survive an edit, so answers stay attached
        $state = $res->json('form.state');
        $state['pages'][0]['fields'][0]['label'] = 'Are you happy?';
        $this->actingAs($admin)->putJson(route('admin.forms.update', $form->uuid), ['form' => $state])->assertOk();
        $this->assertSame($fields[0]['key'], $form->fresh()->fields()[0]['key']);
        $this->assertSame('Are you happy?', $form->fresh()->fields()[0]['label']);

        // switching the visitor link off kills the old one
        $state['interviewer'] = false;
        $this->actingAs($admin)->putJson(route('admin.forms.update', $form->uuid), ['form' => $state])->assertOk();
        $this->assertArrayNotHasKey('visitToken', $form->fresh()->settings);
    }

    public function test_a_form_needs_a_title_and_worded_questions(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson(route('admin.forms.store'), ['form' => ['title' => '', 'pages' => [['fields' => [['type' => 'text', 'label' => '']]]]]])
            ->assertStatus(422)->assertJsonValidationErrors(['title', 'pages.0.fields.0.label']);
        $this->assertSame(0, Form::count());
    }

    public function test_the_builder_renders_on_desktop_and_phone(): void
    {
        $admin = $this->admin();
        $form = $this->questionnaire();
        foreach ([[], ['User-Agent' => self::PHONE]] as $h) {
            $this->actingAs($admin)->withHeaders($h)->get(route('admin.forms.create'))->assertOk()->assertSee('formBuilder', false);
            $this->actingAs($admin)->withHeaders($h)->get(route('admin.forms.edit', $form->uuid))->assertOk()->assertSee('q21', false);
        }
    }

    public function test_duplicate_toggle_and_delete(): void
    {
        $admin = $this->admin();
        $form = $this->questionnaire();
        $this->answer($form, ['q21' => 4]);

        $copy = $this->actingAs($admin)->postJson(route('admin.forms.duplicate', $form->uuid))->assertOk()->json('form');
        $dup = Form::where('uuid', $copy['uuid'])->firstOrFail();
        $this->assertFalse($dup->is_active);
        $this->assertSame(0, $dup->submissions()->count());
        $this->assertNotSame($form->settings['visitToken'], $dup->settings['visitToken']);

        $this->actingAs($admin)->postJson(route('admin.forms.toggle', $form->uuid))->assertOk()->assertJsonPath('form.active', false);
        // signed in, the app turns a 404 into a redirect home — either way nothing renders
        $this->assertContains($this->get('/f/'.$form->uuid)->status(), [302, 404]);

        $this->actingAs($admin)->deleteJson(route('admin.forms.destroy', $form->uuid))->assertOk();
        $this->assertNull(Form::find($form->id));
        $this->assertSame(0, \App\Forms\Models\FormSubmission::where('form_id', $form->id)->count());
        $this->assertNotNull($dup->fresh());
    }

    public function test_an_unknown_form_is_not_found(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson(route('admin.forms.show', '00000000-0000-4000-8000-000000000000'))->assertNotFound();
    }

    private function png(): string
    {
        $im = imagecreatetruecolor(16, 10);
        ob_start();
        imagepng($im);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }

    public function test_cover_artwork_is_uploaded_served_and_cleaned_up(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $admin = $this->admin();
        $form = $this->questionnaire();

        // a script pretending to be a picture is refused
        $this->actingAs($admin)->postJson(route('admin.forms.cover', $form->uuid), ['image' => 'data:image/png;base64,'.base64_encode('<?php echo 1;')])->assertStatus(422);

        $url = $this->actingAs($admin)->postJson(route('admin.forms.cover', $form->uuid), ['image' => $this->png()])->assertOk()->json('cover_url');
        $path = $form->fresh()->settings['cover'];
        $this->assertStringStartsWith('platform/forms/'.$form->uuid.'/cover/', $path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($path);

        // anyone may see an open form's artwork, on the page and at its own address
        auth()->logout();
        $this->get('/f/'.$form->uuid)->assertOk()->assertSee(substr(md5($path), 0, 10), false); // the artwork's address, inside the page's config
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // replacing it removes the old file
        $this->actingAs($admin)->postJson(route('admin.forms.cover', $form->uuid), ['image' => $this->png()])->assertOk();
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($path);
        $path = $form->fresh()->settings['cover'];

        // a copy gets its own file, so deleting one never empties the other
        $copy = \App\Forms\Models\Form::where('uuid', $this->actingAs($admin)->postJson(route('admin.forms.duplicate', $form->uuid))->json('form.uuid'))->first();
        $this->assertNotSame($path, $copy->settings['cover']);

        // a closed form's artwork is shown to nobody else
        $this->actingAs($admin)->postJson(route('admin.forms.toggle', $form->uuid))->assertOk();
        auth()->logout();
        $this->get(route('forms.cover', $form->uuid))->assertNotFound();

        // the file goes before the form does
        $this->actingAs($admin)->deleteJson(route('admin.forms.destroy', $form->uuid))->assertOk();
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($copy->settings['cover']);
    }

    public function test_only_a_super_admin_sets_the_artwork(): void
    {
        $form = $this->questionnaire();
        $this->actingAs($this->createUser())->postJson(route('admin.forms.cover', $form->uuid), ['image' => $this->png()])->assertForbidden();
        $this->assertArrayNotHasKey('cover', $form->fresh()->settings);
    }

    public function test_any_form_prints_as_the_questionnaire_pdf_and_a_qr_poster(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $admin = $this->admin();
        $form = $this->questionnaire();

        // The booklet's layout is read from the form itself.
        $layout = app(\App\Forms\Support\FormPdf::class)->layout($form);
        $this->assertSame('Club name', $layout['cover']['fields'][0]['label']);
        $this->assertNotNull($layout['cover']['consent']);
        $this->assertTrue($layout['interviewer']);
        $this->assertContains('grid', array_column($layout['blocks'], 'kind'));
        $this->assertContains('speak', array_column($layout['blocks'], 'kind'));
        $this->assertSame('free', $layout['free']['key']);
        $this->assertSame('Visit summary', $layout['summary']['page']['title']);

        $html = app(\App\Forms\Support\FormPdf::class)->html($form);
        $this->assertStringContainsString('What does your club <em>really</em> need?', $html);
        $this->assertStringContainsString('How much time goes into chasing late payments?', $html);
        $this->assertStringNotContainsString('https://', strtok(substr($html, strpos($html, '<style>')), '<'.'/style>') ?: '');

        // Printing is Chrome's job; here it is stood in for.
        $this->app->instance(\App\Printables\Support\ChromeRenderer::class, new class extends \App\Printables\Support\ChromeRenderer {
            public function pdf(string $html): string { return '%PDF-1.4 test'; }
            public function png(string $html, float $width, float $height, int $dpi = 96, bool $transparent = false): string { return "\x89PNG test"; }
        });
        $this->actingAs($admin)->get(route('admin.forms.pdf', $form->uuid))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($admin)->get(route('admin.forms.poster', $form->uuid))->assertOk()->assertHeader('content-type', 'image/png');

        // A form built from scratch prints too.
        $res = $this->actingAs($admin)->postJson(route('admin.forms.store'), ['form' => ['title' => 'Parents survey', 'pages' => [['title' => 'One', 'fields' => [
            ['type' => 'radio', 'label' => 'Happy?', 'options' => [['label' => 'Yes'], ['label' => 'No']]],
            ['type' => 'scale', 'label' => 'Rate us'],
        ]]]]])->assertOk();
        $this->actingAs($admin)->get(route('admin.forms.pdf', $res->json('form.uuid')))->assertOk();

        // Only a super-admin.
        $this->actingAs($this->createUser())->get(route('admin.forms.pdf', $form->uuid))->assertRedirect('/');
        $this->actingAs($this->createUser())->get(route('admin.forms.poster', $form->uuid))->assertRedirect('/');
    }
}
