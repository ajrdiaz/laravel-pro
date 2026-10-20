<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Copies the SQLite database to a dated file and keeps only the newest copies.
 *
 * It uses `VACUUM INTO`, which writes a consistent snapshot even while the app is
 * answering requests, unlike copying the file, which can catch it half written.
 */
#[Signature('database:backup {--path= : Folder for the copies (default: a "backups" folder next to the database)} {--keep=14 : How many copies to keep}')]
#[Description('Copy the SQLite database to a dated file and delete the oldest copies')]
class BackupDatabase extends Command
{
    /** The name every copy starts with, which is also how old ones are told apart from other files. */
    private const string PREFIX = 'ruta-laravel-';

    public function handle(): int
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            $this->components->error('This command only knows how to copy a SQLite database.');

            return self::FAILURE;
        }

        $keep = (int) $this->option('keep');

        if ($keep < 1) {
            $this->components->error('--keep has to be at least 1, or the copy just made would be deleted.');

            return self::FAILURE;
        }

        $directory = $this->directory((string) $connection->getConfig('database'));

        if ($directory === null) {
            $this->components->error('An in-memory database has no folder: say where to write with --path.');

            return self::FAILURE;
        }

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            $this->components->error("The folder {$directory} could not be created.");

            return self::FAILURE;
        }

        $file = $directory.DIRECTORY_SEPARATOR.self::PREFIX.now()->format('Ymd-His').'.sqlite';

        if (file_exists($file)) {
            $this->components->error("{$file} already exists: wait a second and try again.");

            return self::FAILURE;
        }

        // SQLite refuses to write over a file that exists, so there is no way to clobber one.
        $connection->statement('VACUUM INTO ?', [$file]);

        if (! $this->isHealthy($file)) {
            @unlink($file);
            $this->components->error('The copy did not pass SQLite\'s integrity check, so it was deleted.');

            return self::FAILURE;
        }

        $removed = $this->prune($directory, $keep);

        $size = number_format(filesize($file) / 1024, 1);
        $this->components->info("Copy saved to {$file} ({$size} KiB).");

        if ($removed > 0) {
            $this->components->info("Deleted {$removed} older ".($removed === 1 ? 'copy' : 'copies').", keeping the newest {$keep}.");
        }

        return self::SUCCESS;
    }

    /**
     * Where the copies go: the folder asked for, else a "backups" folder beside the
     * database file, which in the Docker setup is on the same volume as the database.
     */
    private function directory(string $database): ?string
    {
        $path = $this->option('path');

        if (is_string($path) && $path !== '') {
            return rtrim($path, '/\\');
        }

        if ($database === '' || $database === ':memory:') {
            return null;
        }

        return dirname($database).DIRECTORY_SEPARATOR.'backups';
    }

    private function isHealthy(string $file): bool
    {
        try {
            $result = (new PDO('sqlite:'.$file))->query('PRAGMA integrity_check');

            return $result !== false && $result->fetchColumn() === 'ok';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Deletes the oldest copies, so that `$keep` are left. The date in the name sorts them.
     */
    private function prune(string $directory, int $keep): int
    {
        $copies = glob($directory.DIRECTORY_SEPARATOR.self::PREFIX.'*.sqlite') ?: [];

        rsort($copies);

        $removed = 0;

        foreach (array_slice($copies, $keep) as $old) {
            if (@unlink($old)) {
                $removed++;
            }
        }

        return $removed;
    }
}
