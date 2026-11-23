<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subskripcija extends Model
{
    protected $table = 'subskripcije';

    protected $fillable = [
        'klijent_id',
        'kategorija_id',
        'naziv',
        'iznos',
        'valuta',
        'dan_naplate',
        'ucestalost',
        'sledeci_datum',
        'aktivna',
        'request_id',
    ];

    protected function casts(): array
    {
        return [
            'iznos' => 'double',
            'dan_naplate' => 'integer',
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
