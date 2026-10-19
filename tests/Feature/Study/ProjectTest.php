<?php

namespace Tests\Feature\Study;

use App\Enums\CompletableType;
use App\Models\Completion;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Stack;
use App\Models\StudyDay;
use App\Models\User;
use App\Services\ProgressReport;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RoadmapSeeder;
use Database\Seeders\StackSeeder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\Concerns\ReadsContentFiles;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use ReadsContentFiles;
    use RefreshDatabase;

    private function seedProjects(): void
    {
        $this->seed([RoadmapSeeder::class, StackSeeder::class, ProjectSeeder::class]);
    }

    private function tick(User $user, ProjectMilestone $milestone): void
    {
        Completion::create([
            'user_id' => $user->id,
            'completable_type' => 'milestone',
            'completable_id' => $milestone->id,
        ]);
    }

    public function test_it_loads_every_project_with_its_milestones_modules_and_stack()
    {
        $this->seedProjects();

        $file = $this->projectsFile();

        $this->assertSame($file->count(), Project::count());
        $this->assertSame($file->sum(fn (array $project) => count($project['milestones'])), ProjectMilestone::count());

        foreach ($file as $data) {
            $project = Project::where('slug', $data['slug'])->with(['milestones', 'modules', 'stack'])->firstOrFail();

            $this->assertSame($data['level'], $project->level->value);
            $this->assertSame($data['stack'], $project->stack?->slug);
            $this->assertEqualsCanonicalizing($data['modules'], $project->modules->pluck('slug')->all());
            $this->assertSame(
                array_column($data['milestones'], 'title'),
                $project->milestones->pluck('title')->all(),
                'Milestones should keep the order of the file.',
            );
        }
    }

    public function test_every_project_is_complete_enough_to_be_followed()
    {
        $this->seedProjects();

        Project::with(['milestones', 'modules', 'stack'])->get()->each(function (Project $project) {
            $label = "Project [{$project->slug}]";

            $this->assertNotNull($project->stack, "$label has no stack.");
            $this->assertGreaterThan(0, $project->hours, "$label has no estimate.");
            $this->assertGreaterThanOrEqual(5, $project->milestones->count(), "$label has too few milestones to guide anyone.");
            $this->assertNotEmpty($project->modules, "$label practises no module.");
            $this->assertNotEmpty($project->deliverables, "$label says nothing about what to hand in.");
            $this->assertNotEmpty($project->stretch, "$label has no stretch goal.");

            foreach ($project->milestones as $milestone) {
                $this->assertGreaterThanOrEqual(40, mb_strlen($milestone->detail), "$label milestone [{$milestone->title}] doesn't say what done looks like.");
            }

            // A project builds on a stack, so it should practise what that stack asks for.
            $stackModules = $project->stack->modules()->pluck('slug');
            $this->assertNotEmpty(
                $stackModules->intersect($project->modules->pluck('slug')),
                "$label shares no module with its stack.",
            );
        });
    }

    public function test_the_levels_cover_the_whole_range()
    {
        $this->seedProjects();

        $levels = Project::pluck('level')->map->value->unique()->sort()->values()->all();

        $this->assertSame(['avanzado', 'basico', 'intermedio'], $levels);
    }

    public function test_running_it_again_keeps_ids_and_the_ticks_made_on_them()
    {
        $this->seedProjects();
        $user = User::factory()->create();

        $milestone = ProjectMilestone::orderBy('id')->firstOrFail();
        $this->tick($user, $milestone);
        $ids = ProjectMilestone::orderBy('id')->pluck('id');
        $links = DB::table('module_project')->count();

        Project::query()->update(['summary' => 'stale']);
        ProjectMilestone::query()->update(['detail' => 'stale']);

        $this->seed(ProjectSeeder::class);

        $this->assertEquals($ids, ProjectMilestone::orderBy('id')->pluck('id'));
        $this->assertSame($links, DB::table('module_project')->count());
        $this->assertSame(0, Project::where('summary', 'stale')->count());
        $this->assertSame(0, ProjectMilestone::where('detail', 'stale')->count());
        $this->assertTrue(Completion::where('user_id', $user->id)->where('completable_id', $milestone->id)->exists());
    }

    public function test_projects_and_milestones_that_left_the_file_are_removed()
    {
        $this->seedProjects();

        $obsolete = Project::factory()->create();
        $obsolete->milestones()->create(['position' => 1, 'title' => 'Old', 'detail' => 'Old.']);
        $obsolete->modules()->attach(Module::firstOrFail());

        $extra = ProjectMilestone::create([
            'project_id' => Project::where('slug', $this->projectsFile()->first()['slug'])->firstOrFail()->id,
            'position' => 99,
            'title' => 'Removed from the file',
            'detail' => 'Removed.',
        ]);

        $this->seed(ProjectSeeder::class);

        $this->assertSame($this->projectsFile()->count(), Project::count());
        $this->assertDatabaseMissing('project_milestones', ['project_id' => $obsolete->id]);
        $this->assertDatabaseMissing('module_project', ['project_id' => $obsolete->id]);
        $this->assertModelMissing($extra);
    }

    public function test_a_project_pointing_at_a_missing_module_fails_loudly_and_leaves_nothing_behind()
    {
        $this->seed([RoadmapSeeder::class, StackSeeder::class]);
        Module::where('slug', 'que')->delete();

        try {
            $this->seed(ProjectSeeder::class);
            $this->fail('The seeder should have rejected the unknown module.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Unknown module [que]', $exception->getMessage());
        }

        $this->assertSame(0, Project::count(), 'A failed run should not leave half the projects behind.');
    }

    public function test_a_project_pointing_at_a_missing_stack_fails_loudly()
    {
        $this->seed([RoadmapSeeder::class, StackSeeder::class]);
        Stack::where('slug', 'rest-api')->delete();

        try {
            $this->seed(ProjectSeeder::class);
            $this->fail('The seeder should have rejected the unknown stack.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Unknown stack [rest-api]', $exception->getMessage());
        }

        $this->assertSame(0, Project::count());
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->seedProjects();

        $this->get(route('projects.index'))->assertRedirect(route('login'));
        $this->get(route('projects.show', 'api-de-tareas'))->assertRedirect(route('login'));
    }

    public function test_the_index_lists_every_project_in_the_order_of_the_file()
    {
        $this->seedProjects();

        $this->actingAs(User::factory()->create())->get(route('projects.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects')
                ->has('projects', $this->projectsFile()->count())
                ->where('projects', fn ($projects) => collect($projects)->pluck('slug')->all() === $this->projectsFile()->pluck('slug')->all())
                ->has('projects.0', fn (Assert $project) => $project
                    ->where('slug', 'api-de-tareas')
                    ->where('level', ['value' => 'basico', 'label' => 'Básico'])
                    ->where('stack', ['slug' => 'rest-api', 'name' => 'REST API para móvil y terceros'])
                    ->where('modules', count($this->projectsFile()->first()['modules']))
                    ->where('progress', ['done' => 0, 'total' => count($this->projectsFile()->first()['milestones']), 'pct' => 0])
                    ->hasAll(['title', 'hours', 'summary'])));
    }

    public function test_the_index_counts_only_the_milestones_the_user_has_done()
    {
        $this->seedProjects();
        $user = User::factory()->create();
        $other = User::factory()->create();

        $project = Project::where('slug', 'api-de-tareas')->with('milestones')->firstOrFail();
        $this->tick($user, $project->milestones[0]);
        $this->tick($user, $project->milestones[1]);
        $this->tick($other, $project->milestones[2]);

        $total = $project->milestones->count();

        $this->actingAs($user)->get(route('projects.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.progress', ['done' => 2, 'total' => $total, 'pct' => (int) round(200 / $total)])
                ->where('projects.1.progress.done', 0));
    }

    public function test_a_project_page_lists_milestones_in_order_with_the_users_ticks()
    {
        $this->seedProjects();
        $user = User::factory()->create();

        $project = Project::where('slug', 'api-de-tareas')->with('milestones')->firstOrFail();
        $this->tick($user, $project->milestones[1]);

        $this->actingAs($user)->get(route('projects.show', 'api-de-tareas'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProjectShow')
                ->where('project.slug', 'api-de-tareas')
                ->where('project.stack', ['slug' => 'rest-api', 'name' => 'REST API para móvil y terceros'])
                ->where('project.progress.done', 1)
                ->has('project.deliverables')
                ->has('project.stretch')
                ->has('milestones', $project->milestones->count())
                ->where('milestones', fn ($milestones) => collect($milestones)->pluck('title')->all() === $project->milestones->pluck('title')->all())
                ->where('milestones', fn ($milestones) => collect($milestones)->pluck('completed')->all() === $project->milestones->keys()->map(fn (int $position) => $position === 1)->all())
                ->has('milestones.0', fn (Assert $milestone) => $milestone->hasAll(['id', 'title', 'detail', 'completed'])));
    }

    public function test_a_project_page_shows_the_users_progress_in_each_module_it_practises()
    {
        $this->seedProjects();

        $expected = collect($this->projectsFile()->firstWhere('slug', 'api-de-tareas')['modules']);
        $position = $this->roadmap()->pluck('position', 'slug');

        $this->actingAs(User::factory()->create())->get(route('projects.show', 'api-de-tareas'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('modules', $expected->count())
                ->where('modules', fn ($modules) => collect($modules)->pluck('slug')->all() === $expected->sortBy(fn (string $slug) => $position[$slug])->values()->all())
                ->has('modules.0', fn (Assert $module) => $module
                    ->hasAll(['slug', 'title', 'command', 'progress'])
                    ->where('progress.pct', 0)));
    }

    public function test_an_unknown_project_is_not_found()
    {
        $this->seedProjects();

        $this->actingAs(User::factory()->create())->get(route('projects.show', 'nope'))->assertNotFound();
    }

    public function test_a_milestone_can_be_ticked_and_unticked_through_the_completions_route()
    {
        $this->seedProjects();
        $user = User::factory()->create();
        $milestone = ProjectMilestone::orderBy('id')->firstOrFail();

        $this->actingAs($user)
            ->put(route('completions.update', ['type' => 'milestone', 'id' => $milestone->id]), ['completed' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('completions', [
            'user_id' => $user->id,
            'completable_type' => 'milestone',
            'completable_id' => $milestone->id,
        ]);
        $this->assertSame(1, StudyDay::where('user_id', $user->id)->count(), 'Working on a project counts as studying.');

        $this->actingAs($user)
            ->put(route('completions.update', ['type' => 'milestone', 'id' => $milestone->id]), ['completed' => false])
            ->assertRedirect();

        $this->assertSame(0, Completion::where('user_id', $user->id)->count());
    }

    public function test_an_unknown_milestone_is_not_found()
    {
        $this->actingAs(User::factory()->create())
            ->put(route('completions.update', ['type' => 'milestone', 'id' => 999999]), ['completed' => true])
            ->assertNotFound();
    }

    public function test_milestones_do_not_count_towards_module_progress()
    {
        $this->seedProjects();
        $user = User::factory()->create();

        $before = app(ProgressReport::class)->modules($user)->map->toArray()->all();

        ProjectMilestone::all()->each(fn (ProjectMilestone $milestone) => $this->tick($user, $milestone));

        $this->assertSame($before, app(ProgressReport::class)->modules($user)->map->toArray()->all());
        $this->assertSame(0, app(ProgressReport::class)->stats($user)['progress']);
    }

    public function test_milestones_are_ticked_by_hand_and_the_alias_maps_back_to_the_model()
    {
        $this->assertContains('milestone', CompletableType::manual());
        $this->assertSame(ProjectMilestone::class, CompletableType::from('milestone')->model());
        $this->assertSame(ProjectMilestone::class, Relation::getMorphedModel('milestone'));
    }

    public function test_resetting_progress_clears_the_milestones_too()
    {
        $this->seedProjects();
        $user = User::factory()->create();
        $this->tick($user, ProjectMilestone::orderBy('id')->firstOrFail());

        $this->actingAs($user)->delete(route('progress.destroy'))->assertRedirect(route('dashboard'));

        $this->assertSame(0, Completion::where('user_id', $user->id)->count());
    }
}
