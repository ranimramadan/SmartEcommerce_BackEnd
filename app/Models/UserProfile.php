<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class UserProfile extends Model {
    use HasFactory;
    protected $fillable = [
        'user_id', 'address', 'city', 'country', 'phone_number', 'birthdate', 'gender', 'profile_image'
    ];
    public function user() {
        return $this->belongsTo(User::class);
    }
}
