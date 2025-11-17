<?php

namespace App\DTOs;

class HomeFeedProductFilter
{
    public $agentBranchCode;
    public $countryId;
    public $filterBy;
    public $cacheKey;

    public function __construct(string|null $agentBranchCode, string $countryId, string $filterBy)
    {
        $this->agentBranchCode = $agentBranchCode;
        $this->countryId = $countryId;
        $this->filterBy = $filterBy;
        if ($agentBranchCode) {
            $this->cacheKey = "home_feed_product:{$this->agentBranchCode}_country_"
                . $this->countryId
                . '_'
                . $this->filterBy;
        } else {
            $this->cacheKey = "home_feed_product:country_" . $this->countryId . '_' . $this->filterBy;
        }
    }

    public static function fromRequest($request)
    {
        $agentType = app('agentType');
        $agentBranchCode = $agentType->agent->branch_code;
        $countryId = $request->query('countryId');
        $filterBy = $request->query('filterBy');

        return new self($agentBranchCode, $countryId, $filterBy);
    }

    public static function fromJob($agentBranchCode, $countryId, $filterBy)
    {
        return new self($agentBranchCode, $countryId, $filterBy);
    }
}
