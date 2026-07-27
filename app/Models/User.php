<?php

namespace App\Models;

// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User as Authenticatable;
// use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    protected $table = 'users';
    protected $primaryKey = 'UserID';
    protected $fillable = ['Name', 'Password', 'Role', 'Email', 'PhoneNumber'];
    protected $hidden = ['Password'];

    protected $guarded = ['UserID'];

    public function orders(){
        return $this->hasMany(Order::class, 'CreatedBy', 'UserID');
    }
    public function setPasswordAttribute($value) {
        $this->attributes['Password'] = bcrypt($value);
    }
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
