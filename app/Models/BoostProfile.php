<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoostProfile extends Model
{
    protected $guarded = ['id'];

    public function getIsBoostingPauseAttribute($value)
    {
        return (bool) $value;
    }
        public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }
}
