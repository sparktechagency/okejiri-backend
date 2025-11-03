<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function getImageAttribute($file)
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

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }
    public function service_completions()
    {
        return $this->hasMany(EmployeeServiceCompletion::class);
    }

    public function services_provided()
    {
        return $this->belongsToMany(
            Booking::class,
            'employee_service_completions',
            'employee_id',
            'booking_id'
        )->without('pivot');
    }

}
