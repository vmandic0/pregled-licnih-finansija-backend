<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PonavljajucaTransakcija extends Model
{
    protected $table = 'ponavljajuce_transakcije';

    protected $fillable = [
        'klijent_id',
        'kategorija_id',
        'kolicina',
        'ucestalost',
        'interval_vrednost',
        'sledeci_datum',
        'aktivna',
    ];

    protected function casts(): array
    {
        return [
            'kolicina' => 'double',
            'interval_vrednost' => 'integer',
            'sledeci_datum' => 'date',
            'aktivna' => 'boolean',
        ];
    }

    public function klijent()
    {
        return $this->belongsTo(Klijent::class);
    }

    public function kategorija()
    {
        return $this->belongsTo(Kategorija::class);
    }
}
