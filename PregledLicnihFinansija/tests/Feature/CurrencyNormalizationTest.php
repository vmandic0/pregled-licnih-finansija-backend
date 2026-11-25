<?php

namespace Tests\Feature;

use App\Models\Kategorija;
use App\Models\Klijent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_amounts_are_saved_in_rsd_and_remain_stable_when_preferred_currency_changes(): void
    {
        Cache::flush();
        Http::fake([
            'https://v6.exchangerate-api.com/v6/*/pair/EUR/RSD' => Http::response([
                'result' => 'success',
                'conversion_rate' => 117.5,
            ]),
            'https://v6.exchangerate-api.com/v6/*/pair/RSD/USD' => Http::response([
                'result' => 'success',
                'conversion_rate' => 0.0085,
            ]),
        ]);

        $user = User::factory()->create(['type' => 'klijent']);
        $klijent = Klijent::create([
            'user_id' => $user->id,
            'net_worth' => 0,
            'premium_klijent' => true,
            'preferred_currency' => 'EUR',
        ]);
        $kategorija = Kategorija::create([
            'naziv' => 'Trošak',
            'tip' => 'trosak',
        ]);

        $this->actingAs($user)
            ->postJson('/api/transakcije', [
                'kategorija_id' => $kategorija->id,
                'kolicina' => 10,
                'datum' => '2026-11-25',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('transakcije', [
            'klijent_id' => $klijent->id,
            'kolicina' => 1175,
            'valuta' => 'RSD',
        ]);

        $this->actingAs($user)
            ->postJson('/api/ponavljajuce-transakcije', [
                'kategorija_id' => $kategorija->id,
                'kolicina' => 10,
                'ucestalost' => 'mesecno',
                'interval_vrednost' => 1,
                'sledeci_datum' => '2026-12-01',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('ponavljajuce_transakcije', [
            'klijent_id' => $klijent->id,
            'kolicina' => 1175,
            'valuta' => 'RSD',
        ]);

        $limitResponse = $this->actingAs($user)
            ->postJson('/api/limiti', [
                'kategorija_id' => $kategorija->id,
                'iznos' => 100,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('limiti', [
            'user_id' => $user->id,
            'iznos' => 11750,
            'valuta' => 'RSD',
        ]);

        $this->actingAs($user)
            ->putJson('/api/limiti/'.$limitResponse->json('id'), [
                'iznos' => 200,
            ])
            ->assertOk();

        $creditResponse = $this->actingAs($user)
            ->postJson('/api/krediti', [
                'pozajmljenaCifra' => 1000,
                'kamatnaStopa' => 5,
                'mesecnaRata' => 100,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('krediti', [
            'klijent_id' => $klijent->id,
            'pozajmljenaCifra' => 117500,
            'mesecnaRata' => 11750,
            'valuta' => 'RSD',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/krediti/'.$creditResponse->json('kredit.id'), [
                'mesecnaRata' => 200,
            ])
            ->assertOk();

        $groupResponse = $this->actingAs($user)
            ->postJson('/api/grupe', [
                'naziv' => 'Putovanje',
                'ciljIznos' => 200,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('grupne_transakcije', [
            'kreator_id' => $klijent->id,
            'ciljIznos' => 23500,
            'valuta' => 'RSD',
        ]);

        $this->actingAs($user)
            ->postJson('/api/grupe/'.$groupResponse->json('grupa.id').'/uplati', [
                'iznosUdela' => 20,
                'datumUplate' => '2026-11-25',
            ])
            ->assertCreated()
            ->assertJsonPath('udeo.valuta', 'RSD');

        $this->assertDatabaseHas('udeli_u_grupnoj_transakciji', [
            'grupna_transakcija_id' => $groupResponse->json('grupa.id'),
            'klijent_id' => $klijent->id,
            'iznosUdela' => 2350,
            'valuta' => 'RSD',
        ]);

        $this->actingAs($user)
            ->postJson('/api/promeni-valutu', ['valuta' => 'USD'])
            ->assertOk()
            ->assertJsonPath('preferred_currency', 'USD');

        $this->actingAs($user)
            ->postJson('/api/konvertuj', [
                'iznos' => 1,
                'iz_valute' => 'RSD',
                'u_valutu' => 'USD',
            ])
            ->assertOk()
            ->assertJsonPath('kurs', 0.0085);

        $this->assertDatabaseHas('transakcije', [
            'klijent_id' => $klijent->id,
            'kolicina' => 1175,
            'valuta' => 'RSD',
        ]);
    }
}
