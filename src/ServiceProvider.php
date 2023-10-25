<?php
namespace IchieBenjamin\LaraBackupManager;
use IchieBenjamin\LaraBackupManager\Console\BackupCommand;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use IchieBenjamin\LaraBackupManager\Console\BackupListCommand;
use IchieBenjamin\LaraBackupManager\Console\BackupRestoreCommand;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        // routes
        if (!$this->app->routesAreCached()) {
            require __DIR__ . '/routes.php';
        }

        // views
        $this->loadViewsFrom(__DIR__ . '/Views', 'lara-backup-manager');

//         Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/Config/config.php' => config_path('lara-backup-manager.php'),
                __DIR__ . '/Migrations' => database_path('migrations')
            ]);
        }
    }


    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/config.php', 'lara-backup-manager');

        $this->app->bind('command.backupmanager.create', BackupCommand::class);
        $this->commands('command.backupmanager.create');

        $this->app->bind('command.backupmanager.list', BackupListCommand::class);
        $this->commands('command.backupmanager.list');

        $this->app->bind('command.backupmanager.restore', BackupRestoreCommand::class);
        $this->commands('command.backupmanager.restore');

        $this->app->singleton('BackupManager', function () {
            return $this->app->make(BackupManager::class);
        });
    }


    public function provides()
    {
        return ['BackupManager'];
    }
}
