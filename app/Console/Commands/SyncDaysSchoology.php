<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncDaysSchoology extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:schoology-days';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $hostdb = 'winbox04.isdedu.de';
        $dbname = 'PassMain';
        $usr = env('WCBS_API_LOGIN');
        $psw = env('WCBS_API_PASSWORD');
        $db = new \PDO("dblib:host=$hostdb;dbname=$dbname", $usr, $psw);
        $sql="SELECT DayName, CalendarDate, TimetableId FROM IS_AC_PROJECTED_TIMETABLE WHERE CalendarDate>='2017-08-01 00:00:00.000' AND TimetableId='1900681585'";
        $stmt=$db->prepare($sql);
        $stmt->execute();
        $days=$stmt->fetchAll(PDO::FETCH_ASSOC);
        dd($days);
    }
}
