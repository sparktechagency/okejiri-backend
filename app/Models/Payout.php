<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    protected $guarded = ['id'];

    public function provider(){
        return $this->belongsTo(User::class,'provider_id');
    }
}
