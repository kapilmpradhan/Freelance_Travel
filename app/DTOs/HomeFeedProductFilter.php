<?php

namespace App\DTOs;

class HomeFeedProductFilter
{
    public $countryId;
    public $filterBy;
    public $cacheKey;

    public function __construct(string $countryId, string $filterBy)
    {
        $this->countryId = $countryId;
        $this->filterBy = $filterBy;
        $this->cacheKey = 'home_feed_product:country_' . $this->countryId . '_' . $this->filterBy;
    }

    public static function fromRequest($request)
    {
        $countryId = $request->query('countryId');
        $filterBy = $request->query('filterBy');

        return new self($countryId, $filterBy);
    }

    public static function fromJob($countryId, $filterBy)
    {
        return new self($countryId, $filterBy);
    }
}
