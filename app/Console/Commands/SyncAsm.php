<?php

namespace App\Console\Commands;

use Chumper\Zipper\Zipper;
use Illuminate\Console\Command;
use Storage;
use ZanySoft\Zip\Zip;

class SyncAsm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:asm {year}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate CSV files for ASM to import';

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
        $exclude_subject_sets = ['1942521949', '1942521800', '1942521591', '1942521883', '1942521626', '1942521388', '1942521625'];

        $year = $this->argument('year');    // the academic year
        $wcbs = resolve('App\Services\WCBSApi');
        $url = 'https://3sys.isdedu.de/WCBSAPI.ODataApi/Subject?$select=ID,Code,Description,SubjectSets&$expand=SubjectSets($select=ID,Code,SectionCode,SectionID,Description,Tutor;$expand=Tutor($select=TutorID,TutorCode);$filter=AcademicYearCode+eq+'.$year.'+and+SchoolCode+eq+\'IS\'+and+SectionID+ne+null)&$filter=InUse+eq+true+and+SchoolCode+eq+\'IS\'';
        $result = $wcbs->request($url);     // Fetch the data from 3Sys through the API
        $courses_csv_content = "course_id,course_number,course_name,location_id\r\n";
        $classes_csv_content = "class_id,class_number,course_id,instructor_id,instructor_id_2,instructor_id_3,location_id\r\n";

        foreach($result->value as $subject) {
            if ($subject->SubjectSets) {
                $courses_csv_content .= $subject->ID.','.
                                        $subject->Code.','.
                                        $subject->Description.','.
                                        $subject->SubjectSets[0]->SectionID."\r\n";
                foreach($subject->SubjectSets as $subject_set) {
                    if (!in_array($subject_set->ID, $exclude_subject_sets)) {
                        $classes_csv_content .= $subject_set->ID.','.
                                                $subject_set->Code.','.
                                                $subject->ID.',';
                        for($i = 0; $i <= 2; $i++){
                            if (count($subject_set->Tutor) > $i) {
                                $classes_csv_content .= $subject_set->Tutor[$i]->TutorID.',';
                            } else {
                                $classes_csv_content .= ',';
                            }
                        }
                        $classes_csv_content .= $subject_set->SectionID."\r\n";
                    }
                }
            }
        }
        $url = 'https://3sys.isdedu.de/WCBSAPI.ODataApi/CurrentPupil?$select=ID,Code,SubjectSets,Form,PupilPerson&$expand=SubjectSets($select=ID,SubjectSetID,SubjectSetCode),Form($select=FormYearCode,SectionCode,SectionID),PupilPerson($select=Surname,PreferredName,EmailAddresses;$expand=EmailAddresses($select=EmailAddress))&$filter=InUse+eq+true+and+AcademicYearCode+eq+'.$year.'+and+SchoolCode+eq+\'IS\'';
        $result = $wcbs->request($url);     // Fetch the data from 3Sys through the API
        $student_csv_result = "person_id,person_number,first_name,middle_name,last_name,grade_level,email_address,sis_username,password_policy,location_id\r\n";
        $roster_csv_result = "roster_id,class_id,student_id\r\n";
        foreach($result->value as $student) {
            if (!empty($student->PupilPerson->EmailAddresses)){
                $student_csv_result .=  $student->ID.','.
                    sprintf("%d",$student->Code).','.
                    $student->PupilPerson->PreferredName.',,'.
                    $student->PupilPerson->Surname.','.
                    $student->Form->FormYearCode.','.
                    $student->PupilPerson->EmailAddresses[0]->EmailAddress.',,,'.
                    $student->Form->SectionID."\r\n";
                foreach($student->SubjectSets as $roster) {
                    if (!in_array($roster->SubjectSetID,$exclude_subject_sets)){
                        $roster_csv_result .=   $roster->ID.','.
                            $roster->SubjectSetID.','.
                            $student->ID."\r\n";
                    }
                }
            } else {
                $this->error('No email Address for ' . $student->PupilPerson->PreferredName . ' '. $student->PupilPerson->Surname);
            }
        }

        $url = 'https://3sys.isdedu.de/WCBSAPI.ODataApi/Staff?$select=ID,Code,SectionCode,SectionID,StaffPerson&$expand=StaffPerson($select=Surname,PreferredName,EmailAddresses;$expand=EmailAddresses($select=EmailAddress;$filter=EmailAddressTypeDescription+eq+\'Internal+Staff+email\'))&$filter=InUse+eq+true+and+SchoolCode+eq+\'IS\'+and+CategoryCode+eq+\'TEA001\'+and+SectionCode+ne+null';
        $result = $wcbs->request($url);     // Fetch the data from 3Sys through the API
        $staff_csv_result = "person_id,person_number,first_name,middle_name,last_name,email_address,sis_username,location_id\r\n";
        foreach($result->value as $staff) {
            $staff_csv_result .=    $staff->ID.','.
                                    $staff->Code.','.
                                    $staff->StaffPerson->PreferredName.',,'.
                                    $staff->StaffPerson->Surname.','.
                                    $staff->StaffPerson->EmailAddresses[0]->EmailAddress.',,'.
                                    $staff->SectionID."\r\n";
        }

        $zipper = new Zipper;
        $zipper->make(storage_path('app/asm/asm.zip'))
            ->addString('courses.csv', $courses_csv_content)
            ->addString('classes.csv', $classes_csv_content)
            ->addString('students.csv', $student_csv_result)
            ->addString('rosters.csv', $roster_csv_result)
            ->addString('staff.csv', $staff_csv_result)
            ->close();


        $connection = \ssh2_connect('upload.appleschoolcontent.com', 22);
        \ssh2_auth_password($connection, env('ASM_USER'), env('ASM_PASSWORD'));
        $copy = \ssh2_scp_send($connection, storage_path('app/asm/asm.zip'), 'dropbox/asm.zip', 0644);
        dd($copy);
        /*

        Storage::put('asm/csv/courses.csv', $courses_csv_content);
        Storage::put('asm/csv/classes.csv', $classes_csv_content);
        Storage::put('asm/csv/students.csv', $student_csv_result);
        Storage::put('asm/csv/rosters.csv', $roster_csv_result);
        Storage::put('asm/csv/staff.csv', $staff_csv_result);

        $zip = new \ZipArchive();
        if($zip->open(storage_path('app/asm/asm.zip'),\ZIPARCHIVE::CREATE) !== true) {
            return false;
        };
        $zip->addFromString('courses.csv', $courses_csv_content);
        $zip->addFromString('classes.csv', $classes_csv_content);
        $zip->addFromString('students.csv', $student_csv_result);
        $zip->addFromString('rosters.csv', $roster_csv_result);
        $zip->addFromString('staff.csv', $staff_csv_result);
        $zip->close();

        /*
        $zip = Zip::create(storage_path('app/asm/asm.zip'));
        $zip->add(storage_path('app/asm/csv', true));
        $zip->close();
        dd($zip);
        */
        $this->info('Done!');
    }
}
