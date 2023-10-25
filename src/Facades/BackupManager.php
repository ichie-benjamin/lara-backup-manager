<?php
namespace IchieBenjamin\LaraBackupManager\Facades;

use Illuminate\Support\Facades\Facade;


class BackupManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'BackupManager';
    }

}
