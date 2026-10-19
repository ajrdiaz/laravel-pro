<?php

namespace Tests\Feature\Study;

use App\Services\Stacks\StackAdvisor;
use Tests\Concerns\ReadsContentFiles;
use Tests\TestCase;

class StackAdvisorTest extends TestCase
{
    use ReadsContentFiles;

    private const array EXTRAS = ['realtime', 'subscriptions', 'search', 'background', 'traffic', 'events'];

    private function advisor(): StackAdvisor
    {
        return new StackAdvisor;
    }

    /**
     * @param  list<string>  $extras
     * @return list<string>
     */
    private function bases(string $clients, string $team, string $ui, array $extras = []): array
    {
        $result = $this->advisor()->recommend(compact('clients', 'team', 'ui', 'extras'));

        return array_column($result['base'], 'slug');
    }

    /**
     * @param  list<string>  $extras
     * @return list<string>
     */
    private function addons(array $extras, string $clients = 'web', string $team = 'solo', string $ui = 'rich'): array
    {
        $result = $this->advisor()->recommend(compact('clients', 'team', 'ui', 'extras'));

        return array_column($result['addons'], 'slug');
    }

    /**
     * Every possible set of ticked extras, from none to all of them.
     *
     * @return list<list<string>>
     */
    private function everyExtrasCombination(): array
    {
        $combinations = [];

        for ($mask = 0; $mask < 2 ** count(self::EXTRAS); $mask++) {
            $combinations[] = array_values(array_filter(
                self::EXTRAS,
                fn (string $extra, int $bit) => ($mask >> $bit) & 1,
                ARRAY_FILTER_USE_BOTH,
            ));
        }

        return $combinations;
    }

    public function test_every_combination_of_answers_gets_a_recommendation_that_points_at_real_stacks()
    {
        $known = $this->stacksFile()->pluck('slug')->all();
        $checked = 0;

        foreach (['web', 'mobile', 'both'] as $clients) {
            foreach (['solo', 'separate'] as $team) {
                foreach (['content', 'rich', 'admin'] as $ui) {
                    foreach ($this->everyExtrasCombination() as $extras) {
                        $label = "clients=$clients team=$team ui=$ui extras=".implode(',', $extras);
                        $result = $this->advisor()->recommend(compact('clients', 'team', 'ui', 'extras'));

                        $this->assertNotNull($result, $label);
                        $this->assertNotEmpty($result['base'], "$label has no base stack.");

                        $slugs = array_column([...$result['base'], ...$result['addons']], 'slug');

                        $this->assertSame([], array_diff($slugs, $known), "$label recommends a stack that doesn't exist.");
                        $this->assertSame($slugs, array_values(array_unique($slugs)), "$label recommends the same stack twice.");

                        foreach ([...$result['base'], ...$result['addons']] as $item) {
                            $this->assertNotSame('', trim($item['why']), "$label gives no reason for {$item['slug']}.");
                        }

                        $checked++;
                    }
                }
            }
        }

        $this->assertSame(3 * 2 * 3 * 64, $checked);
    }

    public function test_a_stack_in_the_extras_is_added_on_top_and_never_replaces_the_base()
    {
        foreach ($this->everyExtrasCombination() as $extras) {
            $base = $this->bases('web', 'solo', 'rich', $extras);

            $this->assertSame(['inertia-vue-react'], $base, 'Ticking extras changed the base for '.implode(',', $extras));
        }
    }

    public function test_the_web_stack_follows_the_team_and_the_kind_of_interface()
    {
        $this->assertSame(['monolito-blade'], $this->bases('web', 'solo', 'content'));
        $this->assertSame(['inertia-vue-react'], $this->bases('web', 'solo', 'rich'));
        $this->assertSame(['frontend-desacoplado'], $this->bases('web', 'separate', 'content'));
        $this->assertSame(['frontend-desacoplado'], $this->bases('web', 'separate', 'rich'));
    }

    public function test_a_separate_frontend_team_wins_over_the_kind_of_interface()
    {
        foreach (['content', 'rich'] as $ui) {
            $this->assertSame(['frontend-desacoplado'], $this->bases('web', 'separate', $ui));
        }
    }

    public function test_a_mobile_client_needs_an_api()
    {
        foreach (['solo', 'separate'] as $team) {
            foreach (['content', 'rich'] as $ui) {
                $this->assertSame(['rest-api'], $this->bases('mobile', $team, $ui));
            }
        }
    }

    public function test_a_site_that_also_serves_mobile_gets_the_api_first_and_the_web_stack_after_it()
    {
        $this->assertSame(['rest-api', 'monolito-blade'], $this->bases('both', 'solo', 'content'));
        $this->assertSame(['rest-api', 'inertia-vue-react'], $this->bases('both', 'solo', 'rich'));
        $this->assertSame(['rest-api', 'frontend-desacoplado'], $this->bases('both', 'separate', 'rich'));
    }

    public function test_an_admin_panel_is_filament_and_only_adds_the_api_when_something_else_consumes_it()
    {
        $this->assertSame(['backoffice-filament'], $this->bases('web', 'solo', 'admin'));
        $this->assertSame(['backoffice-filament'], $this->bases('web', 'separate', 'admin'));
        $this->assertSame(['backoffice-filament', 'rest-api'], $this->bases('mobile', 'solo', 'admin'));
        $this->assertSame(['backoffice-filament', 'rest-api'], $this->bases('both', 'separate', 'admin'));
    }

    public function test_each_extra_maps_to_its_own_stack_in_a_fixed_order()
    {
        $this->assertSame(['tiempo-real', 'colas-y-workers'], $this->addons(['realtime']));
        $this->assertSame(['saas-multitenant', 'colas-y-workers'], $this->addons(['subscriptions']));
        $this->assertSame(['busqueda-scout', 'colas-y-workers'], $this->addons(['search']));
        $this->assertSame(['colas-y-workers'], $this->addons(['background']));
        $this->assertSame(['alto-rendimiento-octane'], $this->addons(['traffic']));
        $this->assertSame(['event-driven'], $this->addons(['events']));
        $this->assertSame([], $this->addons([]));
    }

    public function test_a_queue_is_added_once_when_a_piece_needs_one_and_it_was_not_asked_for()
    {
        $this->assertSame(
            ['tiempo-real', 'saas-multitenant', 'busqueda-scout', 'colas-y-workers'],
            $this->addons(['realtime', 'subscriptions', 'search']),
        );

        // Asking for it explicitly keeps it where the fixed order puts it, without a second copy.
        $this->assertSame(
            ['tiempo-real', 'colas-y-workers'],
            $this->addons(['realtime', 'background']),
        );

        // The ones that don't depend on a queue don't pull it in.
        $this->assertSame(['alto-rendimiento-octane', 'event-driven'], $this->addons(['traffic', 'events']));
    }

    public function test_the_order_the_extras_were_ticked_in_does_not_change_the_result()
    {
        $this->assertSame(
            $this->addons(['realtime', 'search', 'traffic']),
            $this->addons(['traffic', 'search', 'realtime']),
        );
    }

    public function test_it_declines_to_recommend_until_every_required_question_is_answered()
    {
        $advisor = $this->advisor();

        $this->assertNull($advisor->recommend([]));
        $this->assertNull($advisor->recommend(['clients' => 'web', 'team' => 'solo']));
        $this->assertNull($advisor->recommend(['clients' => 'web', 'ui' => 'rich']));
        $this->assertNull($advisor->recommend(['team' => 'solo', 'ui' => 'rich', 'extras' => ['realtime']]));
        $this->assertNotNull($advisor->recommend(['clients' => 'web', 'team' => 'solo', 'ui' => 'rich']));
    }

    public function test_unknown_answers_count_as_unanswered()
    {
        $advisor = $this->advisor();

        $this->assertNull($advisor->recommend(['clients' => 'satellite', 'team' => 'solo', 'ui' => 'rich']));
        $this->assertNull($advisor->recommend(['clients' => ['web'], 'team' => 'solo', 'ui' => 'rich']));
        $this->assertNull($advisor->recommend(['clients' => 'web', 'team' => 'solo', 'ui' => null]));
    }

    public function test_sanitize_keeps_what_is_valid_and_drops_the_rest()
    {
        $this->assertSame(
            ['clients' => 'web', 'team' => null, 'ui' => null, 'extras' => ['realtime', 'traffic']],
            $this->advisor()->sanitize([
                'clients' => 'web',
                'team' => 'nobody',
                'ui' => 42,
                // Out of order, repeated, and with things that aren't extras at all.
                'extras' => ['traffic', 'bitcoin', 'realtime', 'traffic', ['realtime'], 7],
            ]),
        );

        $this->assertSame(
            ['clients' => null, 'team' => null, 'ui' => null, 'extras' => []],
            $this->advisor()->sanitize(['extras' => 'realtime']),
        );
    }

    public function test_the_questions_offer_exactly_the_answers_the_rules_understand()
    {
        $questions = collect($this->advisor()->questions())->keyBy('key');

        $this->assertSame(['clients', 'team', 'ui', 'extras'], $questions->keys()->all());
        $this->assertSame(['web', 'mobile', 'both'], array_column($questions['clients']['options'], 'value'));
        $this->assertSame(['solo', 'separate'], array_column($questions['team']['options'], 'value'));
        $this->assertSame(['content', 'rich', 'admin'], array_column($questions['ui']['options'], 'value'));
        $this->assertSame(self::EXTRAS, array_column($questions['extras']['options'], 'value'));
        $this->assertTrue($questions['extras']['multiple']);
        $this->assertFalse($questions['clients']['multiple']);

        // Each offered answer has to be accepted as given.
        foreach ($questions->except('extras') as $question) {
            foreach ($question['options'] as $option) {
                $this->assertSame($option['value'], $this->advisor()->sanitize([$question['key'] => $option['value']])[$question['key']]);
            }
        }
    }
}
