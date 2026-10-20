<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    // Not RefreshDatabase: that wraps each test in a transaction, and SQLite cannot VACUUM inside one.
    use DatabaseMigrations;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ruta-backups-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    /**
     * @return list<string>
     */
    private function copies(): array
    {
        return array_map('basename', glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: []);
    }

    public function test_it_saves_a_copy_that_opens_as_a_database_with_the_same_data()
    {
        User::factory()->count(3)->create();

        $this->artisan('database:backup', ['--path' => $this->directory])->assertSuccessful();

        $copies = $this->copies();

        $this->assertCount(1, $copies);
        $this->assertMatchesRegularExpression('/^ruta-laravel-\d{8}-\d{6}\.sqlite$/', $copies[0]);

        $copy = new PDO('sqlite:'.$this->directory.DIRECTORY_SEPARATOR.$copies[0]);

        $this->assertSame(3, (int) $copy->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $this->assertSame('ok', $copy->query('PRAGMA integrity_check')->fetchColumn());
    }

    public function test_it_creates_the_folder_when_it_does_not_exist()
    {
        $this->assertDirectoryDoesNotExist($this->directory);

        $this->artisan('database:backup', ['--path' => $this->directory])->assertSuccessful();

        $this->assertDirectoryExists($this->directory);
    }

    public function test_it_keeps_only_the_newest_copies()
    {
        File::ensureDirectoryExists($this->directory);

        foreach (['20260101-100000', '20260102-100000', '20260103-100000', '20260104-100000'] as $stamp) {
            touch($this->directory."/ruta-laravel-{$stamp}.sqlite");
        }

        $this->artisan('database:backup', ['--path' => $this->directory, '--keep' => 3])
            ->expectsOutputToContain('Deleted 2 older copies, keeping the newest 3')
            ->assertSuccessful();

        $copies = $this->copies();

        $this->assertCount(3, $copies);
        // The two oldest went, and the one just made (today) is among the newest.
        $this->assertNotContains('ruta-laravel-20260101-100000.sqlite', $copies);
        $this->assertNotContains('ruta-laravel-20260102-100000.sqlite', $copies);
        $this->assertContains('ruta-laravel-20260104-100000.sqlite', $copies);
    }

    public function test_it_never_deletes_files_that_are_not_its_own_copies()
    {
        File::ensureDirectoryExists($this->directory);
        touch($this->directory.'/notes.txt');
        touch($this->directory.'/other-app-20200101-100000.sqlite');

        $this->artisan('database:backup', ['--path' => $this->directory, '--keep' => 1])->assertSuccessful();

        $this->assertContains('notes.txt', $this->copies());
        $this->assertContains('other-app-20200101-100000.sqlite', $this->copies());
    }

    public function test_it_refuses_to_keep_fewer_than_one_copy()
    {
        $this->artisan('database:backup', ['--path' => $this->directory, '--keep' => 0])
            ->expectsOutputToContain('--keep has to be at least 1')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->directory);
    }

    public function test_an_in_memory_database_needs_a_folder_to_be_named()
    {
        $this->artisan('database:backup')
            ->expectsOutputToContain('An in-memory database has no folder')
            ->assertFailed();
    }

    public function test_two_copies_in_the_same_second_do_not_overwrite_each_other()
    {
        $this->travelTo(now()->startOfSecond());

        $this->artisan('database:backup', ['--path' => $this->directory])->assertSuccessful();
        $first = $this->copies();

        $this->artisan('database:backup', ['--path' => $this->directory])
            ->expectsOutputToContain('already exists')
            ->assertFailed();

        $this->assertSame($first, $this->copies());
    }
}
