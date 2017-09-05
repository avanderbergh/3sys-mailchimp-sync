<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\SyncList::class,
        Commands\SyncAsm::class,
        Commands\TestWonde::class,
        Commands\TestWcbsApi2::class,
        Commands\SyncSchoology::class,
        Commands\SyncManageBac::class,
        Commands\SyncDaysSchoology::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('list:sync c927498c0a 2017')->daily();
        $schedule->command('sync:schoology')->twiceDaily();
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
