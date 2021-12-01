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
        return $this->where('bookingReference', $bookingReference)->first();
    }


    public function CallApiDetailBooking($booking)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "{$booking->request_url}/voucher/{$booking->order_id}.json");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer $booking->accessToken",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $result = curl_exec($curl);
        return $result;
    }
}
