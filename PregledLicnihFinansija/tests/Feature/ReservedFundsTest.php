<?php

namespace Tests\Feature;

use App\Models\Kategorija;
use App\Models\Klijent;
use App\Models\PonavljajucaTransakcija;
use App\Models\Subskripcija;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReservedFundsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_it_sums_monthly_expense_occurrences_and_releases_amounts_when_generated(): void
    {
        CarbonImmutable::setTestNow('2026-11-01 10:00:00');
        Http::fake([
            'https://v6.exchangerate-api.com/v6/*/pair/EUR/RSD' => Http::response([
                'result' => 'success',
                'conversion_rate' => 117.5,
            ]),
        ]);

        $user = User::factory()->create(['type' => 'klijent']);
        $klijent = Klijent::create([
            'user_id' => $user->id,
            'net_worth' => 0,
            'premium_klijent' => false,
            'preferred_currency' => 'RSD',
        ]);
        $expenseCategory = Kategorija::create([
            'naziv' => 'Redovni trošak',
            'tip' => 'trosak',
        ]);
        $incomeCategory = Kategorija::create([
            'naziv' => 'Redovni prihod',
            'tip' => 'prihod',
        ]);

        PonavljajucaTransakcija::create([
            'klijent_id' => $klijent->id,
            'kategorija_id' => $expenseCategory->id,
            'kolicina' => 10,
            'ucestalost' => 'nedeljno',
            'interval_vrednost' => 1,
            'sledeci_datum' => '2026-11-01',
            'aktivna' => true,
        ]);
        PonavljajucaTransakcija::create([
            'klijent_id' => $klijent->id,
            'kategorija_id' => $incomeCategory->id,
            'kolicina' => 999,
            'ucestalost' => 'nedeljno',
            'interval_vrednost' => 1,
            'sledeci_datum' => '2026-11-01',
            'aktivna' => true,
        ]);
        Subskripcija::create([
            'klijent_id' => $klijent->id,
            'kategorija_id' => $expenseCategory->id,
            'naziv' => 'Pretplata',
            'iznos' => 5,
            'valuta' => 'EUR',
            'dan_naplate' => 1,
            'ucestalost' => 'mesecno',
            'sledeci_datum' => '2026-11-01',
            'aktivna' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/rezervisana-sredstva')
            ->assertOk()
            ->assertJsonPath('rezervisano', 637.5)
            ->assertJsonPath('valuta', 'RSD')
            ->assertJsonPath('period.od', '2026-11-01')
            ->assertJsonPath('period.do', '2026-11-30')
            ->assertJsonPath('razrada.ponavljajuce_transakcije', 50)
            ->assertJsonPath('razrada.pretplate', 587.5);

        Artisan::call('transactions:generate-recurring');

        $this->actingAs($user)
            ->getJson('/api/rezervisana-sredstva')
            ->assertOk()
            ->assertJsonPath('rezervisano', 40)
            ->assertJsonPath('razrada.ponavljajuce_transakcije', 40)
            ->assertJsonPath('razrada.pretplate', 0);

        $this->assertDatabaseHas('transakcije', [
            'klijent_id' => $klijent->id,
            'kolicina' => 587.5,
            'valuta' => 'RSD',
        ]);
    }
}
