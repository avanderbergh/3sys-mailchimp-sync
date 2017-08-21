<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Avanderbergh\Schoology\SchoologyApi;
use Illuminate\Support\Facades\Storage;

class TestWcbsApi2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wcbs:test';

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
        $wcbs = resolve('App\Services\WCBSApi2');
        $students = [];
        $this->info('Fetching Students from WCBS');
        $response = $wcbs->getRequest('schools(1)/Pupils?$expand=Name,Form($expand=FormYear)&$filter=SubSchool eq \'SRS\' and AcademicYear eq 2017 and RecordType eq \'Current\'');
        $result = json_decode($response->getBody()->getContents());
        $students = array_merge($result->value, $students);
        $bar = $this->output->createProgressBar();
        while (property_exists($result, '@odata.nextLink')) {
            $response = $wcbs->getUrl($result->{'@odata.nextLink'});
            $result = json_decode($response->getBody()->getContents());
            $students = array_merge($result->value, $students);
            $bar->advance();
        }
        $bar->finish();
        echo PHP_EOL;
        $this->info('Creating CSV');
        //$bar = $this->output->createProgressBar(sizeof($students));
        $student_csv = 'Code,Name,Surname' . PHP_EOL;
        foreach ($students as $k => $student) {
            $student_csv .= $student->Code . ',' . $student->Name->PreferredName . ',' . $student->Name->Surname . PHP_EOL;
            echo $k . PHP_EOL;
            //$bar->advance();
        }
        //$bar->finish();
        Storage::put('api-students.csv', $student_csv);
        $schoology_key = env('CONSUMER_KEY');
        $schoology_secret = env('CONSUMER_SECRET');
        $schoology = new SchoologyApi($schoology_key,$schoology_secret,null,null,null, true);
        $this->info('Creating/Updating Students in Schoology');
        $bar = $this->output->createProgressBar(count($students));
        foreach ($students as $student) {
            $body = [
                'users' => [
                    'user' => [
                        'school_uid' => $student->Code,
                        'name_first' => $student->Name->PreferredName,
                        'name_last' => $student->Name->Surname[0] . ' ' . $student->Form->Description,
                        'primary_email' => $student->Name->EmailAddress,
                        'role_id' => 266721
                    ]
                ]
            ];
            try {
                $result = $schoology->apiResult('users?update_existing=1', 'POST', $body);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $bar->advance();
        }
        $bar->finish();
        $this->info('Done!');
    }
}