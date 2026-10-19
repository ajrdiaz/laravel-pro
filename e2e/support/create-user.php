<?php

/**
 * Creates a verified user in the e2e database and prints its credentials as JSON.
 * Every test gets its own user, so tests never see each other's notes or progress.
 *
 * Usage: php e2e/support/create-user.php "Name"
 */

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::factory()->create(['name' => $argv[1] ?? 'E2E User']);

// The factory gives every user the password "password".
echo json_encode(['email' => $user->email, 'password' => 'password']);
