<?php

namespace Database\Seeders;

use App\Models\Administrator;
use App\Models\Klijent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin: podaci iz env varijabli (repo je javan, lozinka ne sme u kod)
        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');

        if ($adminEmail && $adminPassword) {
            $admin = User::firstOrCreate(
                ['email' => $adminEmail],
                ['name' => 'Administrator', 'password' => Hash::make($adminPassword), 'type' => 'administrator']
            );
            Administrator::firstOrCreate(['user_id' => $admin->id]);
            Klijent::firstOrCreate(
                ['user_id' => $admin->id],
                [
                    'net_worth' => 0,
                    'premium_klijent' => true,
                    'preferred_currency' => 'RSD',
                ],
            );
        } else {
            $this->command->warn('ADMIN_EMAIL / ADMIN_PASSWORD nisu postavljeni - admin nije kreiran.');
        }

        // Premium demo nalog za posetioce CV-ja (javni podaci, namerno)
        $demo = User::firstOrCreate(
            ['email' => 'demo@fintrack.test'],
            ['name' => 'Demo Korisnik', 'password' => Hash::make('demo1234'), 'type' => 'klijent']
        );
        Klijent::firstOrCreate(
            ['user_id' => $demo->id],
            ['net_worth' => 0, 'premium_klijent' => true, 'preferred_currency' => 'RSD']
        );
    }
}