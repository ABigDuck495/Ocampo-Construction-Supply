<?php

namespace App\Models;

// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    protected $table = 'users';
    protected $primaryKey = 'UserID';
    protected $fillable = ['Name', 'Password', 'Role', 'Email', 'PhoneNumber'];
    protected $hidden = ['Password'];

    protected $guarded = ['UserID'];
    public $timestamps = false;

    public function orders(){
        return $this->hasMany(Order::class, 'CreatedBy', 'UserID');
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

    // public function getAuthIdentifierName(): string
    // {
    //     return 'Email';
    // }

    public function resetPassword($newPassword) {
        $this->Password = bcrypt($newPassword);
        $this->save();
    }

    public function scopeAdmins($query) {
        return $query->where('Role', 'Admin');
    }
    public function scopeStaffs($query) {
        return $query->where('Role', 'Staff');
    }
}
