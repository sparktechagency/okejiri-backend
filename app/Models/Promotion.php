<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
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
}
