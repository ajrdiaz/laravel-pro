<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationToggleTest extends TestCase
{
    /**
     * The features Fortify gets from config/fortify.php when the environment says so.
     *
     * @return list<string>
     */
    private function featuresWith(?string $registrationEnabled): array
    {
        $previous = getenv('REGISTRATION_ENABLED');

        try {
            if ($registrationEnabled === null) {
                putenv('REGISTRATION_ENABLED');
                unset($_ENV['REGISTRATION_ENABLED'], $_SERVER['REGISTRATION_ENABLED']);
            } else {
                putenv("REGISTRATION_ENABLED={$registrationEnabled}");
                $_ENV['REGISTRATION_ENABLED'] = $_SERVER['REGISTRATION_ENABLED'] = $registrationEnabled;
            }

            return array_map('strval', (require config_path('fortify.php'))['features']);
        } finally {
            if ($previous === false) {
                putenv('REGISTRATION_ENABLED');
                unset($_ENV['REGISTRATION_ENABLED'], $_SERVER['REGISTRATION_ENABLED']);
            } else {
                putenv("REGISTRATION_ENABLED={$previous}");
                $_ENV['REGISTRATION_ENABLED'] = $_SERVER['REGISTRATION_ENABLED'] = $previous;
            }
        }
    }

    public function test_registration_is_on_unless_the_environment_turns_it_off()
    {
        $this->assertContains('registration', $this->featuresWith(null));
        $this->assertContains('registration', $this->featuresWith('true'));
        $this->assertNotContains('registration', $this->featuresWith('false'));
    }

    public function test_turning_registration_off_leaves_the_rest_of_the_account_features_alone()
    {
        $on = $this->featuresWith('true');
        $off = $this->featuresWith('false');

        $this->assertSame(array_values(array_diff($on, ['registration'])), $off);
        $this->assertContains('reset-passwords', $off);
        $this->assertContains('email-verification', $off);
    }

    public function test_the_login_page_offers_to_sign_up_while_registration_is_on()
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('canRegister', true));

        $this->get(route('register'))->assertOk();
    }

    public function test_the_login_page_stops_offering_it_once_registration_is_off()
    {
        config(['fortify.features' => array_values(array_diff(config('fortify.features'), ['registration']))]);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('canRegister', false)
                ->where('canResetPassword', true));
    }
}
