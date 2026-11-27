<?php

namespace Tests\Feature;

use App\Models\Klijent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupUserSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_search_ignores_letter_case_for_name_and_email(): void
    {
        $searcher = $this->premiumClient('Demo Korisnik', 'demo@example.com');
        $this->premiumClient('Marko Test', 'prvi@example.com');

        foreach (['marko', 'MARKO', 'PRVI@'] as $search) {
            $this->actingAs($searcher)
                ->getJson('/api/grupe/dostupni-korisnici?pretraga='.urlencode($search))
                ->assertOk()
                ->assertJsonCount(1)
                ->assertJsonPath('0.name', 'Marko Test');
        }
    }

    public function test_demo_and_real_users_cannot_find_or_invite_each_other(): void
    {
        // Real user registered before seeding must stay hidden from the demo account.
        $real = $this->premiumClient('Stvarni Korisnik', 'real@example.com');
        $this->seed();
        $this->seed(); // seeder runs on every container start
        $demo = User::where('email', 'demo@fintrack.test')->firstOrFail();

        $names = $this->actingAs($demo)->getJson('/api/grupe/dostupni-korisnici')
            ->assertOk()->json('*.name');
        $this->assertEqualsCanonicalizing(
            ['Ana Primer', 'Marko Primer', 'Jelena Primer', 'Nikola Primer'],
            $names,
        );

        $this->actingAs($real)->getJson('/api/grupe/dostupni-korisnici?pretraga=primer')
            ->assertOk()->assertJsonCount(0);

        $mock = User::where('email', 'ana.demo@fintrack.test')->firstOrFail();

        foreach ([[$demo, $real], [$real, $demo]] as [$from, $to]) {
            $this->actingAs($from)->postJson('/api/grupe', [
                'naziv' => 'Test', 'ciljIznos' => 100, 'clanovi' => [$to->klijent->id],
            ])->assertForbidden();
        }
        $this->assertDatabaseCount('grupne_transakcije', 0);

        $groupId = $this->actingAs($demo)->postJson('/api/grupe', [
            'naziv' => 'Demo grupa', 'ciljIznos' => 100, 'clanovi' => [$mock->klijent->id],
        ])->assertCreated()->assertJsonPath('poslato_poziva', 1)->json('grupa.id');

        $this->actingAs($demo)
            ->postJson("/api/grupe/{$groupId}/dodaj-clana", ['klijent_id' => $real->klijent->id])
            ->assertForbidden();
        $this->assertDatabaseMissing('grupna_transakcija_pozivi', ['pozvani_id' => $real->klijent->id]);
    }

    public function test_registration_cannot_create_a_demo_account(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/register', [
            'name' => 'Neko', 'email' => 'neko@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123', 'age_confirmed' => true, 'is_demo' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'neko@example.com', 'is_demo' => false]);
    }

    private function premiumClient(string $name, string $email): User
    {
        $user = User::factory()->create(['name' => $name, 'email' => $email, 'type' => 'klijent']);
        Klijent::create([
            'user_id' => $user->id,
            'net_worth' => 0,
            'premium_klijent' => true,
            'preferred_currency' => 'RSD',
        ]);

        return $user;
    }
}
