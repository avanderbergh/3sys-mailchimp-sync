<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Wonde\Client;

class TestWonde extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wonde:test';

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
        $client = new Client(env('WONDE_KEY'));

// Loop through the schools your account has access to
        $school = $client->school('A1930499544');
        foreach ($school->students->all(['contact_details']) as $student) {
            dd($student);
        }
    }
}
