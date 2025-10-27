<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    protected $guarded = ['id'];

    public function getAttachmentsAttribute($file)
    {
        $useStoragePrefix = false;
        $prefix           = $useStoragePrefix ? 'storage/' : '';

        $isJson = fn($string) => is_string($string)
        && str_starts_with(trim($string), '[')
        && str_ends_with(trim($string), ']');

        $buildUrl = function ($f) use ($prefix) {
            $path = $prefix . ltrim($f, '/');
            return asset($path);
        };

        if ($isJson($file)) {
            $files = json_decode($file, true);
            if (is_array($files)) {
                return array_map($buildUrl, $files);
            }
        }

        if (is_string($file) && trim($file) !== '') {
            return $buildUrl($file);
        }

        return null;
    }

    public function appeal()
    {
        return $this->hasOne(DisputeAppeal::class);
    }
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
    public function to_user()
    {
        return $this->belongsTo(User::class,'to_user_id');
    }
    public function from_user()
    {
        return $this->belongsTo(User::class,'from_user_id');
    }
}
