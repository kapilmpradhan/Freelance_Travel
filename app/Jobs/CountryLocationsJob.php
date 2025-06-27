<?php

namespace App\Jobs;

use App\Logging\Logger;
use App\Services\TdmsService;
use App\Services\UserAgentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class CountryLocationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $agentTokenResponse = UserAgentService::getDefaultAgentToken();
            $countries = TdmsService::getCountries($agentTokenResponse->data['access_token'])->data;
            foreach ($countries as &$country) {
                Logger::info('Fetching states for country: ' . $country['text']);
                $states = TdmsService::getCategoriesByTypeAndSubtype(
                    type: 'states',
                    subType: 'countries',
                    subtypeId: $country['id'],
                    agentToken: $agentTokenResponse->data['access_token']
                )->data;
                if (empty($states)) {
                    Logger::info('No states found for country: ' . $country['text']);
                    Logger::info('Fetching regions for country: ' . $country['text']);
                    $regions = TdmsService::getCategoriesByTypeAndSubtype(
                        type: 'regions',
                        subType: 'countries',
                        subtypeId: $country['id'],
                        agentToken: $agentTokenResponse->data['access_token']
                    )->data;

                    if (empty($regions)) {
                        Logger::info('No regions found for country: ' . $country['text']);
                        Logger::info('Fetching locations for country: ' . $country['text']);
                        $locations = TdmsService::getCategoriesByTypeAndSubtype(
                            type: 'locality',
                            subType: 'regions',
                            subtypeId: $country['id'],
                            agentToken: $agentTokenResponse->data['access_token']
                        )->data;
                        $country['locations'] = $locations;
                    } else {
                        foreach ($regions as &$region) {
                            Logger::info('Fetching location for region: ' . $region['text']);
                            $locations = TdmsService::getCategoriesByTypeAndSubtype(
                                type: 'locality',
                                subType: 'regions',
                                subtypeId: $region['id'],
                                agentToken: $agentTokenResponse->data['access_token']
                            )->data;

                            $region['locations'] = $locations;
                        }
                        $country['regions'] = $regions;
                    }
                } else {
                    foreach ($states as &$state) {
                        Logger::info('Fetching regions for state: ' . $state['text']);
                        $regions = TdmsService::getCategoriesByTypeAndSubtype(
                            type: 'regions',
                            subType: 'states',
                            subtypeId: $state['id'],
                            agentToken: $agentTokenResponse->data['access_token']
                        )->data;

                        foreach ($regions as &$region) {
                            Logger::info('Fetching location for region: ' . $region['text']);
                            $locations = TdmsService::getCategoriesByTypeAndSubtype(
                                type: 'locality',
                                subType: 'regions',
                                subtypeId: $region['id'],
                                agentToken: $agentTokenResponse->data['access_token']
                            )->data;

                            $region['locations'] = $locations;
                        }
                        $state['regions'] = $regions;
                    }
                    $country['states'] = $states;
                }
            }

            Redis::set('all_countries_states_regions_locations', json_encode($countries));
            Logger::info('Country locations caching job completed successfully');
        } catch (Exception $e) {
            Logger::error('Error in CountryLocationsJob: ' . $e);
            throw $e;
        }
    }
}
