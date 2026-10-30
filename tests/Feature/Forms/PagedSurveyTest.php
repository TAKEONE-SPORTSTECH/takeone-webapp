<?php

namespace Tests\Feature\Forms;

use App\Forms\Models\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagedSurveyTest extends TestCase
{
    use RefreshDatabase;

    private function import(): Form
    {
        $this->artisan('forms:import-template', ['name' => 'club-questionnaire'])->assertSuccessful();

        return Form::latest('id')->firstOrFail();
    }

    private function grid(string $v = 'must'): array
    {
        $out = [];
        for ($i = 1; $i <= 22; $i++) {
            $out[sprintf('f%02d', $i)] = $v;
        }

        return $out;
    }

    public function test_the_template_imports_once_with_a_visit_token(): void
    {
        $form = $this->import();
        $this->artisan('forms:import-template', ['name' => 'club-questionnaire'])->assertSuccessful();

        $this->assertSame(1, Form::count());
        $this->assertTrue($form->isPaged());
        $this->assertSame(40, strlen($form->settings['visitToken']));
        $this->assertSame('survey', $form->type);
    }

    public function test_a_bad_template_name_is_refused(): void
    {
        $this->artisan('forms:import-template', ['name' => '../config'])->assertFailed();
        $this->assertSame(0, Form::count());
    }

    public function test_the_public_link_never_ships_the_private_pages(): void
    {
        $form = $this->import();

        $html = $this->get('/f/'.$form->uuid)->assertOk()->getContent();
        $this->assertStringContainsString('q21', $html);
        $this->assertStringNotContainsString('cover_club', $html);
        $this->assertStringNotContainsString('obs1', $html);
        $this->assertStringNotContainsString($form->settings['visitToken'], $html);
    }

    public function test_the_visit_link_shows_the_private_pages(): void
    {
        $form = $this->import();

        $this->get('/f/'.$form->uuid.'?v='.$form->settings['visitToken'])
            ->assertOk()->assertSee('cover_club', false)->assertSee('obs1', false);
    }

    public function test_phones_get_the_one_question_layout(): void
    {
        $form = $this->import();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148')
            ->get('/f/'.$form->uuid)->assertOk()->assertSee('q-head', false);
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)')
            ->get('/f/'.$form->uuid)->assertOk()->assertSee('rail', false);
    }

    public function test_a_public_answer_keeps_only_declared_keys(): void
    {
        $form = $this->import();

        $this->postJson('/f/'.$form->uuid, [
            'lang' => 'ar',
            'fields' => [
                'grid' => $this->grid(),
                'q21' => 4, 'q21__note' => '2–3 hours',
                'q2' => ['taekwondo'], 'q2__other' => 'Kids self-defence',
                'top5' => ['f05', 'f08'],
                'q1' => ['active-members' => 140],
                'obs1' => 'private note smuggled in',
                'evil' => str_repeat('x', 10),
            ],
        ])->assertOk()->assertJson(['success' => true]);

        $data = $form->submissions()->firstOrFail()->data;
        $this->assertSame(4, $data['q21']);
        $this->assertSame('2–3 hours', $data['q21__note']);
        $this->assertSame(['f05', 'f08'], $data['top5']);
        $this->assertSame(140, $data['q1']['active-members']);
        $this->assertArrayNotHasKey('obs1', $data);
        $this->assertArrayNotHasKey('evil', $data);
        $this->assertSame('self', $data['_mode']);
        $this->assertSame('ar', $data['_lang']);
    }

    public function test_the_visit_link_keeps_private_answers(): void
    {
        $form = $this->import();

        $this->postJson('/f/'.$form->uuid, [
            'visit' => $form->settings['visitToken'],
            'fields' => ['grid' => $this->grid('nice'), 'cover_club' => 'Falcon Kicks', 'consent' => true, 'obs1' => 'Blue notebook'],
        ])->assertOk();

        $data = $form->submissions()->firstOrFail()->data;
        $this->assertSame('Blue notebook', $data['obs1']);
        $this->assertSame('visit', $data['_mode']);
    }

    public function test_another_forms_visit_token_unlocks_nothing(): void
    {
        $a = $this->import();
        $this->artisan('forms:import-template', ['name' => 'club-questionnaire', '--new' => true])->assertSuccessful();
        $b = Form::latest('id')->firstOrFail();
        $this->assertNotSame($a->settings['visitToken'], $b->settings['visitToken']);

        $this->get('/f/'.$a->uuid.'?v='.$b->settings['visitToken'])->assertOk()->assertDontSee('cover_club', false);
        $this->postJson('/f/'.$a->uuid, [
            'visit' => $b->settings['visitToken'],
            'fields' => ['grid' => $this->grid(), 'obs1' => 'x'],
        ])->assertOk();
        $this->assertArrayNotHasKey('obs1', $a->submissions()->firstOrFail()->data);
    }

    public function test_the_survey_types_are_validated(): void
    {
        $form = $this->import();
        $url = '/f/'.$form->uuid;

        $partial = $this->grid();
        unset($partial['f07']);
        $this->postJson($url, ['fields' => ['grid' => $partial]])->assertStatus(422);
        $this->postJson($url, ['fields' => ['grid' => $this->grid('maybe')]])->assertStatus(422);
        $this->postJson($url, ['fields' => ['grid' => $this->grid(), 'q21' => 9]])->assertStatus(422);
        $this->postJson($url, ['fields' => ['grid' => $this->grid(), 'top5' => ['f01', 'f01']]])->assertStatus(422);
        $this->postJson($url, ['fields' => ['grid' => $this->grid(), 'top5' => ['f01', 'f02', 'f03', 'f04', 'f05', 'f06']]])->assertStatus(422);
        $this->postJson($url, ['fields' => ['grid' => $this->grid(), 'q2' => ['not-a-sport']]])->assertStatus(422);
        $this->postJson($url, ['fields' => ['grid' => $this->grid(), 'q1' => ['made-up-row' => 3]]])->assertStatus(422);

        $this->assertSame(0, $form->submissions()->count());
    }

    public function test_an_inactive_or_unknown_form_is_not_found(): void
    {
        $form = $this->import();
        $form->update(['is_active' => false]);

        $this->get('/f/'.$form->uuid)->assertNotFound();
        $this->get('/f/00000000-0000-4000-8000-000000000000')->assertNotFound();
    }

    public function test_a_plain_form_keeps_its_old_page(): void
    {
        $form = Form::create(['title' => 'Waiver', 'type' => 'generic', 'schema' => ['pages' => [['id' => 'p', 'fields' => [
            ['key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
        ]]]], 'settings' => [], 'is_active' => true]);

        $this->get('/f/'.$form->uuid)->assertOk()->assertDontSee('q-head', false)->assertDontSee('rail', false);
    }

    public function test_the_federation_questionnaire_imports_and_takes_answers(): void
    {
        $this->artisan('forms:import-template', ['name' => 'federation-questionnaire'])->assertSuccessful();
        $form = Form::where('settings->template', 'federation-questionnaire')->firstOrFail();
        $this->assertSame('What does your federation really need?', $form->title);
        $this->assertCount(24, $form->settings['analysis']['status']);

        $grid = [];
        for ($i = 1; $i <= 24; $i++) {
            $grid[sprintf('f%02d', $i)] = 'nice';
        }
        $this->postJson('/f/'.$form->uuid, ['fields' => ['grid' => $grid, 'q1' => ['member-clubs' => 38]]])->assertOk();
        $this->assertSame(38, $form->submissions()->firstOrFail()->data['q1']['member-clubs']);
    }
}
