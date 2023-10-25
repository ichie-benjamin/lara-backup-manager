<?php

namespace IchieBenjamin\LaraBackupManager\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use IchieBenjamin\LaraBackupManager\Facades\BackupManager;

class BackupCommand extends Command
{

    //added option for --only=files, --only=db
    protected $signature = 'backupmanager:create {--only=}';
    protected $description = 'Creates backup of files and/or database.';

    public function handle()
    {
        $argument = $this->option('only');
        if ($argument!==null && !in_array($argument,['db','files']) ) {
            $this->info('You can only select "files" or "db" argument!');
            return;
        }
        if ($argument===null) {
            $result = BackupManager::createBackup();
        }elseif($argument==='files'){
            $result = BackupManager::backupFiles(true);
        }else{
            $result = BackupManager::backupDatabase(true);
        }

        // set status messages
        if (isset($result['file']) && $result['file'] === true) {
            $message = 'Files Backup Created Successfully';
            Log::info($message);
            $this->info($message);
        } elseif(isset($result['file']) && $result['file'] === false) {
            if (config('lara-backup-manager.backups.files.enable')) {
                $message = 'Files Backup Failed';
                $this->error($message);
                Log::error($message);
            }
        }

        if (isset($result['db']) && $result['db'] === true) {
            $message = 'Database Backup Created Successfully';
//            $this->info($message);
            Log::info($message);
        } elseif(isset($result['db']) && $result['db'] === false) {
            if (config('lara-backup-manager.backups.database.enable')) {
                $message = 'Database Backup Failed';
                Log::error($message);
                $this->error($message);
            }
        }
    }

}
