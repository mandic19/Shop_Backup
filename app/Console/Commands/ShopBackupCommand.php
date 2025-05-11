<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopBackupService;

class ShopBackupCommand extends Command
{
    protected $signature = 'shop:backup';

    protected $description = 'Perform a backup of shop data';

    public function __construct(
        protected ShopBackupService $backupService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting shop backup...');

        $startTime = microtime(true);

        try {
            $success = $this->backupService->run();

            if ($success) {
                $duration = round(microtime(true) - $startTime, 2);
                $this->info("Shop backup completed successfully in {$duration} seconds.");

                return Command::SUCCESS;
            } else {
                $this->error('Shop backup failed.');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Backup failed with error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
