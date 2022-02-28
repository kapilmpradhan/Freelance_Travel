<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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

    public function updateProduct($productId, $token)
    {
        // WHAT: call get product detail api
        $productDetail = getProductDetail($productId, $token);
        Log::info("Product detail: {$productDetail}");
        $product = json_decode($productDetail);
        $productImage = @$product->results[0]->productImagePath;
        $productName = @$product->results[0]->name;
        $productLocation = @$product->results[0]->categories->startLocationName ?? @$product->results[0]->country;
        $productRRP = @$product->results[0]->rrp;
        dump($product);
        $productDuration = "";
        $durationDay = intval(@$product->results[0]->durationDays);
        if ($durationDay > 0) {
            $productDuration .= $durationDay;
            if ($durationDay === 1) {
                $productDuration .= " day";
            } else {
                $productDuration .= " days";
            }
        }

        $durationNight = intval(@$product->results[0]->durationNight);
        if ($durationDay > 0 && $durationNight > 0) {
            $productDuration .= " - ";
        }

        if ($durationNight > 0) {
            $productDuration .= $durationNight;
            if ($durationNight === 1) {
                $productDuration .= " night";
            } else {
                $productDuration .= " nights";
            }
        }
        $updated = [
            'productImagePath' => $productImage,
            'name' => $productName,
            'location' => $productLocation,
            'duration' => $productDuration,
            'rrp' => $productRRP
        ];
        $updatedLog = json_encode($updated);

        Log::info("Product Info: {$updatedLog}");

        dd($updated);
        $this->where('productId', $productId)->update($updated);
    }
}
