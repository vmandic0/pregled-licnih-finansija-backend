<?php

namespace Tests\Feature;

use App\Models\Kategorija;
use App\Models\Klijent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTwoDecimalTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_category_and_two_decimal_transaction_are_saved(): void
    {
        $user = User::factory()->create(['type' => 'klijent']);
        $klijent = Klijent::create([
            'user_id' => $user->id,
            'net_worth' => 0,
            'premium_klijent' => false,
            'preferred_currency' => 'RSD',
        ]);

        $categoryResponse = $this->actingAs($user)
            ->postJson('/api/kategorije', [
                'naziv' => 'Kafeterija',
                'tip' => 'trosak',
            ]);

        $categoryResponse
            ->assertCreated()
            ->assertJsonPath('naziv', 'Kafeterija')
            ->assertJsonPath('tip', 'trosak');

        $kategorija = Kategorija::where('naziv', 'Kafeterija')->firstOrFail();

        $transactionResponse = $this->actingAs($user)
            ->postJson('/api/transakcije', [
                'kategorija_id' => $kategorija->id,
                'kolicina' => 365.82,
                'datum' => '2026-11-23',
            ]);

        $transactionResponse
            ->assertCreated()
            ->assertJsonPath('transakcija.kolicina', '365.82');

        $this->assertDatabaseHas('transakcije', [
            'klijent_id' => $klijent->id,
            'kategorija_id' => $kategorija->id,
            'kolicina' => 365.82,
        ]);
    }

    public function test_amount_with_more_than_two_decimals_is_rejected(): void
    {
        $user = User::factory()->create(['type' => 'klijent']);
        Klijent::create([
            'user_id' => $user->id,
            'net_worth' => 0,
            'premium_klijent' => false,
            'preferred_currency' => 'RSD',
        ]);

        $kategorija = Kategorija::create([
            'naziv' => 'Test kategorija',
            'tip' => 'trosak',
        ]);

        $this->actingAs($user)
            ->postJson('/api/transakcije', [
                'kategorija_id' => $kategorija->id,
                'kolicina' => 365.821,
                'datum' => '2026-11-23',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kolicina');
    }
}
