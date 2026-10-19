<?php

namespace Database\Seeders;


use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Kategorija;

class KategorijaSeeder extends Seeder
{
    
    public function run(): void
    {
        $kategorije = [
            ['naziv' => 'Namirnice', 'tip' => 'trosak'],
            ['naziv' => 'Gorivo', 'tip' => 'trosak'],
            ['naziv' => 'Kirija', 'tip' => 'trosak'],
            ['naziv' => 'Izlasci', 'tip' => 'trosak'],
            ['naziv' => 'Odeca', 'tip' => 'trosak'],
            ['naziv' => 'Putovanja', 'tip' => 'trosak'],
            ['naziv' => 'Pokloni', 'tip' => 'trosak'],
            ['naziv' => 'Plata', 'tip' => 'prihod'],
            ['naziv' => 'Investicije', 'tip' => 'prihod'],
            ['naziv' => 'Obrazovanje', 'tip' => 'trosak'],
            ['naziv' => 'Stipendija', 'tip' => 'prihod'],
            ['naziv' => 'Honorar', 'tip' => 'prihod'],
            ['naziv' => 'Lepota i nega', 'tip' => 'trosak'],
            ['naziv' => 'Sport', 'tip' => 'trosak'],
            ['naziv' => 'Kucni ljubimac', 'tip' => 'trosak'],
            ['naziv' => 'Racuni', 'tip' => 'trosak'],
            ['naziv' => 'Reskorani i kafici', 'tip' => 'trosak'],
            ['naziv' => 'Kucne potrepstine', 'tip' => 'trosak'],
            ['naziv' => 'Cigarete', 'tip' => 'trosak'],
            ['naziv' => 'Deciji troskovi', 'tip' => 'trosak'],
            ['naziv' => 'Nepredvidjeni trosak', 'tip' => 'trosak'],
            ['naziv' => 'Nepredvidjeni prihod', 'tip' => 'prihod'],
            ['naziv' => 'Subskripcije', 'tip' => 'trosak'],


        ];

        foreach ($kategorije as $kategorija) {
            Kategorija::firstOrCreate($kategorija);
        }
    }
}
