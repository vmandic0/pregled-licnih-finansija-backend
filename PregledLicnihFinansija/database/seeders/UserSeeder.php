<?php

namespace Database\Seeders;

use App\Models\Administrator;
use App\Models\Klijent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        $this->markAsDemo($demo);

        // Izmisljeni korisnici koje demo nalog moze da pozove u grupnu stednju.
        // Lozinka je nasumicna, pa se niko ne prijavljuje na ove naloge.
        $mockUsers = [
            'ana.demo@fintrack.test' => 'Ana Primer',
            'marko.demo@fintrack.test' => 'Marko Primer',
            'jelena.demo@fintrack.test' => 'Jelena Primer',
            'nikola.demo@fintrack.test' => 'Nikola Primer',
        ];

        foreach ($mockUsers as $email => $name) {
            $this->markAsDemo(User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make(Str::random(40)), 'type' => 'klijent']
            ));
        }
    }

    private function markAsDemo(User $user): void
    {
        // forceFill: is_demo namerno nije u Fillable, ne sme da dodje iz zahteva.
        $user->forceFill(['is_demo' => true])->save();

        Klijent::firstOrCreate(
            ['user_id' => $user->id],
            ['net_worth' => 0, 'premium_klijent' => true, 'preferred_currency' => 'RSD']
        );
    }
}
