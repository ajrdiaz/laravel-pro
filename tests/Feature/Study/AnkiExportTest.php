<?php

namespace Tests\Feature\Study;

use App\Models\Flashcard;
use App\Models\Module;
use App\Models\User;
use App\Services\Anki\AnkiExport;
use Database\Seeders\RoadmapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ReadsContentFiles;
use Tests\TestCase;

class AnkiExportTest extends TestCase
{
    use ReadsContentFiles;
    use RefreshDatabase;

    private const int HEADER_LINES = 4;

    /**
     * The rows of an export, without the headers, each split into its fields.
     *
     * @return list<list<string>>
     */
    private function rows(string $content): array
    {
        $lines = explode("\n", rtrim($content, "\n"));

        return array_map(fn (string $line) => explode("\t", $line), array_slice($lines, self::HEADER_LINES));
    }

    private function card(string $question, string $answer, string $module = 'php', int $position = 1): Flashcard
    {
        $card = Flashcard::factory()->make(['question' => $question, 'answer' => $answer, 'position' => $position]);
        $card->setRelation('module', Module::factory()->make(['slug' => $module]));

        return $card;
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get(route('flashcards.export'))->assertRedirect(route('login'));
    }

    public function test_the_file_starts_with_the_headers_anki_reads()
    {
        $this->seed(RoadmapSeeder::class);

        $response = $this->actingAs(User::factory()->create())->get(route('flashcards.export'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertDownload('ruta-laravel-tarjetas.txt');

        $this->assertSame(
            ['#separator:tab', '#html:true', '#guid column:1', '#tags column:4'],
            array_slice(explode("\n", $response->getContent()), 0, self::HEADER_LINES),
        );
    }

    public function test_it_exports_every_card_as_one_line_of_four_fields()
    {
        $this->seed(RoadmapSeeder::class);

        $content = $this->actingAs(User::factory()->create())->get(route('flashcards.export'))->getContent();

        $this->assertStringEndsWith("\n", $content);
        $this->assertStringNotContainsString("\r", $content);

        $rows = $this->rows($content);

        $this->assertCount($this->roadmapTotal('flashcards'), $rows);

        foreach ($rows as $row) {
            $this->assertCount(4, $row, 'A row should be guid, front, back and tag: '.implode(' | ', $row));
            $this->assertStringNotContainsString('"', implode('', $row), 'A quote would make Anki read the field as quoted.');
            $this->assertNotSame('', trim($row[1]));
            $this->assertNotSame('', trim($row[2]));
        }
    }

    public function test_every_card_has_its_own_guid_and_it_survives_a_second_export()
    {
        $this->seed(RoadmapSeeder::class);
        $user = User::factory()->create();

        $first = $this->rows($this->actingAs($user)->get(route('flashcards.export'))->getContent());
        $second = $this->rows($this->actingAs($user)->get(route('flashcards.export'))->getContent());

        $guids = array_column($first, 0);

        $this->assertCount(count($guids), array_unique($guids));
        $this->assertSame($guids, array_column($second, 0), 'Importing again has to match the notes already in Anki.');
    }

    public function test_the_guid_comes_from_the_module_and_position_so_it_survives_a_fresh_database()
    {
        $this->seed(RoadmapSeeder::class);
        $user = User::factory()->create();

        $before = array_column($this->rows($this->actingAs($user)->get(route('flashcards.export'))->getContent()), 0);

        $this->assertSame('ruta-laravel-php-1', $before[0]);

        // Rebuilding the cards gives them new ids; the notes in Anki must still be recognised.
        Flashcard::query()->delete();
        $this->seed(RoadmapSeeder::class);

        $after = array_column($this->rows($this->actingAs($user)->get(route('flashcards.export'))->getContent()), 0);

        $this->assertSame($before, $after);
    }

    public function test_the_cards_come_in_roadmap_order_and_are_tagged_with_their_module()
    {
        $this->seed(RoadmapSeeder::class);

        $rows = $this->rows($this->actingAs(User::factory()->create())->get(route('flashcards.export'))->getContent());

        $expected = $this->roadmap()->flatMap(fn (array $module) => array_map(
            fn (array $card) => [$card['question'], 'ruta-laravel::'.$module['slug']],
            $module['flashcards'],
        ))->values();

        // The questions go through HTML escaping and <code>, so compare them in their plain form.
        $plain = fn (string $html) => html_entity_decode(strip_tags(str_replace(['<code>', '</code>'], '`', $html)), ENT_QUOTES);

        $this->assertSame(
            $expected->map(fn (array $item) => $item[1])->all(),
            array_column($rows, 3),
        );
        $this->assertSame(
            $expected->map(fn (array $item) => $item[0])->all(),
            array_map(fn (array $row) => $plain($row[1]), $rows),
        );
    }

    public function test_a_module_can_be_exported_on_its_own()
    {
        $this->seed(RoadmapSeeder::class);

        $php = $this->roadmap()->firstWhere('slug', 'php');

        $response = $this->actingAs(User::factory()->create())->get(route('flashcards.export', ['module' => 'php']));

        $response->assertDownload('ruta-laravel-php.txt');

        $rows = $this->rows($response->getContent());

        $this->assertCount(count($php['flashcards']), $rows);
        $this->assertSame(['ruta-laravel::php'], array_values(array_unique(array_column($rows, 3))));
    }

    public function test_an_unknown_module_is_not_found_instead_of_exporting_everything()
    {
        $this->seed(RoadmapSeeder::class);

        $this->actingAs(User::factory()->create())
            ->get(route('flashcards.export', ['module' => 'nope']))
            ->assertNotFound();
    }

    public function test_the_export_does_not_depend_on_who_asks_for_it()
    {
        $this->seed(RoadmapSeeder::class);

        $a = $this->actingAs(User::factory()->create())->get(route('flashcards.export'))->getContent();
        $b = $this->actingAs(User::factory()->create())->get(route('flashcards.export'))->getContent();

        $this->assertSame($a, $b);
    }

    public function test_text_is_escaped_and_code_between_backticks_becomes_code()
    {
        $content = (new AnkiExport)->build([
            $this->card('¿Qué hace `<script>` & `&&`?', 'Usa "comillas" y <b>negritas</b>.', 'php', 7),
        ]);

        $this->assertSame(
            [
                'ruta-laravel-php-7',
                '¿Qué hace <code>&lt;script&gt;</code> &amp; <code>&amp;&amp;</code>?',
                'Usa &quot;comillas&quot; y &lt;b&gt;negritas&lt;/b&gt;.',
                'ruta-laravel::php',
            ],
            $this->rows($content)[0],
        );
    }

    public function test_line_breaks_and_tabs_never_break_the_row()
    {
        $content = (new AnkiExport)->build([
            $this->card("Primera\nsegunda\r\ntercera", "con\ttabulación\ry otra", 'php', 1),
        ]);

        $rows = $this->rows($content);

        $this->assertCount(1, $rows);
        $this->assertCount(4, $rows[0]);
        $this->assertSame('Primera<br>segunda<br>tercera', $rows[0][1]);
        $this->assertSame('con tabulación<br>y otra', $rows[0][2]);
    }

    public function test_an_empty_deck_is_just_the_headers()
    {
        $content = (new AnkiExport)->build([]);

        $this->assertSame("#separator:tab\n#html:true\n#guid column:1\n#tags column:4\n", $content);
    }
}
