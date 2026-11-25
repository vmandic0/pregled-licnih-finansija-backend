<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Limit extends Model
{
    protected $table = 'limiti';

protected $fillable = [
    'user_id',
    'kategorija_id',
    'iznos',
    'valuta',
];

public function user()
{
    return $this->belongsTo(User::class);
}

public function kategorija()
{
    return $this->belongsTo(Kategorija::class);
}
}
