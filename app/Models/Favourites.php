<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favourites extends Model
{
    use HasFactory;

    protected $table = 'favourites';
    protected $fillable = ['email', 'productId', 'name', 'location', 'duration', 'date', 'rrp', 'productImagePath'];
    protected $hidden = ['id', 'created_at', 'updated_at'];

    public function addFavorite($email, $productId, $data)
    {
        $favorite = $this->where('email', $email)->where('productId', $productId)->first();
        if ($favorite) {
            $favorite->update($data);
        } else {
            $favorite = $this->create($data);
        }
        return $favorite;
    }

    public function getFavoriteByEmail($email)
    {
        return $this->where('email', $email)->get();
    }


    public function removeFavorite($email, $productId)
    {
        $favorite = $this->where('email', $email)->where('productId', $productId);
        $favorite->delete();
    }
}
