<?php

namespace App\Services\Stacks;

/**
 * Turns a few answers about a project into a recommended stack and the pieces to add on top.
 *
 * Every question has a closed list of answers, so the rules can be tested against every
 * possible combination. The stack slugs refer to database/data/stacks.json.
 */
class StackAdvisor
{
    private const array CLIENTS = ['web', 'mobile', 'both'];

    private const array TEAMS = ['solo', 'separate'];

    private const array INTERFACES = ['content', 'rich', 'admin'];

    /**
     * What each extra adds on top of the base stack, in the order they are listed. This map is
     * the single list of valid extras.
     */
    private const array ADDONS = [
        'realtime' => ['slug' => 'tiempo-real', 'why' => 'Necesitas que la pantalla se actualice sin recargar: Reverb y Echo lo resuelven con WebSockets.'],
        'subscriptions' => ['slug' => 'saas-multitenant', 'why' => 'Varios clientes con datos aislados y cobros recurrentes: Cashier para los pagos y un paquete de tenancy para el aislamiento.'],
        'search' => ['slug' => 'busqueda-scout', 'why' => 'Una búsqueda tolerante a errores y con filtros no se hace bien con LIKE: Scout la conecta a un motor de búsqueda.'],
        'background' => ['slug' => 'colas-y-workers', 'why' => 'Los emails, las importaciones y los reportes no deben hacer esperar al usuario: van a una cola.'],
        'traffic' => ['slug' => 'alto-rendimiento-octane', 'why' => 'Si de verdad necesitas más tráfico o menos latencia, Octane evita arrancar el framework en cada petición. Mide antes de activarlo.'],
        'events' => ['slug' => 'event-driven', 'why' => 'Cuando varios sistemas reaccionan a los mismos hechos conviene comunicarlos con eventos y colas compartidas. Si puedes, empieza con un monolito modular.'],
    ];

    /**
     * The questions, in the order they are asked.
     *
     * @return list<array{key: string, title: string, help: string|null, multiple: bool, options: list<array{value: string, label: string}>}>
     */
    public function questions(): array
    {
        return [
            [
                'key' => 'clients',
                'title' => '¿Quién va a usar tu aplicación?',
                'help' => null,
                'multiple' => false,
                'options' => [
                    ['value' => 'web', 'label' => 'Solo un sitio web propio'],
                    ['value' => 'mobile', 'label' => 'Una app móvil u otros programas (integraciones, terceros)'],
                    ['value' => 'both', 'label' => 'Un sitio web y también app móvil u otros programas'],
                ],
            ],
            [
                'key' => 'team',
                'title' => '¿Quién construye y mantiene el frontend?',
                'help' => 'El frontend es lo que se ve en el navegador.',
                'multiple' => false,
                'options' => [
                    ['value' => 'solo', 'label' => 'Yo, o el mismo equipo que hace el backend'],
                    ['value' => 'separate', 'label' => 'Un equipo distinto, con su propio ritmo de lanzamientos'],
                ],
            ],
            [
                'key' => 'ui',
                'title' => '¿Cómo es la interfaz?',
                'help' => null,
                'multiple' => false,
                'options' => [
                    ['value' => 'content', 'label' => 'Sobre todo contenido y formularios, con buen SEO'],
                    ['value' => 'rich', 'label' => 'Muy interactiva: paneles, filtros, estado compartido'],
                    ['value' => 'admin', 'label' => 'Es un panel interno de administración'],
                ],
            ],
            [
                'key' => 'extras',
                'title' => '¿Necesitas alguna de estas piezas?',
                'help' => 'Marca todas las que apliquen, o ninguna.',
                'multiple' => true,
                'options' => [
                    ['value' => 'realtime', 'label' => 'Cosas que se actualizan en vivo (chat, notificaciones, progreso)'],
                    ['value' => 'subscriptions', 'label' => 'Clientes con sus datos aislados y cobros recurrentes'],
                    ['value' => 'search', 'label' => 'Búsqueda con tolerancia a errores y filtros'],
                    ['value' => 'background', 'label' => 'Trabajo pesado en segundo plano (emails, importaciones, reportes)'],
                    ['value' => 'traffic', 'label' => 'Mucho tráfico o latencia muy baja como requisito'],
                    ['value' => 'events', 'label' => 'Varios sistemas que reaccionan a los mismos hechos'],
                ],
            ],
        ];
    }

    /**
     * Keep the valid answers and drop the rest, so a half-filled or tampered form can still
     * be shown again. An unanswered question stays null.
     *
     * @param  array<string, mixed>  $answers
     * @return array{clients: string|null, team: string|null, ui: string|null, extras: list<string>}
     */
    public function sanitize(array $answers): array
    {
        $pick = fn (string $key, array $allowed): ?string => in_array($answers[$key] ?? null, $allowed, true) ? $answers[$key] : null;

        $extras = is_array($answers['extras'] ?? null) ? $answers['extras'] : [];

        return [
            'clients' => $pick('clients', self::CLIENTS),
            'team' => $pick('team', self::TEAMS),
            'ui' => $pick('ui', self::INTERFACES),
            // Fixed order and no repeats, whatever the order they were ticked in.
            'extras' => array_values(array_filter(array_keys(self::ADDONS), fn (string $extra) => in_array($extra, $extras, true))),
        ];
    }

    /**
     * The answers once every required question is answered, otherwise null.
     *
     * @param  array<string, mixed>  $answers
     * @return array{clients: string, team: string, ui: string, extras: list<string>}|null
     */
    public function normalize(array $answers): ?array
    {
        $clean = $this->sanitize($answers);

        if ($clean['clients'] === null || $clean['team'] === null || $clean['ui'] === null) {
            return null;
        }

        return [
            'clients' => $clean['clients'],
            'team' => $clean['team'],
            'ui' => $clean['ui'],
            'extras' => $clean['extras'],
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array{base: list<array{slug: string, why: string}>, addons: list<array{slug: string, why: string}>}|null
     */
    public function recommend(array $answers): ?array
    {
        $answers = $this->normalize($answers);

        if ($answers === null) {
            return null;
        }

        $base = $this->base($answers['clients'], $answers['team'], $answers['ui']);

        $addons = [];

        foreach (self::ADDONS as $extra => $addon) {
            if (in_array($extra, $answers['extras'], true)) {
                $addons[] = $addon;
            }
        }

        // Several pieces need a queue worker underneath, even when it wasn't asked for.
        $needsQueue = array_intersect($answers['extras'], ['realtime', 'search', 'subscriptions']);

        if ($needsQueue !== [] && ! in_array('background', $answers['extras'], true)) {
            $addons[] = [
                'slug' => 'colas-y-workers',
                'why' => 'Lo necesitan las piezas que marcaste: los eventos en vivo, la indexación de búsqueda y los webhooks de pago se procesan en cola.',
            ];
        }

        return ['base' => $base, 'addons' => $addons];
    }

    /**
     * @return list<array{slug: string, why: string}>
     */
    private function base(string $clients, string $team, string $ui): array
    {
        $mobile = $clients !== 'web';
        $api = ['slug' => 'rest-api', 'why' => 'Una app móvil o un tercero no tiene sesión de navegador: necesita una API con tokens.'];

        if ($ui === 'admin') {
            $admin = ['slug' => 'backoffice-filament', 'why' => 'Es un panel interno de administración: Filament lo construye a partir de tus modelos, con tablas, formularios y permisos.'];

            return $mobile ? [$admin, $api] : [$admin];
        }

        if ($clients === 'mobile') {
            return [$api];
        }

        $web = $this->web($team, $ui, $mobile);

        return $mobile ? [$api, $web] : [$web];
    }

    /**
     * @return array{slug: string, why: string}
     */
    private function web(string $team, string $ui, bool $alongsideAnApi): array
    {
        if ($team === 'separate') {
            return [
                'slug' => 'frontend-desacoplado',
                'why' => $alongsideAnApi
                    ? 'Otro equipo hace el frontend: consumirá la misma API que usa el móvil, con sus propios lanzamientos.'
                    : 'Otro equipo hace el frontend con su propio ritmo: conviene separarlo y que consuma una API.',
            ];
        }

        if ($ui === 'rich') {
            return [
                'slug' => 'inertia-vue-react',
                'why' => $alongsideAnApi
                    ? 'Un solo equipo y una interfaz interactiva: sirve la web con Inertia dentro del mismo proyecto y deja la API para el móvil.'
                    : 'Un solo equipo y una interfaz interactiva: Inertia te da una SPA sin construir ni proteger una API aparte.',
            ];
        }

        return [
            'slug' => 'monolito-blade',
            'why' => $alongsideAnApi
                ? 'Un solo equipo y sobre todo contenido: Blade sirve la web con buen SEO dentro del mismo proyecto, y la API cubre al móvil.'
                : 'Un solo equipo y sobre todo contenido y formularios: Blade es el camino más corto y tiene buen SEO sin configurar SSR.',
        ];
    }
}
