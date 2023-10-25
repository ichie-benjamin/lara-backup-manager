<?php

namespace IchieBenjamin\LaraBackupManager\Http\Controllers;

use Illuminate\Mail\Message;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use IchieBenjamin\LaraBackupManager\Facades\BackupManager;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class BackupManagerApiController extends BaseController
{
    public function __construct()
    {
        $api_auth = config('lara-backup-manager.api_authentication');
        if ($api_auth) {
            $this->middleware('auth.basic');
        }
    }

    public function index()
    {
        $backups = BackupManager::getBackups();

        return response()->json($backups);

    }

}
