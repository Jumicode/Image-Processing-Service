<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = ['user_id','path'];
 // Relationship: An image belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
