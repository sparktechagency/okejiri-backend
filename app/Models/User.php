<?php
namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'stripe_charges_enabled',
        'stripe_payouts_enabled',
        'stripe_account_id',
        'otp_expires_at',
        'updated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function getIsBlockedAttribute($value)
    {
        return (bool) $value;
    }

    public function getAvatarAttribute($file)
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
    public function getIdCardFrontAttribute($file)
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
    public function getIdCardBackAttribute($file)
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
    public function getSelfieAttribute($file)
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
    public function getIsPersonalizationCompleteAttribute($value)
    {
        return (bool) $value;
    }
    public function getHasServiceAttribute($value)
    {
        return (bool) $value;
    }

    public function company()
    {
        return $this->hasOne(Company::class, 'provider_id');
    }
    public function provider_services()
    {
        return $this->hasMany(ProviderService::class, 'provider_id');
    }
}
