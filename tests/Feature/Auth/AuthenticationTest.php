<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_login_page_is_accessible(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = $this->createUser(['password' => bcrypt('password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
             ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $user = $this->createUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
             ->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_user_cannot_login_with_unknown_email(): void
    {
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'password'])
             ->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
             ->post('/logout')
             ->assertRedirect();

        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login_from_root(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_authenticated_user_is_redirected_to_explore_from_root(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->get('/')->assertRedirect(route('clubs.explore'));
    }
}
