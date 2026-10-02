<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{

    use HasApiTokens;
    protected $table = 'users';
    protected $primaryKey = 'UserID';
    protected $fillable = ['Name', 'Username', 'Password', 'Role', 'Email', 'PhoneNumber', 'Status', 'DriverID', 'LastLoginAt'];
    protected $hidden = ['Password'];

    protected $guarded = ['UserID'];
    public $timestamps = false;

    protected $casts = [
        'LastLoginAt' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $user) {
            $role = strtoupper($user->Role ?? 'Staff');

            if ($role === 'DRIVER') {
                $driver = Driver::firstOrCreate(
                    ['Name' => $user->Name],
                    ['PhoneNumber' => $user->PhoneNumber]
                );

                if ($user->PhoneNumber !== null && $driver->PhoneNumber !== $user->PhoneNumber) {
                    $driver->PhoneNumber = $user->PhoneNumber;
                    $driver->save();
                }

                $user->DriverID = $driver->DriverID;
                return;
            }

            if ($user->DriverID !== null) {
                $user->DriverID = null;
            }
        });
    }

    public function orders(){
        return $this->hasMany(Order::class, 'CreatedBy', 'UserID');
    }

    public function transactions(){
        return $this->hasMany(Transaction::class, 'CreatedBy', 'UserID');
    }

    public function setPasswordAttribute($value)
    {
        if (empty($value)) {
            return;
        }

        if (is_string($value) && preg_match('/^\$2[ayb]\$/i', $value)) {
            $this->attributes['Password'] = $value;
            return;
        }

        $this->attributes['Password'] = Hash::make($value);
    }

    public function getAuthPassword(): string
    {
        return $this->Password;
    }

    public function resetPassword($newPassword) {
        $this->Password = bcrypt($newPassword);
        $this->save();
    }

    public function markLoggedIn()
    {
        $this->LastLoginAt = now();
        $this->save();
    }

    public function scopeAdmins($query) {
        return $query->where('Role', 'Admin');
    }
    public function scopeStaffs($query) {
        return $query->where('Role', 'Staff');
    }
    public function scopeActive($query) {
        return $query->where('Status', 'Active');
    }
    public function scopeInactive($query) {
        return $query->where('Status', 'Inactive');
    }
}