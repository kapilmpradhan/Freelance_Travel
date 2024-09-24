<?php

namespace App\Jobs;

use App\Models\Favourites;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateFavoriteImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $token;
    protected $productId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($token, $productId)
    {
        $this->token = $token;
        $this->productId = $productId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Favourites $favourites)
    {
        $favourites->updateProduct($this->productId, $this->token);
    }
}
