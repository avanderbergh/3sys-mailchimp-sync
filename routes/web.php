<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('test', function () {
    $errors = [];
    $completed_emails = [];
    $login = 'API2';
    $password = 'API559867';
    $url = 'https://3sys.isdedu.de/WCBSAPI.ODataApi/CurrentPupil?$select=PupilPerson&$expand=PupilPerson($select=Relationships;$expand=Relationships($select=FromPupilPersonID,ToContactPersonID,RelationshipTypeTo,Rank;$expand=ToContactPerson($select=Title,FirstNames,Surname;$expand=EmailAddresses($select=EmailAddress),TelephoneNumbers($select=TelephoneNumber))))&$filter=AcademicYearCode+eq+2016';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$login:$password");
    $result = json_decode(curl_exec($ch));
    curl_close($ch);
    foreach ($result->value as $value) {
        foreach ($value->PupilPerson->Relationships as $relationship) {
            foreach ($relationship->ToContactPerson->EmailAddresses as $emailAddress) {
                if (!in_array($emailAddress->EmailAddress, $completed_emails)) {
                    try {
                        MC::patch('lists/4256957767/members/'.md5(strtolower($emailAddress->EmailAddress)), [
                            'email_address' => $emailAddress->EmailAddress,
                            'status' => 'subscribed',
                            'merge_fields' => [
                                'FNAME' => $relationship->ToContactPerson->FirstNames,
                                'LNAME' => $relationship->ToContactPerson->Surname,
                                'TITLE' => $relationship->ToContactPerson->Title
                            ]
                        ]);
                        $completed_emails[] = $emailAddress->EmailAddress;
                    } catch (\Exception $e){
                        try{
                            MC::post('lists/4256957767/members', [
                                'email_address' => $emailAddress->EmailAddress,
                                'status' => 'subscribed',
                                'merge_fields' => [
                                    'FNAME' => $relationship->ToContactPerson->FirstNames,
                                    'LNAME' => $relationship->ToContactPerson->Surname,
                                    'TITLE' => $relationship->ToContactPerson->Title
                                ]
                            ]);
                            $completed_emails[] = $emailAddress->EmailAddress;
                        } catch (\Exception $f) {
                            $errors[] = $f->getMessage();
                        }
                    }
                }
            }
        }
    }
    return "Created and Updated Email Addresses: \r\n".var_dump($completed_emails)."\r\n Errors: \r\n".var_dump($errors);
});
