<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{

    use HasFactory;
    protected $guarded = ['id'];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function booking_items()
    {
        return $this->hasMany(BookingItem::class, 'booking_id', 'id');
    }
    public function billing()
    {
        return $this->hasOne(BillingDetail::class);
    }
    public function review()
    {
        return $this->hasOne(Rating::class);
    }

}
