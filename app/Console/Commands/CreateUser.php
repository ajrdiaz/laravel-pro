<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates an account from the terminal, for a deployment with public registration off.
 * The password is asked for rather than taken as an option, so it never lands in the
 * shell history or in the list of processes.
 */
#[Signature('user:create {name? : Name of the user} {email? : Email address, also the login}')]
#[Description('Create a user whose email is already verified')]
class CreateUser extends Command
{
    public function handle(): int
    {
        $name = $this->valueOf('name', 'Name');
        $email = $this->valueOf('email', 'Email address');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $password = password('Password', required: true, validate: fn (string $value) => $this->passwordError($value));

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        // There is no inbox to confirm from: whoever runs this command is trusted.
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->components->info("User {$email} created. They can sign in now.");

        return self::SUCCESS;
    }

    /**
     * An argument of the command, or what the user types when it was left out.
     */
    private function valueOf(string $argument, string $label): string
    {
        $value = $this->argument($argument);

        return is_string($value) && $value !== '' ? $value : text($label, required: true);
    }

    /**
     * The first problem with a password under the rules of the app, or null if it is fine.
     */
    private function passwordError(string $value): ?string
    {
        $validator = Validator::make(['password' => $value], ['password' => ['required', Password::default()]]);

        return $validator->fails() ? $validator->errors()->first('password') : null;
    }
}
