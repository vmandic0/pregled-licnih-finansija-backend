<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrupnaTransakcija extends Model
{
    use HasFactory;
    protected $table = 'grupne_transakcije';

    protected $fillable = [
        'kreator_id',
        'naziv',
        'ciljIznos',
        'trenutnoPrikupljeno',
        'rok',
    ];

    protected function casts(): array
    {
        return [
            'ciljIznos' => 'double',
            'trenutnoPrikupljeno' => 'double',
            'rok' => 'date:Y-m-d',
        ];
    }

    public function kreator()
    {
        return $this->belongsTo(Klijent::class, 'kreator_id');
    }

    public function udeli()
    {
        return $this->hasMany(UdeoUGrupnojTransakciji::class);
    }

    public function pozivi()
    {
        return $this->hasMany(GrupnaTransakcijaPoziv::class, 'grupna_transakcija_id');
    }

    // Use case 10 - proverava stanje prikupljanja
    public function proveriStanje(): float
    {
        return ($this->trenutnoPrikupljeno / $this->ciljIznos) * 100;
    }
}
