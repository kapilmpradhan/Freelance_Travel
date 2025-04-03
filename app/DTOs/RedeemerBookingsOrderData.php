<?php

namespace App\DTOs;

class RedeemerBookingsOrderData
{
    public $bookingComment;
    public $bookingDetailsComment;
    public $travelDate;
    public $timeId;
    public $commences;
    public $pickupId;
    public $pickupLocation;
    public $dropoffId;
    public $dropoffLocation;
    public $datePriceCacheId;

    public function __construct(
        $travelDate,
        $timeId,
        $commences,
        $pickupId,
        $pickupLocation,
        $dropoffId,
        $dropoffLocation,
        $datePriceCacheId,
        $bookingComment = null,
        $bookingDetailsComment = null,
    ) {
        $this->bookingComment = $bookingComment;
        $this->bookingDetailsComment = $bookingDetailsComment;
        $this->travelDate = $travelDate;
        $this->timeId = $timeId;
        $this->commences = $commences;
        $this->pickupId = $pickupId;
        $this->pickupLocation = $pickupLocation;
        $this->dropoffId = $dropoffId;
        $this->dropoffLocation = $dropoffLocation;
        $this->datePriceCacheId = $datePriceCacheId;
    }

    public function toArray(): array
    {
        return [
            "bookingComment" => $this->bookingComment,
            "bookingDetailsComment" => $this->bookingDetailsComment,
            "travelDate" => $this->travelDate,
            "timeId" => $this->timeId,
            "commences" => $this->commences,
            "pickupId" => $this->pickupId,
            "pickupLocation" => $this->pickupLocation,
            "dropoffId" => $this->dropoffId,
            "dropoffLocation" => $this->dropoffLocation,
            "datePriceCacheId" => $this->datePriceCacheId,
        ];
    }
}
