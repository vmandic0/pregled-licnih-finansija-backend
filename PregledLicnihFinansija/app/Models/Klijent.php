<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Kredit;
use App\Models\Transakcija;
use App\Models\GrupnaTransakcija;
use App\Models\UdeoUGrupnojTransakciji;

class Klijent extends Model
{
    protected $table = 'klijenti';

    protected $fillable = [
        'user_id',
        'net_worth',
        'premium_klijent',
        'preferred_currency',
    ];

    protected function casts(): array
    {
        return [
            'net_worth' => 'double',
            'premium_klijent' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPremium(): bool
    {
        return $this->premium_klijent === true;
    }
    public function transakcije()
    {
        return $this->hasMany(Transakcija::class);
    }
    public function krediti()
    {
        return $this->hasMany(Kredit::class);
    }
    public function azurirajNetWorth(): void
    {
        $prihodi = Transakcija::where('klijent_id', $this->id)
            ->whereHas('kategorija', fn($q) => $q->where('tip', 'prihod'))
            ->sum('kolicina');

        $troskovi = Transakcija::where('klijent_id', $this->id)
            ->whereHas('kategorija', fn($q) => $q->where('tip', 'trosak'))
            ->sum('kolicina');

        $dugovi = Kredit::where('klijent_id', $this->id)
            ->get()
            ->sum(fn($k) => $k->pozajmljenaCifra * (1 + $k->kamatnaStopa / 100));

        $this->net_worth = round($prihodi - $troskovi - $dugovi, 2);
        $this->save();
    }
    public function getNetWorth(): float
    {
        $this->azurirajNetWorth();
        return $this->net_worth;
    }

    public function grupneTransakcije()
    {
        return $this->hasMany(GrupnaTransakcija::class, 'kreator_id');
    }

    public function udeli()
    {
        return $this->hasMany(UdeoUGrupnojTransakciji::class);
    }
    public function grupneTransakcijeKaoClan()
    {
        return $this->belongsToMany(GrupnaTransakcija::class, 'udeli_u_grupnoj_transakciji', 'klijent_id', 'grupna_transakcija_id');
    }
}
