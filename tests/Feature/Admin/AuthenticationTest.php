<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        foreach (['admin.dashboard', 'admin.leads', 'admin.media', 'admin.keys', 'admin.theme', 'admin.details'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.login'));
        }
    }

    public function test_the_owner_can_sign_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.test',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        Livewire::test(Login::class)
            ->set('email', 'owner@example.test')
            ->set('password', 'correct-horse-battery')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_is_rejected_without_revealing_which_field_failed(): void
    {
        User::factory()->create([
            'email' => 'owner@example.test',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        Livewire::test(Login::class)
            ->set('email', 'owner@example.test')
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_repeated_failures_are_throttled(): void
    {
        User::factory()->create(['email' => 'owner@example.test']);

        $component = Livewire::test(Login::class)
            ->set('email', 'owner@example.test')
            ->set('password', 'wrong-password');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $component->call('authenticate');
        }

        $component->call('authenticate');

        $this->assertStringContainsString(
            'Too many attempts',
            $component->errors()->first('email'),
        );
    }

    public function test_signed_in_owners_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    /**
     * Asserts the absence of the route itself rather than a status code: every
     * URL now resolves through the page fallback, so an unhandled POST answers
     * 405 rather than 404. What matters is that nothing can register an account.
     */
    public function test_the_site_exposes_no_public_registration_route(): void
    {
        $this->get('/register')->assertNotFound();

        $this->assertFalse(Route::has('register'));

        $this->assertEmpty(
            collect(Route::getRoutes())->filter(
                fn ($route): bool => str_contains($route->uri(), 'register')
            ),
        );
    }
}
