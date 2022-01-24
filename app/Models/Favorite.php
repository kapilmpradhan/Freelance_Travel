<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    use HasFactory;

    protected $table = 'favorites';
    protected $fillable = ['email', 'json', 'product_id'];

    public function rule()
    {
        return [
            'email' => 'required|email|max:100',
        ];
    }

    public function addFavorite($email, $product_id, $json)
    {
        $favorite = $this->where('email', $email)->where('product_id', $product_id)->first();
        if ($favorite) {
            $favorite->update(['json' => json_encode($json)]);
        } else {
            $favorite = $this->create([
                'email' => $email,
                'json' => json_encode($json),
                'product_id' => $product_id
            ]);
        }
        return $favorite;
    }

    public function getFavoriteByEmail($email)
    {
        return $this->where('email', $email)->paginate(6);
    }

    public function removeFavorite($email, $product_id)
    {
        $favorite = $this->where('email', $email)->where('product_id', $product_id);
        $favorite->delete();
    }
}
