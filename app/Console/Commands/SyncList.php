<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Mailchimp\MailchimpFacade as MC;

class SyncList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'list:sync {listID} {year}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the email address list from 3Sys';

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
        $listID = $this->argument('listID'); // the list ID of the list in MailChimp
        $year = $this->argument('year');    // the academic year
        dd([$listID, $year]);
        $completed_emails = [];
        $wcbs = resolve('App\Services\WCBSApi');
        $url = 'https://3sys.isdedu.de/WCBSAPI.ODataApi/CurrentPupil?$select=PupilPerson&$expand=PupilPerson($select=Relationships;$expand=Relationships($select=FromPupilPersonID,ToContactPersonID,RelationshipTypeTo,Rank;$expand=ToContactPerson($select=Title,FirstNames,Surname;$expand=EmailAddresses($select=EmailAddress,EmailAddressTypeDescription))))&$filter=AcademicYearCode+eq+'.$year.'+and+InUse+eq+true';
        $result = $wcbs->request($url); // Fetch the data from 3Sys through the API
        $this->info('Syncing List');
        foreach ($result->value as $value) {
            foreach ($value->PupilPerson->Relationships as $relationship) {
                if ($relationship->Rank <= 3){
                    foreach ($relationship->ToContactPerson->EmailAddresses as $emailAddress) {
//                  Check if this address has already been updated in this run
                        if ($emailAddress->EmailAddressTypeDescription == 'Main' && !in_array($emailAddress->EmailAddress,$completed_emails)) {
                            try {
//                            First try to update an existing address, if this fails a new address will be created.
                                $this->info('Attempting to update '.$emailAddress->EmailAddress);
                                MC::patch('lists/'.$listID.'/members/'.md5(strtolower($emailAddress->EmailAddress)), [
                                    'email_address' => $emailAddress->EmailAddress,
                                    'merge_fields' => [
                                        'FNAME' => $relationship->ToContactPerson->FirstNames,
                                        'LNAME' => $relationship->ToContactPerson->Surname,
                                        'TITLE' => $relationship->ToContactPerson->Title
                                    ]
                                ]);
                                $this->info('Updated '.$emailAddress->EmailAddress);
                                $completed_emails[] = $emailAddress->EmailAddress;
                            } catch (\Exception $e){
                                try{
//                                Create a new member
                                    $this->info('Attempting to create '.$emailAddress->EmailAddress);
                                    MC::post('lists/'.$listID.'/members', [
                                        'email_address' => $emailAddress->EmailAddress,
                                        'status' => 'subscribed',
                                        'merge_fields' => [
                                            'FNAME' => $relationship->ToContactPerson->FirstNames,
                                            'LNAME' => $relationship->ToContactPerson->Surname,
                                            'TITLE' => $relationship->ToContactPerson->Title
                                        ]
                                    ]);
                                    $this->info('Created '.$emailAddress->EmailAddress);
                                    $completed_emails[] = $emailAddress->EmailAddress;
                                } catch (\Exception $f) {
//                                Creating has failed for some reason, show an error message
                                    $this->error($f->getMessage());
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
