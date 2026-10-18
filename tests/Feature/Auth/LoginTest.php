<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk');
    }

    public function test_authenticated_users_are_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect(route('dashboard'));
    }

    public function test_users_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'rahasia-kuat']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'rahasia-kuat',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create(['password' => 'rahasia-kuat']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password-salah',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_email_is_required_to_login(): void
    {
        $this->post('/login', [
            'email' => '',
            'password' => 'apa-saja',
        ])->assertSessionHasErrors('email');
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $user = User::factory()->create(['password' => 'rahasia-kuat']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'password-salah',
            ])->assertSessionHasErrors('email');
        }

        // Percobaan keenam dengan password benar tetap ditolak.
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'rahasia-kuat',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guest_is_redirected_from_root_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
