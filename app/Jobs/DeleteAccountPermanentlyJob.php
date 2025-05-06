<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Carbon\Carbon;

class DeleteAccountPermanentlyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $temporarilyDeletedUsers = User::getTemporarilyDeletedUsers();
        $temporarilyDeletedUsers->where('deletion_date', Carbon::now()->addDays(7)->toDateString())
                    ->update(['is_permanently_deleted' => true]);
    }
}
