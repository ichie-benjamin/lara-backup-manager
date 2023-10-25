<?php

Route::group(['middleware' => 'web','namespace' => 'IchieBenjamin\LaraBackupManager\Http\Controllers',
        'prefix' => config('lara-backup-manager.route', 'lara-backup-manager')
    ],
    function () {
        // list backups
        Route::get('/', 'BackupManagerController@index')->name('lara-backup-manager');

        // create backups
        Route::post('create', 'BackupManagerController@createBackup')->name('lara-backup-manager.create');

        // restore/delete backups
        Route::post('restore_delete',
            'BackupManagerController@restoreOrDeleteBackups')->name('lara-backup-manager.restore_delete');

        // download backup
        Route::get('download/{file}', 'BackupManagerController@download')->name('lara-backup-manager.download');


        Route::group(['prefix' => 'api', 'as' => 'lara-backup-manager.api.'], function () {
            Route::get('download/{file}', 'BackupManagerController@download')->name('download');
            Route::get('/', 'BackupManagerApiController@index')->name('all');
        });

});
