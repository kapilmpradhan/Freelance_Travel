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
        Logger::debug('Country locations job started', [
            'log_file' => config('logging.log_files.products'),
            'action' => 'country_locations_job_start',
        ]);

        try {
            $agentTokenResponse = UserAgentService::getDefaultAgentToken();
            $countries = TdmsService::getCountries($agentTokenResponse->data['access_token'])->data;
            foreach ($countries as &$country) {
                Logger::debug('Fetching states for country', [
                    'log_file' => config('logging.log_files.products'),
                    'country' => $country['text'],
                    'action' => 'fetch_country_states',
                ]);
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
            Logger::debug('Country locations caching job completed', [
                'log_file' => config('logging.log_files.products'),
                'countries_count' => count($countries),
                'action' => 'country_locations_job_complete',
            ]);
        } catch (Exception $e) {
            Logger::error('Error in CountryLocationsJob', $e, data: [
                'log_file' => config('logging.log_files.products'),
                'action' => 'country_locations_job_error',
            ]);
            throw $e;
        }
    }
}
