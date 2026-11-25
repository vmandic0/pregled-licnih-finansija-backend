<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UdeoUGrupnojTransakciji extends Model
{
    protected $table = 'udeli_u_grupnoj_transakciji';

    protected $fillable = [
        'grupna_transakcija_id',
        'klijent_id',
        'iznosUdela',
        'valuta',
        'datumUplate',
    ];

    protected function casts(): array
    {
        return [
            'iznosUdela' => 'double',
            'datumUplate' => 'date',
        ];
    }

    public function grupnaTransakcija()
    {
        return $this->belongsTo(GrupnaTransakcija::class);
    }

    public function klijent()
    {
        return $this->belongsTo(Klijent::class);
    }
    public function clanovi()
    {
        return $this->belongsToMany(Klijent::class, 'udeli_u_grupnoj_transakciji', 'grupna_transakcija_id', 'klijent_id');
    }
}
