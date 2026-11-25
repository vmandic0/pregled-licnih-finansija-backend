<?php

namespace Tests\Feature;

use App\Models\Kategorija;
use App\Models\Klijent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionImmediateChargeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_subscription_due_today_is_charged_immediately_without_being_generated_twice(): void
    {
        CarbonImmutable::setTestNow('2026-11-25 10:00:00');
        Cache::flush();
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
            'naziv' => 'Pretplate',
            'tip' => 'trosak',
        ]);

        $this->actingAs($user)
            ->postJson('/api/subskripcije', [
                'naziv' => 'Netflix',
                'iznos' => 10,
                'valuta' => 'EUR',
                'datum_naplate' => '2026-11-25',
                'ucestalost' => 'mesecno',
                'kategorija_id' => $expenseCategory->id,
                'aktivna' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('transakcije', [
            'klijent_id' => $klijent->id,
            'kategorija_id' => $expenseCategory->id,
            'kolicina' => 1175,
            'valuta' => 'RSD',
            'datum' => '2026-11-25 00:00:00',
        ]);
        $this->assertDatabaseHas('subskripcije', [
            'klijent_id' => $klijent->id,
            'naziv' => 'Netflix',
            'sledeci_datum' => '2026-12-25 00:00:00',
        ]);

        Artisan::call('transactions:generate-recurring');

        $this->assertDatabaseCount('transakcije', 1);
    }
}
