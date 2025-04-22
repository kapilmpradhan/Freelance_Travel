<?php

namespace App\DTOs;

class RedeemerBookingsOrderData
{
    public $cartItemId;
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
    public $optionalData;

    public function __construct(
        $cartItemId,
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
        $optionalData = []
    ) {
        $this->cartItemId = $cartItemId;
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
        $this->optionalData = $optionalData;
    }

    public function toArray(): array
    {
        $optionalFields = [];
        foreach ($this->optionalData ?? [] as $key => $value) {
            $optionalFields[] = [
                "optionalFieldId" => $key,
                "optionalFieldValue" => $value,
            ];
        }
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
            "optionalFields" => $optionalFields,
        ];
    }
}
