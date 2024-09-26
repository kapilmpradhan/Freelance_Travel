<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingCancelRequest extends Model
{
    use HasFactory;

    protected $table = 'booking_cancel_requests';
    protected $fillable = ['email', 'bookingReference', 'voucherNumber', 'quantity', 'reason'];


    public function getAllByEmail($email)
    {
        return $this->where('email', $email)->get();
    }

    public function getAllByBookingReference($bookingReference)
    {
        return $this->where('bookingReference', $bookingReference)->get();
    }

    public function getOne($email, $bookingReference)
    {
        return $this->where('email', $email)->where('bookingReference', $bookingReference)->get();
    }

    public function createCancelRequest($data)
    {
        $cancelRequest = $this->where('bookingReference', $data['bookingReference'])
            ->where('voucherNumber', $data['voucherNumber'])
            ->first();
        if (isset($cancelRequest)) {
            $cancelRequest->update([
                "quantity" => $cancelRequest->quantity + $data['quantity'],
                "reason" => $data['reason'],
            ]);
        } else {
            $cancelRequest = $this->create($data);
        }

        return $cancelRequest;
    }
}
