<?php

namespace IchieBenjamin\LaraBackupManager;

use App;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BackupManager
{
    protected mixed $disk = '';
    protected string $backupPath;
    protected string $backupSuffix;
    protected string $fileBackupName;
    protected string $dbBackupName;
    protected string $fileVerifyName = 'lara-backup-verify';

    /**
     * BackupManager constructor.
     */
    public function __construct()
    {
        $this->disk = config('lara-backup-manager.backups.disk');
        $this->backupPath = config('lara-backup-manager.backups.backup_path') . DIRECTORY_SEPARATOR;
        $this->backupSuffix = date(strtolower(config('lara-backup-manager.backups.backup_file_date_suffix')));
        $this->fileBackupName = "file_$this->disk-$this->backupSuffix.tar";
        $this->dbBackupName = "db_$this->disk.'_'.$this->backupSuffix.gz";

        $this->mysql = config('lara-backup-manager.paths.mysql', 'mysql');
        $this->mysqldump = config('lara-backup-manager.paths.mysqldump', 'mysqldump');
        $this->tar = config('lara-backup-manager.paths.tar', 'tar');
        $this->zcat = config('lara-backup-manager.paths.zcat', 'zcat');

        if($this->disk != 'custom-http'){
            Storage::disk($this->disk)->makeDirectory($this->backupPath);
        }
    }

    /**
     * Gets list of backups
     */

    public function getBackups()
    {
        try {
            if($this->disk == 'custom-http'){
                return [];
            }

            $files = Storage::disk($this->disk)->listContents($this->backupPath);
            $filesData = [];

            foreach ($files as $index => $file) {
                if ($file instanceof FileAttributes) {
                    $extraMetadata = $file->extraMetadata();
                    if (isset($extraMetadata['filename']) && isset($extraMetadata['extension'])) {
                        $name = $extraMetadata['filename'] . "." . $extraMetadata['extension'];
                    } else {
                        $pathInfo = pathinfo($file['path']);
                        $name = $pathInfo['filename'] . "." . $pathInfo['extension'];
                    }
                } else {
                    $pathInfo = pathinfo($file['path']);
                    $name = $pathInfo['filename'] . "." . $pathInfo['extension'];
                }

                $array = explode('_', $name);
                $filesData[] = [
                    'name' => $name,
                    'size_raw' => $file instanceof FileAttributes ? $file->fileSize() : $file['size'],
                    'size' => $this->formatSizeUnits($file instanceof FileAttributes ? $file->fileSize() : $file['size']),
                    'type' => $array[0] === 'db' ? 'Database' : 'Files',
                    'date' => date('M d Y', $this->getFileTimeStamp($file)),
                ];
            }

            // Sort by date
            $filesData = collect($filesData)->sortByDesc(function ($temp, $key) {
                return Carbon::parse($temp['date'])->getTimestamp();
            })->all();

            return array_values($filesData);
        } catch (\Exception $e) {
            // Handle the exception, log it, or return an error response as needed.
            // For example:
            // Log::error('An exception occurred: ' . $e->getMessage());
            // return ['error' => 'An error occurred.'];
            throw $e; // Rethrow the exception for further handling at a higher level.
        }
    }

    public function createBackup($type = null): array
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        if(!$type){
            $this->backupFiles();
            $this->backupDatabase();
        }else{
            if($type == 'file'){
                $this->backupFiles();
            }
            if($type == 'file'){
                $this->backupDatabase();
            }
        }
        $this->deleteOldBackups();
        return $this->getBackupStatus($type);
    }

    public function restoreBackups(array $files): array
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $restoreStatus = [];

        foreach ($files as $file) {
            $parts = explode('_', $file);

            if(isset($parts[0])){
                $isFiles = $parts[0] === 'file';

                if ($isFiles) {
                    $this->restoreFiles($file);
                } else {
                    $this->restoreDatabase($file);
                }

                $restoreStatus[] = $this->getRestoreStatus($isFiles);
            }

        }

        return $restoreStatus;
    }

    public function deleteBackups(array $files): bool
    {
        $status = false;

        foreach ($files as $file) {
            $status = Storage::disk($this->disk)->delete($this->backupPath . $file);
        }

        return $status;
    }

    public function backupFiles($bypass=false)
    {
        if (config('lara-backup-manager.backups.files.enable') || $bypass===true) {

            // delete previous backup of same date
            if($this->disk != 'custom-http'){
                if (Storage::disk($this->disk)->exists($this->backupPath . $this->fileBackupName)) {
                    Storage::disk($this->disk)->delete($this->backupPath . $this->fileBackupName);
                }
            }


            file_put_contents(base_path($this->fileVerifyName), 'backup');

            $itemsToBackup = config('lara-backup-manager.backups.files.folders');

            $itemsToBackup = array_map(
                function ($str) {
                    $pathPrefix = dirname(getcwd());

                    if (App::runningInConsole()) {
                        $pathPrefix = getcwd();
                    }

                    return str_replace(array($pathPrefix, '/', '\\'), '', $str);
                },
                $itemsToBackup
            );

            // also add our backup verifier
            $itemsToBackup[] = $this->fileVerifyName;

            $itemsToBackup = implode(' ', $itemsToBackup);

            $command = 'cd ' . str_replace('\\', '/',
                    base_path()) . " && $this->tar -cpzf $this->fileBackupName $itemsToBackup";
            //exit($command);

            shell_exec($command . ' 2>&1');

            if (file_exists(base_path($this->fileBackupName))) {
                $storageLocal = Storage::createLocalDriver(['root' => base_path()]);
                $file = $storageLocal->get($this->fileBackupName);

                if($this->disk == 'custom-http'){
                    $fileContent = base_path($this->fileBackupName);
                   $http_status = $this->sendFileToHttp($fileContent, $this->fileBackupName);

                    if(!$http_status){
                        Log::error('Http failed, reverted to local');
                        $fallback_disk = config('lara-backup-manager.backups.custom_http.fallback_disk');
                        if($fallback_disk){
                            Storage::disk($fallback_disk)->put($this->backupPath . $this->fileBackupName, $file);
                        }
                    }
                }else{
                    Storage::disk($this->disk)->put($this->backupPath . $this->fileBackupName, $file);
                }

                // delete local file
                $storageLocal->delete($this->fileBackupName);

                if($bypass){
                    return $this->getBackupStatus('file');
                }

            }

            if ($bypass===true) {
                $this->deleteOldBackups("file");
            }
        }
    }

    /**
     * Backup Database
     */

    public function sendFileToHttp($file, $filename)
    {
        try {

            $apiKey = config('lara-backup-manager.backups.custom_http.api_key');

            $response = Http::attach('attachment', file_get_contents($file), $filename)
                ->post(config('lara-backup-manager.backups.custom_http.url'), [
                    'name' => $filename,
                    'api_key' => $apiKey,
                ]);

            if ($response->successful()) {
                return true; // Success
            } else {
                return false;
//                return $response;
            }
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());
            return false;
        }
    }
    public function backupDatabase($bypass=false)
    {
        if (config('lara-backup-manager.backups.database.enable') || $bypass) {

            // delete previous backup for same date
            if (Storage::disk($this->disk)->exists($this->backupPath . $this->dbBackupName)) {
                Storage::disk($this->disk)->delete($this->backupPath . $this->dbBackupName);
            }

            # this will be used to verify later if restore was successful
            DB::statement(" INSERT INTO verifybackup (id, verify_status) VALUES (1, 'backup') ON DUPLICATE KEY UPDATE verify_status = 'backup' ");

            $connection = [
                'host' => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
                'username' => config('database.connections.mysql.username'),
                'password' => config('database.connections.mysql.password'),
            ];

            $tableOptions = '';
            $connectionOptions = "--user={$connection['username']} --password=\"{$connection['password']}\" --host={$connection['host']} {$connection['database']} ";

            $options = [
                '--single-transaction',
                '--max-allowed-packet=4096',
                '--quick',
                // '--force', // ignore errors
                //'--set-gtid-purged=OFF',
                //'--skip-lock-tables',
            ];

            $options = implode(' ', $options);

            $itemsToBackup = config('lara-backup-manager.backups.database.tables');

            if ($itemsToBackup) {

                // also add our backup verifier
                $itemsToBackup[] = 'verifybackup';

                $tableOptions = implode(' ', $itemsToBackup);
            }

            $command = 'cd ' . str_replace('\\', '/',
                    base_path()) . " && $this->mysqldump $options $connectionOptions $tableOptions | gzip > $this->dbBackupName";
            //exit($command);

            shell_exec($command . ' 2>&1');

            if (file_exists(base_path($this->dbBackupName))) {
                $storageLocal = Storage::createLocalDriver(['root' => base_path()]);
                $file = $storageLocal->get($this->dbBackupName);

                Storage::disk($this->disk)->put($this->backupPath . $this->dbBackupName, $file);

                // delete local file
                $storageLocal->delete($this->dbBackupName);

            }

            if ($bypass===true) {
                $this->deleteOldBackups("db");


                return $this->getBackupStatus('db');

            }
        }
    }

    protected function restoreFiles($file): void
    {
        if (Storage::disk($this->disk)->exists($this->backupPath . $file)) {

            $storageLocal = Storage::createLocalDriver(['root' => base_path()]);
            $contents = Storage::disk($this->disk)->get($this->backupPath . $file);

            $storageLocal->put($file, $contents);

            if (file_exists(base_path($file))) {

                file_put_contents(base_path($this->fileVerifyName), 'restore');

                $command = 'cd ' . str_replace('\\', '/', base_path()) . " && $this->tar -xzf $file";
                //exit($command);

                shell_exec($command . ' 2>&1');

                // delete local file
                $storageLocal->delete($file);
            }

        }
    }

    protected function restoreDatabase($file): void
    {
        if (Storage::disk($this->disk)->exists($this->backupPath . $file)) {

            $storageLocal = Storage::createLocalDriver(['root' => base_path()]);
            $contents = Storage::disk($this->disk)->get($this->backupPath . $file);

            $storageLocal->put($file, $contents);

            if (file_exists(base_path($file))) {

                DB::statement(" INSERT INTO verifybackup (id, verify_status) VALUES (1, 'restore') ON DUPLICATE KEY UPDATE verify_status = 'restore' ");

                $connection = [
                    'host' => config('database.connections.mysql.host'),
                    'database' => config('database.connections.mysql.database'),
                    'username' => config('database.connections.mysql.username'),
                    'password' => config('database.connections.mysql.password'),
                ];

                $connectionOptions = "-u {$connection['username']} ";

                if (trim($connection['password'])) {
                    $connectionOptions .= " -p\"{$connection['password']}\" ";
                }

                $connectionOptions .= " -h {$connection['host']} {$connection['database']} ";

                //$command = "$cd gunzip < $this->fBackupName | mysql $connectionOptions";
                $command = 'cd ' . str_replace('\\', '/',
                        base_path()) . " && $this->zcat $file | mysql $connectionOptions";
                //exit($command);

                shell_exec($command . ' 2>&1');

                // delete local file
                $storageLocal->delete($file);
            }

        }
    }

    protected function getBackupStatus($type = null): array
    {
        @unlink(base_path($this->fileVerifyName));

        $okSizeBytes = 1024;

        $config = config('lara-backup-manager.backups');

        $fStatus = $this->isBackupEnabled('files', $config) &&
            $this->isBackupValid($this->fileBackupName, $this->disk, $this->backupPath, $okSizeBytes);

        $dStatus = $this->isBackupEnabled('database', $config) &&
            $this->isBackupValid($this->dbBackupName, $this->disk, $this->backupPath, $okSizeBytes);

        if ($type === 'file') {
            $dStatus = false;
        } elseif ($type === 'db') {
            $fStatus = false;
        }

        return ['file' => $fStatus, 'db' => $dStatus];
    }

    private function isBackupEnabled($type, $config): bool
    {
        return $config[$type]['enable'] ?? false;
    }

    private function isBackupValid($backupName, $disk, $path, $okSizeBytes): bool
    {
        return Storage::disk($disk)->exists($path . $backupName) &&
            Storage::disk($disk)->size($path . $backupName) > $okSizeBytes;
    }
    protected function getRestoreStatus($isFiles): array
    {
        // for files
        if ($isFiles) {
            $contents = file_get_contents(base_path($this->fileVerifyName));

            @unlink(base_path($this->fileVerifyName));

            return ['file' => $contents === 'backup'];
        }

        // for db
        $dbStatus = false;
        $data = DB::select(' SELECT verify_status FROM verifybackup WHERE id = 1 ');

        if ($data && isset($data[0])) {
            $dbStatus = $data[0]->verify_status;
        }

        return ['db' => $dbStatus === 'backup'];
    }

    protected function deleteOldBackups($del_specific="")
    {

        if($this->disk == 'custom-http'){
            return;
        }
        $daysOldToDelete = (int)config('lara-backup-manager.backups.delete_old_backup_days');
        $now = time();

        $files = Storage::disk($this->disk)->listContents($this->backupPath);
        foreach ($files as $file) {
            if ($file['type'] !== 'file') {
                continue;
            }
            if (empty($file['basename'])) {
                $filename = $file->path();
            }else{
                $filename = $this->backupPath . $file['basename'];
            }
            if ($del_specific!=="") {
                //skip delete if del_specific has value for specific deletes only
                if (!empty($file['basename'][0]) && $file['basename'][0] !== $del_specific) {
                    continue;
                }
                if (!empty($file->extraMetadata()['filename'][0]) && $file->extraMetadata()['filename'][0].".".$file->extraMetadata()['extension'][0] !== $del_specific) {
                    continue;
                }
            }
            if ($now - $this->getFileTimeStamp($file) >= 60 * 60 * 24 * $daysOldToDelete) {
                if (Storage::disk($this->disk)->exists($filename)) {
                    Storage::disk($this->disk)->delete($filename);
                    $name = str_replace($this->backupPath,"",$filename);
                    Log::info('Deleted old backup file: ' . $name);
                }
            }
        }
    }

    protected function getFileTimeStamp($file)
    {
        if ($file instanceof \League\Flysystem\FileAttributes) {
            return $file->lastModified();
        }else{
            if (isset($file['timestamp'])) {
                return $file['timestamp'];
            }
            // otherwise get date from file name
            $array = explode('_', $file['filename']);

            return strtotime(end($array));
        }
    }

    protected function formatSizeUnits($size)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;

        return number_format($size / (1024 ** $power), 2, '.', ',') . ' ' . $units[$power];
    }
}
