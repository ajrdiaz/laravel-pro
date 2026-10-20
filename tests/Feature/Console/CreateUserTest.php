<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_user_who_can_sign_in_right_away()
    {
        $this->artisan('user:create', ['name' => 'Ana Pérez', 'email' => 'ana@example.com'])
            ->expectsQuestion('Password', 'a-long-enough-password')
            ->expectsOutputToContain('ana@example.com created')
            ->assertSuccessful();

        $user = User::where('email', 'ana@example.com')->firstOrFail();

        $this->assertSame('Ana Pérez', $user->name);
        $this->assertTrue(Hash::check('a-long-enough-password', $user->password));
        $this->assertNotNull($user->email_verified_at, 'There is no inbox to verify from, so the address counts as verified.');

        $this->post(route('login.store'), ['email' => 'ana@example.com', 'password' => 'a-long-enough-password'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_new_user_reaches_the_private_pages()
    {
        $this->artisan('user:create', ['name' => 'Ana', 'email' => 'ana@example.com'])
            ->expectsQuestion('Password', 'a-long-enough-password')
            ->assertSuccessful();

        // The `verified` middleware would send an unverified user to a verification page.
        $this->actingAs(User::where('email', 'ana@example.com')->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_it_asks_for_the_name_and_the_email_when_they_are_not_given()
    {
        $this->artisan('user:create')
            ->expectsQuestion('Name', 'Luis')
            ->expectsQuestion('Email address', 'luis@example.com')
            ->expectsQuestion('Password', 'a-long-enough-password')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['name' => 'Luis', 'email' => 'luis@example.com']);
    }

    public function test_it_refuses_an_email_that_is_already_taken()
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->artisan('user:create', ['name' => 'Otra Ana', 'email' => 'ana@example.com'])
            ->expectsOutputToContain('has already been taken')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_it_refuses_something_that_is_not_an_email()
    {
        $this->artisan('user:create', ['name' => 'Ana', 'email' => 'not-an-email'])
            ->expectsOutputToContain('valid email')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_the_password_is_never_an_option_of_the_command()
    {
        // An option would end up in the shell history and in the process list.
        $options = array_keys($this->app->make('Illuminate\Contracts\Console\Kernel')->all()['user:create']->getDefinition()->getOptions());

        $this->assertNotContains('password', $options);
    }
}
