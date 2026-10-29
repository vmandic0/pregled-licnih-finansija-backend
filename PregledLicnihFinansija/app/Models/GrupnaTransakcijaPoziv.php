<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrupnaTransakcijaPoziv extends Model
{
    protected $table = 'grupna_transakcija_pozivi';

    protected $fillable = [
        'grupna_transakcija_id',
        'pozivalac_id',
        'pozvani_id',
        'status',
        'istice_at',
        'odgovoreno_at',
    ];

    protected function casts(): array
    {
        return [
            'istice_at' => 'datetime',
            'odgovoreno_at' => 'datetime',
        ];
    }

    public function grupa()
    {
        return $this->belongsTo(GrupnaTransakcija::class, 'grupna_transakcija_id');
    }

    public function pozivalac()
    {
        return $this->belongsTo(Klijent::class, 'pozivalac_id');
    }

    public function pozvani()
    {
        return $this->belongsTo(Klijent::class, 'pozvani_id');
    }
}
