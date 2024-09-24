<?php

namespace App\Jobs;

use App\Models\Favourites;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateFavoriteNightly implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Favourites $favourites)
    {
        Log::info("========== UPDATE NIGHTLY ============");
        $accessToken = getToken();
        Log::info("Access token: $accessToken");
        $accessToken = json_decode($accessToken);
        $token = @$accessToken->access_token;
        $listId = $favourites->get()->unique("productId")->pluck("productId");
        foreach ($listId as $id) {
            Log::info("========== PRODUCT ID: $id ============");
            dispatch(new UpdateFavoriteImage($token, $id));
        }
        Log::info("========== END UPDATE NIGHTLY ============");
    }
}
