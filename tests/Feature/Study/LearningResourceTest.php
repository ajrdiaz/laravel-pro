<?php

namespace Tests\Feature\Study;

use App\Enums\ResourceKind;
use App\Enums\ResourceLanguage;
use App\Models\LearningResource;
use App\Models\Module;
use Database\Seeders\LearningResourceSeeder;
use Database\Seeders\RoadmapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ReadsContentFiles;
use Tests\TestCase;

class LearningResourceTest extends TestCase
{
    use ReadsContentFiles;
    use RefreshDatabase;

    private function seedResources(): void
    {
        $this->seed([RoadmapSeeder::class, LearningResourceSeeder::class]);
    }

    private function resourcesTotal(): int
    {
        return $this->resourcesFile()->sum(fn (array $group) => count($group['items']));
    }

    public function test_every_module_gets_a_handful_of_valid_resources()
    {
        $this->seedResources();

        $this->assertSame($this->resourcesTotal(), LearningResource::count());

        Module::with('learningResources')->get()->each(function (Module $module) {
            $this->assertGreaterThanOrEqual(3, $module->learningResources->count(), "Module [{$module->slug}] has too few resources.");

            $urls = $module->learningResources->pluck('url');
            $this->assertSame($urls->count(), $urls->unique()->count(), "Module [{$module->slug}] lists the same URL twice.");

            foreach ($module->learningResources as $resource) {
                $this->assertStringStartsWith('https://', $resource->url, $resource->title);
                $this->assertNotSame('', trim($resource->note), $resource->title);
            }
        });
    }

    public function test_there_is_content_in_spanish_and_every_kind_is_used()
    {
        $this->seedResources();

        $this->assertGreaterThan(0, LearningResource::where('language', ResourceLanguage::Es)->count());

        foreach (ResourceKind::cases() as $kind) {
            $this->assertGreaterThan(0, LearningResource::where('kind', $kind)->count(), "No resource of kind [{$kind->value}].");
        }
    }

    public function test_every_seeded_video_can_be_embedded()
    {
        $this->seedResources();

        $videos = LearningResource::where('kind', ResourceKind::Video)->get();

        $this->assertNotEmpty($videos);
        $videos->each(fn (LearningResource $video) => $this->assertNotNull($video->embedUrl(), "Video [{$video->title}] cannot be embedded."));
    }

    public function test_running_it_again_updates_in_place_without_duplicating_or_changing_ids()
    {
        $this->seedResources();

        $ids = LearningResource::orderBy('id')->pluck('id');
        LearningResource::query()->update(['note' => 'stale']);

        $this->seed(LearningResourceSeeder::class);

        $this->assertSame($this->resourcesTotal(), LearningResource::count());
        $this->assertEquals($ids, LearningResource::orderBy('id')->pluck('id'));
        $this->assertSame(0, LearningResource::where('note', 'stale')->count());
    }

    public function test_resources_for_modules_left_out_of_the_file_are_removed()
    {
        $this->seedResources();

        $extra = Module::factory()->create();
        LearningResource::factory()->create(['module_id' => $extra->id]);

        $this->seed(LearningResourceSeeder::class);

        $this->assertSame(0, LearningResource::where('module_id', $extra->id)->count());
    }

    public function test_a_resource_pointing_at_a_missing_module_fails_loudly()
    {
        $this->seed(RoadmapSeeder::class);
        Module::where('slug', 'ine')->delete();

        try {
            $this->seed(LearningResourceSeeder::class);
            $this->fail('The seeder should have rejected the unknown module.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Unknown module [ine]', $exception->getMessage());
        }

        $this->assertSame(0, LearningResource::count(), 'A failed run should not leave half the resources behind.');
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function youtubeUrls(): array
    {
        return [
            'watch link' => ['https://www.youtube.com/watch?v=gv9z1yv0_7k', 'https://www.youtube-nocookie.com/embed/gv9z1yv0_7k'],
            'watch link without www' => ['https://youtube.com/watch?v=gv9z1yv0_7k', 'https://www.youtube-nocookie.com/embed/gv9z1yv0_7k'],
            'mobile link' => ['https://m.youtube.com/watch?v=gv9z1yv0_7k', 'https://www.youtube-nocookie.com/embed/gv9z1yv0_7k'],
            'short link' => ['https://youtu.be/gv9z1yv0_7k', 'https://www.youtube-nocookie.com/embed/gv9z1yv0_7k'],
            'extra parameters' => ['https://www.youtube.com/watch?ntb=1&v=gv9z1yv0_7k&t=30s', 'https://www.youtube-nocookie.com/embed/gv9z1yv0_7k'],
            'id of the wrong length' => ['https://www.youtube.com/watch?v=abc', null],
            'no id at all' => ['https://www.youtube.com/watch', null],
            'id with unsafe characters' => ['https://www.youtube.com/watch?v=abc"onload=alert(1)', null],
            'another site that looks like YouTube' => ['https://youtube.com.evil.test/watch?v=gv9z1yv0_7k', null],
            'another video site' => ['https://vimeo.com/123456789', null],
        ];
    }

    #[DataProvider('youtubeUrls')]
    public function test_it_builds_a_safe_embed_url_only_for_real_youtube_videos(string $url, ?string $expected)
    {
        $this->assertSame($expected, LearningResource::factory()->video($url)->make()->embedUrl());
    }

    public function test_only_videos_have_an_embed_url()
    {
        $resource = LearningResource::factory()->make(['url' => 'https://www.youtube.com/watch?v=gv9z1yv0_7k']);

        $this->assertNull($resource->embedUrl());
    }
}
