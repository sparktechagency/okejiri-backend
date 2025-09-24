<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $guarded = ['id'];

    public function getImageAttribute($file)
    {
        $useStoragePrefix = false;
        $prefix = $useStoragePrefix ? 'storage/' : '';

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

    public function service(){
        return $this->belongsTo(Service::class);
    }
    public function provider(){
        return $this->belongsTo(User::class,'provider_id');
    }
    public function package_detail_items(){
        return $this->hasMany(PackageDetail::class);
    }
    public function available_time(){
        return $this->hasMany(PackageAvailableTime::class);
    }
}
