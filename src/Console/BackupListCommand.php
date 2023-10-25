<?php

namespace IchieBenjamin\LaraBackupManager\Console;

use Illuminate\Console\Command;
use IchieBenjamin\LaraBackupManager\Facades\BackupManager;

class BackupListCommand extends Command
{
    protected $signature = 'backupmanager:list';
    protected $description = 'Shows list of backups taken.';

    public function handle()
    {
        $tableData = BackupManager::getBackups();

        $headers = ['Name', 'Size', 'Type', 'Date'];

        $this->table($headers, $tableData);
    }
}
