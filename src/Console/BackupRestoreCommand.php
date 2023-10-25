<?php

namespace IchieBenjamin\LaraBackupManager\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use IchieBenjamin\LaraBackupManager\Facades\BackupManager;
use Illuminate\Support\Facades\Storage;

class BackupRestoreCommand extends Command
{
    protected $signature = 'backupmanager:restore';
    protected $description = 'Restores a backup already taken.';

    // other vars
    protected mixed $disk = '';
    protected string $backupPath = '';

    public function __construct()
    {
        $this->disk = config('backupmanager.backups.disk');
        $this->backupPath = config('lara-backup-manager.backups.backup_path') . DIRECTORY_SEPARATOR;

        parent::__construct();
    }

    public function handle()
    {
        $tableData = BackupManager::getBackups();

        $headers = ['Name', 'Size', 'Type', 'Date'];

        // show available backups
        $this->table($headers, $tableData);

        // ask for backup file
        $backupFilename = $this->ask('Which file would you like to restore?');

        if (!Storage::disk($this->disk)->exists($this->backupPath . $backupFilename)) {
            $this->error('Specified backup file does not exist.');
            return false;
        }

        $results = BackupManager::restoreBackups([$backupFilename]);

        foreach ($results as $result) {
            if (isset($result['file'])) {
                if ($result['file'] === true) {
                    $message = 'Files Backup Restored Successfully';

                    $this->info($message);
                    Log::info($message);
                } else {
                    $message = 'Files Restoration Failed';

                    $this->error($message);
                    Log::error($message);
                }
            } elseif (isset($result['db'])) {
                if ($result['db'] === true) {
                    $message = 'Database Backup Restored Successfully';

                    $this->info($message);
                    Log::info($message);
                } else {
                    $message = 'Database Restoration Failed';

                    $this->error($message);
                    Log::error($message);
                }
            }
        }
    }
}
