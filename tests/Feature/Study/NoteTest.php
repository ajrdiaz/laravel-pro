<?php

namespace Tests\Feature\Study;

use App\Models\Module;
use App\Models\Note;
use App\Models\StudyDay;
use App\Models\User;
use Database\Seeders\LessonSeeder;
use Database\Seeders\RoadmapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    private function save(User $user, Module $module, mixed $body): TestResponse
    {
        return $this->actingAs($user)->putJson(route('notes.update', $module->slug), ['body' => $body]);
    }

    public function test_guests_cannot_save_notes()
    {
        $module = Module::factory()->create();

        $this->putJson(route('notes.update', $module->slug), ['body' => 'hola'])->assertUnauthorized();
        $this->assertSame(0, Note::count());
    }

    public function test_it_saves_a_note_for_the_user_and_module()
    {
        $user = User::factory()->create();
        $module = Module::factory()->create();

        $this->save($user, $module, 'Recordar: el contenedor resuelve las dependencias.')
            ->assertOk()
            ->assertExactJson(['saved' => true]);

        $this->assertDatabaseHas('notes', [
            'user_id' => $user->id,
            'module_id' => $module->id,
            'body' => 'Recordar: el contenedor resuelve las dependencias.',
        ]);
    }

    public function test_saving_again_replaces_the_note_instead_of_adding_another()
    {
        $user = User::factory()->create();
        $module = Module::factory()->create();

        $this->save($user, $module, 'primera versión')->assertOk();
        $id = Note::firstOrFail()->id;

        $this->save($user, $module, 'segunda versión')->assertOk();

        $this->assertSame(1, Note::count());
        $this->assertSame($id, Note::firstOrFail()->id);
        $this->assertSame('segunda versión', Note::firstOrFail()->body);
    }

    public function test_the_text_is_kept_as_typed_with_its_line_breaks_and_markup()
    {
        $user = User::factory()->create();
        $module = Module::factory()->create();
        $body = "Línea 1\n    sangrada\n\n<script>alert(1)</script> y `código`";

        $this->save($user, $module, $body)->assertOk();

        // Notes are plain text: nothing is rewritten here, and the page shows them as text.
        $this->assertSame($body, Note::firstOrFail()->body);
    }

    public function test_an_empty_or_blank_note_removes_it()
    {
        $user = User::factory()->create();
        $module = Module::factory()->create();

        foreach (['', '   ', "\n\n \t", null] as $empty) {
            Note::factory()->create(['user_id' => $user->id, 'module_id' => $module->id]);

            $this->save($user, $module, $empty)->assertOk();

            $this->assertSame(0, Note::count(), 'Emptying the note should delete it.');
        }
    }

    public function test_a_missing_body_counts_as_empty()
    {
        $user = User::factory()->create();
        $module = Module::factory()->create();
        Note::factory()->create(['user_id' => $user->id, 'module_id' => $module->id]);

        $this->actingAs($user)->putJson(route('notes.update', $module->slug))->assertOk();

        $this->assertSame(0, Note::count());
    }

    public function test_emptying_a_note_that_never_existed_creates_nothing_and_is_not_an_error()
    {
        $this->save(User::factory()->create(), Module::factory()->create(), '')->assertOk();

        $this->assertSame(0, Note::count());
    }

    public function test_a_note_can_be_as_long_as_the_limit_but_no_longer()
    {
        $user = User::factory()->create();
        $module = Module::factory()->create();

        $this->save($user, $module, str_repeat('a', Note::MAX_LENGTH))->assertOk();
        $this->assertSame(Note::MAX_LENGTH, mb_strlen(Note::firstOrFail()->body));

        $this->save($user, $module, str_repeat('b', Note::MAX_LENGTH + 1))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body' => 'La nota es demasiado larga (hasta 20.000 caracteres).']);

        $this->assertSame(str_repeat('a', Note::MAX_LENGTH), Note::firstOrFail()->body, 'A rejected save must not touch the old note.');
    }

    public function test_the_body_must_be_text()
    {
        $this->save(User::factory()->create(), Module::factory()->create(), ['no', 'es', 'texto'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->assertSame(0, Note::count());
    }

    public function test_an_unknown_module_is_not_found()
    {
        $this->actingAs(User::factory()->create())
            ->putJson(route('notes.update', 'no-existe'), ['body' => 'hola'])
            ->assertNotFound();
    }

    public function test_notes_belong_to_each_user_and_never_overwrite_each_other()
    {
        $module = Module::factory()->create();
        $ana = User::factory()->create();
        $luis = User::factory()->create();

        $this->save($ana, $module, 'nota de Ana')->assertOk();
        $this->save($luis, $module, 'nota de Luis')->assertOk();

        $this->assertSame(2, Note::count());
        $this->assertSame('nota de Ana', Note::where('user_id', $ana->id)->firstOrFail()->body);
        $this->assertSame('nota de Luis', Note::where('user_id', $luis->id)->firstOrFail()->body);

        // Emptying one leaves the other.
        $this->save($ana, $module, '')->assertOk();

        $this->assertSame(['nota de Luis'], Note::pluck('body')->all());
    }

    public function test_one_user_can_keep_a_note_per_module()
    {
        $user = User::factory()->create();
        [$first, $second] = Module::factory()->count(2)->create();

        $this->save($user, $first, 'sobre el primero')->assertOk();
        $this->save($user, $second, 'sobre el segundo')->assertOk();

        $this->assertSame(2, Note::where('user_id', $user->id)->count());
    }

    public function test_writing_a_note_counts_as_studying_today_but_deleting_it_does_not()
    {
        $user = User::factory()->create();
        $module = Module::factory()->create();

        $this->save($user, $module, '')->assertOk();
        $this->assertSame(0, StudyDay::where('user_id', $user->id)->count());

        $this->save($user, $module, 'algo')->assertOk();
        $this->save($user, $module, 'algo más')->assertOk();

        $this->assertSame(1, StudyDay::where('user_id', $user->id)->count(), 'Saving again the same day is still one study day.');
    }

    public function test_the_module_page_brings_the_users_own_note()
    {
        $this->seed([RoadmapSeeder::class, LessonSeeder::class]);
        $user = User::factory()->create();
        $module = Module::where('slug', 'req')->firstOrFail();

        $this->actingAs($user)->get(route('learn.module', 'req'))
            ->assertInertia(fn (Assert $page) => $page->component('LearnModule')->where('note', null));

        Note::factory()->create(['user_id' => $user->id, 'module_id' => $module->id, 'body' => 'mi nota']);
        Note::factory()->create(['module_id' => $module->id, 'body' => 'la nota de otra persona']);

        $this->actingAs($user)->get(route('learn.module', 'req'))
            ->assertInertia(fn (Assert $page) => $page->where('note', 'mi nota'));
    }

    public function test_the_lesson_page_brings_the_note_of_its_module()
    {
        $this->seed([RoadmapSeeder::class, LessonSeeder::class]);
        $user = User::factory()->create();

        Note::factory()->create([
            'user_id' => $user->id,
            'module_id' => Module::where('slug', 'req')->firstOrFail()->id,
            'body' => 'nota de req',
        ]);

        $this->actingAs($user)->get(route('learn.lesson', ['req', 'ciclo-de-una-request']))
            ->assertInertia(fn (Assert $page) => $page->component('LearnLesson')->where('note', 'nota de req'));

        // A module without a note shows none, even though another module has one.
        $this->actingAs($user)->get(route('learn.lesson', ['php', Module::where('slug', 'php')->firstOrFail()->lessons()->firstOrFail()->slug]))
            ->assertInertia(fn (Assert $page) => $page->where('note', null));
    }

    public function test_resetting_progress_keeps_the_notes()
    {
        $user = User::factory()->create();
        $module = Module::factory()->create();

        $this->save($user, $module, 'esto no es progreso')->assertOk();

        $this->actingAs($user)->delete(route('progress.destroy'))->assertRedirect(route('dashboard'));

        $this->assertSame(1, Note::where('user_id', $user->id)->count());
    }

    public function test_notes_go_away_with_their_user_or_module()
    {
        $user = User::factory()->create();
        $module = Module::factory()->create();
        $other = Module::factory()->create();

        $this->save($user, $module, 'a')->assertOk();
        $this->save($user, $other, 'b')->assertOk();

        $module->delete();
        $this->assertSame(['b'], Note::pluck('body')->all());

        $user->delete();
        $this->assertSame(0, Note::count());
    }
}
