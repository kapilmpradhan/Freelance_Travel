<?php

namespace App\Jobs;

use App\Logging\Logger;
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
        Logger::debug('Processing permanent account deletion job', [
            'log_file' => config('logging.log_files.user_activity'),
            'action' => 'delete_account_permanently_start',
        ]);

        $temporarilyDeletedUsers = User::getTemporarilyDeletedUsers();
        $count = $temporarilyDeletedUsers->where('deletion_date', Carbon::now()->addDays(7)->toDateString())
                    ->update(['is_permanently_deleted' => true]);

        Logger::debug('Permanent account deletion completed', [
            'log_file' => config('logging.log_files.user_activity'),
            'deleted_count' => $count,
            'action' => 'delete_account_permanently_completed',
        ]);
    }
}
