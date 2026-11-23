<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kategorija extends Model
{
    protected $table = 'kategorije';

    protected $fillable = [
        'naziv',
        'tip',
        'user_id',
    ];

    public function transakcije()
    {
        return $this->hasMany(Transakcija::class);
    }
}
