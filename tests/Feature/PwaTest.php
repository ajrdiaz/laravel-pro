<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PwaTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        return json_decode(file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Width and height from the header of a PNG file.
     *
     * @return array{int, int}
     */
    private function pngSize(string $path): array
    {
        $header = file_get_contents($path, length: 24);

        $this->assertSame("\x89PNG\r\n\x1a\n", substr($header, 0, 8), "{$path} is not a PNG file.");

        return [unpack('N', substr($header, 16, 4))[1], unpack('N', substr($header, 20, 4))[1]];
    }

    /**
     * The paths the app answers on, such as `/flashcards`.
     *
     * @return list<string>
     */
    private function appPaths(): array
    {
        $paths = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (in_array('GET', $route->methods(), true)) {
                $paths[] = '/'.ltrim($route->uri(), '/');
            }
        }

        return $paths;
    }

    public function test_the_manifest_describes_an_app_that_can_be_installed()
    {
        $manifest = $this->manifest();

        $this->assertSame('Ruta Laravel', $manifest['name']);
        $this->assertNotSame('', $manifest['short_name']);
        $this->assertLessThanOrEqual(12, mb_strlen($manifest['short_name']), 'A short name has to fit under an icon.');
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('es', $manifest['lang']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $manifest['theme_color']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $manifest['background_color']);
    }

    public function test_it_starts_on_a_page_of_the_app_inside_its_scope()
    {
        $manifest = $this->manifest();

        $this->assertContains($manifest['start_url'], $this->appPaths());
        $this->assertStringStartsWith($manifest['scope'], $manifest['start_url']);
    }

    public function test_it_has_the_icons_browsers_require_and_each_has_the_size_it_declares()
    {
        $icons = collect($this->manifest()['icons']);

        $this->assertTrue($icons->contains(fn (array $icon) => $icon['sizes'] === '192x192' && $icon['purpose'] === 'any'));
        $this->assertTrue($icons->contains(fn (array $icon) => $icon['sizes'] === '512x512' && $icon['purpose'] === 'any'));
        $this->assertTrue($icons->contains(fn (array $icon) => $icon['purpose'] === 'maskable'), 'A maskable icon avoids a white frame on Android.');

        foreach ($icons as $icon) {
            $this->assertSame('image/png', $icon['type']);
            $this->assertFileExists(public_path($icon['src']));
            $this->assertSame(
                array_map('intval', explode('x', $icon['sizes'])),
                $this->pngSize(public_path($icon['src'])),
                "{$icon['src']} is not the size the manifest says.",
            );
        }
    }

    public function test_the_shortcuts_lead_to_real_pages_and_use_icons_that_exist()
    {
        $paths = $this->appPaths();
        $shortcuts = $this->manifest()['shortcuts'];

        $this->assertNotEmpty($shortcuts);

        foreach ($shortcuts as $shortcut) {
            $this->assertContains($shortcut['url'], $paths, "The shortcut {$shortcut['name']} points nowhere.");

            foreach ($shortcut['icons'] as $icon) {
                $this->assertFileExists(public_path($icon['src']));
            }
        }
    }

    public function test_iphones_get_a_home_screen_icon_of_the_size_they_expect()
    {
        $this->assertSame([180, 180], $this->pngSize(public_path('apple-touch-icon.png')));
    }

    public function test_the_offline_page_is_a_standalone_document_that_points_back_into_the_app()
    {
        $html = file_get_contents(public_path('offline.html'));

        $this->assertStringContainsString('<html lang="es">', $html);
        $this->assertStringContainsString('Sin conexión', $html);
        $this->assertStringContainsString('href="/learn"', $html);

        // It has to work with nothing else cached: no scripts or files of its own.
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<link', $html);
    }

    public function test_the_service_worker_only_precaches_files_that_exist()
    {
        $worker = file_get_contents(public_path('sw.js'));

        $this->assertSame(1, preg_match('/addAll\(\[(.*?)\]\)/s', $worker, $list), 'The worker should precache a list of files.');

        preg_match_all("/'(\\/[^']+)'|OFFLINE_URL/", $list[1], $matches);

        $this->assertStringContainsString("const OFFLINE_URL = '/offline.html';", $worker);
        $this->assertFileExists(public_path('offline.html'));

        foreach (array_filter($matches[1]) as $path) {
            $this->assertFileExists(public_path($path), "The service worker precaches {$path}, which is missing.");
        }
    }

    public function test_the_service_worker_keeps_lessons_and_build_files_and_nothing_else()
    {
        $worker = file_get_contents(public_path('sw.js'));

        // The paths it saves responses for: anything else could show another user's or old data.
        preg_match_all("/url\\.pathname(?: ===|\\.startsWith\\() ?'([^']+)'/", $worker, $matches);

        $this->assertEqualsCanonicalizing(['/build/assets/', '/learn', '/learn/'], $matches[1]);
        $this->assertStringContainsString("request.method !== 'GET'", $worker, 'It must never touch writes.');
        $this->assertStringContainsString('response.redirected', $worker, 'A redirect to the login page must not be saved as a lesson.');
    }

    public function test_the_pages_link_the_manifest_and_set_the_theme_color()
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.json">', false)
            ->assertSee('<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">', false)
            ->assertSee('<meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">', false)
            ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false);
    }
}
