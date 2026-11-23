<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Transakcija extends Model
{
    use HasFactory;
    protected $table = 'transakcije';

    protected $fillable = [
        'klijent_id',
        'kategorija_id',
        'kolicina',
        'datum',
        'valuta',
        'request_id',
    ];

    protected function casts(): array
    {
        return [
            'kolicina' => 'decimal:2',
            'datum' => 'date',
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