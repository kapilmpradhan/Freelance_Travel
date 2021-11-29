<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $table = 'orders';
    protected $fillable = ['booking_id', 'bookingReference', 'accessToken', 'order_id', "request_url", "return_url"];

    public function storeOrder($request)
    {
        $params = [
            'booking_id' => $request->booking_id,
            'bookingReference' => $request->bookingReference,
            'accessToken' => $request->accessToken,
            'order_id' => $request->order_id,
            'request_url' => $request->request_url,
            'return_url' => $request->return_url,
        ];
        return $this->create($params);
    }

    public function getAccessToken($bookingReference)
    {
        return $this->where('bookingReference', $bookingReference)->pluck('accessToken')->toArray();
    }
}
