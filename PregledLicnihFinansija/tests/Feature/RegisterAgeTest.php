<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterAgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_age_confirmation(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test Korisnik',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('age_confirmed');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_records_age_confirmation_timestamp(): void
    {
        $response = $this
            ->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/register', [
                'name' => 'Test Korisnik',
                'email' => 'test@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'age_confirmed' => true,
            ]);

        $response->assertSuccessful();

        $this->assertNotNull(User::where('email', 'test@example.com')->value('age_confirmed_at'));
    }
}
