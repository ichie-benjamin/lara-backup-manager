<?php

namespace IchieBenjamin\LaraBackupManager\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;

use Illuminate\Mail\Message;

use Illuminate\Support\Facades\Log;
use IchieBenjamin\LaraBackupManager\Facades\BackupManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class BackupManagerController extends BaseController
{
    public function __construct()
    {
//        if (config('lara-backup-manager.http_authentication')) {
//            $this->middleware('auth.basic');
//        }
    }

    public function index()
    {
        $title = 'Available backups';

        $backups = BackupManager::getBackups();

        return view('lara-backup-manager::index', compact('title', 'backups'));
    }

    public function createBackup(Request $request)
    {
        $type = $request->get('type');
        if(!in_array($type,['file','db'])){
           $type = null;
        }
        $message = '';
        $mailBody = '';
        $messages = [];

        // create backups
        $result = BackupManager::createBackup($type);

        // set status messages
        if ($result['file'] === true) {
            $message = 'Files Backup Taken Successfully';

            $messages[] = [
                'type' => 'success',
                'message' => $message
            ];

            Session::flash('success', $message);

            Log::info($message);
        } else {
            if (config('lara-backup-manager.backups.files.enable')) {
                $message = 'Files Backup Failed';

                $messages[] = [
                    'type' => 'danger',
                    'message' => $message
                ];

                Log::error($message);
            }
        }

        $mailBody .= $message;

        if ($result['db'] === true) {
            $message = 'Database Backup Taken Successfully';

            $messages[] = [
                'type' => 'success',
                'message' => $message
            ];

            Session::flash('success', $message);

            Log::info($message);
        } else {
            if (config('lara-backup-manager.backups.database.enable')) {
                $message = 'Database Backup Failed';

                $messages[] = [
                    'type' => 'danger',
                    'message' => $message
                ];

                Log::error($message);
            }
        }

        $mailBody .= '<br>' . $message;

        $this->sendMail($mailBody);

        Session::flash('messages', $messages);

        return redirect()->back();
    }


    public function restoreOrDeleteBackups()
    {
        $mailBody = '';
        $messages = [];
        $backups = request()->backups;
        $type = request()->type;

        if ($type === 'restore' && count($backups) > 1) {
            $messages[] = [
                'type' => 'danger',
                'message' => 'Max of two backups can be restored at a time.'
            ];
        }

        if ($type === 'restore') {
            // restore backups

            $results = BackupManager::restoreBackups($backups);


            // set status messages
            foreach ($results as $result) {

                if (isset($result['file'])) {
                    if ($result['file'] === true) {
                        $message = 'Files Backup Restored Successfully';

                        $messages[] = [
                            'type' => 'success',
                            'message' => $message
                        ];

                        Log::info($message);
                    } else {
                        $message = 'Files Restoration Failed';

                        $messages[] = [
                            'type' => 'danger',
                            'message' => $message
                        ];

                        Log::error($message);
                    }

                    $mailBody .= $message;

                } elseif (isset($result['db'])) {
                    if ($result['db'] === true) {
                        $message = 'Database Backup Restored Successfully';

                        $messages[] = [
                            'type' => 'success',
                            'message' => $message
                        ];

                        Log::info($message);
                    } else {
                        $message = 'Database Restoration Failed';

                        $messages[] = [
                            'type' => 'danger',
                            'message' => $message
                        ];

                        Log::error($message);
                    }

                    $mailBody .= '<br>' . $message;
                }
            }

            $this->sendMail($mailBody);

        } else {
            // delete backups

            $results = BackupManager::deleteBackups($backups);

            if ($results) {
                $messages[] = [
                    'type' => 'success',
                    'message' => 'Backup(s) deleted successfully.'
                ];
            } else {
                $messages[] = [
                    'type' => 'danger',
                    'message' => 'Deletion failed.'
                ];
            }
        }

        Session::flash('messages', $messages);

        return redirect()->back();
    }

    public function download($file)
    {
        $backupPath = config('lara-backup-manager.backups.backup_path');
        $diskName = config('lara-backup-manager.backups.disk');

        $filePath = $backupPath . DIRECTORY_SEPARATOR . $file;

        if (Storage::disk($diskName)->exists($filePath)) {
            // File exists, you can now download it
            return response()->download(Storage::disk($diskName)->path($filePath));
        } else {
            // File doesn't exist, handle the scenario accordingly
            return response()->json(['message' => 'File not found'], 404);
        }
    }

    protected function sendMail($body)
    {
        try {

            $emails = config('lara-backup-manager.mail.mail_receivers', []);

            if ($emails) {
                foreach ($emails as $email) {
                    \Mail::send([], [], static function (Message $message) use ($body, $email) {
                        $message
                            ->subject(config('lara-backup-manager.mail.mail_subject', 'BackupManager Alert'))
                            ->to($email)
                            ->text($body);
                    });
                }
            }
        } catch (\Exception $e) {
            Log::error('BackupManager Email Sending Failed: ' . $e->getMessage());
        }
    }
}
