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
        setlocale(LC_ALL, 'en_US.UTF8');

        $wcbs = resolve('App\Services\WCBSApi2');
        $response = $wcbs->getRequest('schools(1)/SubjectSets?%24expand=Pupil&%24filter=SetCode%20eq%20\'H3EH11.1\'%20and%20AcademicYear%20eq%202017');
        $result = json_decode($response->getBody()->getContents());
        $client = new Client(env('MANAGEBAC_TOKEN'));
        foreach ($result->value[0]->Pupil as $pupil) {
            echo "\r\nTrying ".$pupil->Code."\r\n";
            $response = $wcbs->getRequest('schools(1)/Pupils?%24filter=Code%20eq%20\''.$pupil->Code.'\'');
            $result = json_decode($response->getBody()->getContents());
            $response = $wcbs->getRequest('names?%24expand=Relationships(%24expand%3DTo%2CRelationshipType)&%24filter=Id%20eq%20'.$result->value[0]->NameId);
            $result = json_decode($response->getBody()->getContents());
            $relationships = $result->value[0]->Relationships;
            $students = $client->students->all();
            foreach ($students as $student) {
                if (property_exists($student, 'student_id')) {
                    if ($student->student_id == substr($pupil->Code, 2)) {
                        foreach ($relationships as $relationship) {
                            if ($relationship->RelationshipType->ToRelation == 'Mother' || $relationship->RelationshipType->ToRelation == 'Father') {
                                $parent = [
                                    'email' => $relationship->To->EmailAddress,
                                    'first_name' => $relationship->To->PreferredName,
                                    'last_name' => $relationship->To->Surname,
                                    'child_ids' => [$student->id]
                                ];
                                try {
                                    $client->parents->create($parent);
                                } catch (\Exception $e) {
                                    echo "Already Exists...\r\n";
                                }
                                echo "Created ".$relationship->To->EmailAddress."\r\n";
                            }
                        }
                    }
                }
                echo '.';
            }
        }
    }
}
