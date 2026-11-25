<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kredit extends Model
{
    use HasFactory;
    protected $table = 'krediti';

    protected $fillable = [
        'klijent_id',
        'pozajmljenaCifra',
        'kamatnaStopa',
        'mesecnaRata',
        'valuta',
    ];

    protected function casts(): array
    {
        return [
            'pozajmljenaCifra' => 'double',
            'kamatnaStopa' => 'double',
            'mesecnaRata' => 'double',
        ];
    }

    public function klijent()
    {
        return $this->belongsTo(Klijent::class);
    }

    // Use case 8 - automatsko racunanje vremena do otplate
    public function racunajVremeOtplate(): float
    {
        if ($this->mesecnaRata <= 0) {
            return 0;
        }

        $ukupanDug = $this->pozajmljenaCifra * (1 + $this->kamatnaStopa / 100);
        return ceil($ukupanDug / $this->mesecnaRata);
    }
}