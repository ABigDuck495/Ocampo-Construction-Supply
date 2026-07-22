<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'UserID';
    protected $fillable = ['Name', 'Password', 'Role', 'Email', 'PhoneNumber'];

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

    public function isAdmin() {
        return $this->Role === 'Admin';
    }
    public function isStaff() {
        return $this->Role === 'Staff';
    }
}
