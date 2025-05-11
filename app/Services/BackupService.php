<?php

namespace App\Services;

use App\Support\BlueprintMacros;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

abstract class BackupService
{
    /**
     * Tables to be backed up in hierarchical order (parent → child)
     * Should be defined in child classes
     */
    protected array $tableMacrosMap = [];

    protected string $timestamp;

    public function __construct()
    {
        $this->timestamp = Carbon::now()->format('Ymd_His');
    }

    public function run(): bool
    {
        try {
            $startTime = microtime(true);
            Log::info('Starting backup process for ' . $this->getBackupName());

            $this->dropTemporaryTables();

            $this->createTemporaryTables();

            $this->executeBackup();

            $this->swapTables();

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Backup completed successfully', [
                'backup_type' => $this->getBackupName(),
                'duration_seconds' => $duration
            ]);

            return true;
        } catch (Exception $exception) {
            Log::error('Shop backup failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);

            // Try to clean up temporary tables to avoid leaving the database in an inconsistent state
            try {
                $this->dropTemporaryTables();
            } catch (Exception $cleanupException) {
                Log::error('Failed to clean up temporary tables after backup failure', [
                    'error' => $cleanupException->getMessage()
                ]);
            }

            return false;
        }
    }

    /**
     * Get the name of this backup for logging purposes
     */
    abstract protected function getBackupName(): string;

    /**
     * Execute specific backup operations
     * Should be implemented by child classes
     */
    abstract protected function executeBackup(): void;


    protected function getTemporaryTableName(string $tableName): string
    {
        return "{$tableName}_temp";
    }

    protected function getSnapshotTableName(string $tableName): string
    {
        return "{$tableName}_snap_{$this->timestamp}";
    }

    protected function createTemporaryTables(): void
    {
        Log::info('Creating temporary tables');

        foreach ($this->tableMacrosMap as $table => $map) {
            $temporaryTableName = $this->getTemporaryTableName($table);

            Log::debug('Creating temporary table', ['table' => $temporaryTableName]);

            Schema::create($temporaryTableName, function (Blueprint|BlueprintMacros $table) use ($temporaryTableName, $map) {
                $fieldsMacroCallback = $map['fields']['macro'];

                $table->{$fieldsMacroCallback}();

                if (!isset($map['fk'])) {
                    return;
                }

                $fkMacroCallback = $map['fk']['macro'];
                $fkKeys = $map['fk']['keys'];

                foreach ($fkKeys as $fkKey) {
                    $columns = $fkKey['columns'];
                    $refTable = $fkKey['references'];
                    $tempRefTable = $this->getTemporaryTableName($refTable);
                    $fk = $temporaryTableName . "_" . implode('_', $columns) . "_foreign_" . $this->timestamp;

                    $table->{$fkMacroCallback}($columns, $tempRefTable, $fk);
                }
            });
        }

        Log::info('Temporary tables created successfully');
    }

    protected function swapTables(): void
    {
        Log::info('Swapping temporary tables with original ones');

        // Build dynamic rename query
        $renameTableMap = [];

        foreach (array_keys($this->tableMacrosMap) as $table) {
            $renameTableMap[] = "{$table} TO {$this->getSnapshotTableName($table)}";
            $renameTableMap[] = "{$this->getTemporaryTableName($table) } TO {$table}";
        }

        DB::statement("RENAME TABLE " . implode(', ', $renameTableMap));

        $this->dropSnapshotTables();
    }

    protected function dropTemporaryTables(): void
    {
        Log::info('Dropping temporary tables.');
        $pattern = $this->getTemporaryTableName('%s');
        $this->dropTablesWithPattern($pattern);
    }

    protected function dropSnapshotTables(): void
    {
        Log::info('Dropping snapshot tables.');
        $pattern = $this->getSnapshotTableName('%s');
        $this->dropTablesWithPattern($pattern);
    }

    protected function dropTablesWithPattern(string $tableNamePattern): void
    {
        // Reverse hierarchy for dropping (child → parent)
        foreach (array_reverse(array_keys($this->tableMacrosMap)) as $table) {
            $targetTable = sprintf($tableNamePattern, $table);

            if (Schema::hasTable($targetTable)) {
                Log::debug("Dropping table {$targetTable}");

                Schema::drop($targetTable);
            }
        }
    }
}
