<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferUser extends Model
{
    protected $guarded = ['id'];

    public function referred_user(){
        return $this->belongsTo(User::class,'referred');
    }

}
