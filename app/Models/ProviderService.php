<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderService extends Model
{
    protected $guarded = ['id'];

    public function service(){
        return $this->belongsTo(Service::class);
    }
}
