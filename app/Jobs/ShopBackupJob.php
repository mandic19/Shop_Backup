<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ShopBackupService;
use Illuminate\Support\Facades\Log;

class ShopBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600; // 1 hour

    public function handle(ShopBackupService $backupService): void
    {
        Log::info('Starting shop backup job...');
        $startTime = microtime(true);

        try {
            $success = $backupService->run();

            if ($success) {
                $duration = round(microtime(true) - $startTime, 2);
                Log::info("Shop backup completed successfully in {$duration} seconds.");
            } else {
                Log::error('Shop backup failed.');
                $this->fail('Backup operation returned false');
            }
        } catch (\Exception $e) {
            Log::error('Backup job failed with error: ' . $e->getMessage());
            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception = null): void
    {
        Log::error('Shop backup job failed finally', [
            'exception' => $exception ? $exception->getMessage() : 'Unknown error'
        ]);
    }
}
