<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    protected $guarded = ['id'];

public function package()
{
    return $this->belongsTo(Package::class);
}
public function sender()
{
    return $this->belongsTo(User::class,'sender_id');
}
public function receiver()
{
    return $this->belongsTo(User::class,'receiver_id');
}

    protected static function booted()
    {
        static::creating(function ($transaction) {
            if (empty($transaction->transaction_id)) {
                $transaction->transaction_id = 'TXN-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
            }
        });
    }


}
