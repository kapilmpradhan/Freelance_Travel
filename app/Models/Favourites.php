<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favourites extends Model
{
    use HasFactory;

    protected $table = 'favourites';
    protected $fillable = ['user_id', 'tdms_product_id'];
    protected $hidden = ['id', 'created_at', 'updated_at'];
}
