<?php

namespace App\Console\Commands;

use Eduvo\Client;
use Illuminate\Console\Command;

class SyncManageBac extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:managebac';

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
        $client = new Client(env('MANAGEBAC_TOKEN'));
        $students = $client->students->all();
        foreach ($students as $student) {
            if (!$student->archived && !property_exists($student, 'student_id')) {
                echo $student->id . ' ' . $student->first_name . ' ' . $student->last_name . PHP_EOL;
            }
        }
    }
}
