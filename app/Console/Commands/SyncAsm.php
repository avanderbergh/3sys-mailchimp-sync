<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Storage;

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
        $year = $this->argument('year');    // the academic year
        $wcbs = resolve('App\Services\WCBSApi');
        $url = 'https://3sys.isdedu.de/WCBSAPI.ODataApi/Subject?$select=ID,Code,Description,SubjectSets&$expand=SubjectSets($select=ID,Code,SectionCode,SectionID,SectionID,SectionID,Description,Tutor;$expand=Tutor($select=TutorID,TutorCode);$filter=AcademicYearCode+eq+'.$year.'+and+SchoolCode+eq+\'IS\')&$filter=InUse+eq+true+and+SchoolCode+eq+\'IS\'';
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
        $url = 'https://3sys.isdedu.de/WCBSAPI.ODataApi/CurrentPupil?$select=ID,Code,SubjectSets,Form,PupilPerson&$expand=SubjectSets($select=ID,SubjectSetID,SubjectSetCode),Form($select=FormYearCode,SectionCode,SectionID),PupilPerson($select=Surname,PreferredName,EmailAddresses;$expand=EmailAddresses($select=EmailAddress))&$filter=InUse+eq+true+and+AcademicYearCode+eq+'.$year.'+and+SchoolCode+eq+\'IS\'';
        $result = $wcbs->request($url);     // Fetch the data from 3Sys through the API
        $student_csv_result = "person_id,person_number,first_name,middle_name,last_name,grade_level,email_address,sis_username,password_policy,location_id\r\n";
        $roster_csv_result = "roster_id,class_id,student_id\r\n";
        foreach($result->value as $student) {
            $student_csv_result .=  $student->ID.','.
                                    sprintf("%d",$student->Code).','.
                                    $student->PupilPerson->PreferredName.',,'.
                                    $student->PupilPerson->Surname.','.
                                    $student->Form->FormYearCode.','.
                                    $student->PupilPerson->EmailAddresses[0]->EmailAddress.',,,'.
                                    $student->Form->SectionID."\r\n";
            foreach($student->SubjectSets as $roster) {
                $roster_csv_result .=   $roster->ID.','.
                                        $roster->SubjectSetID.','.
                                        $student->ID."\r\n";
            }
        }

        $url = 'https://3sys.isdedu.de/WCBSAPI.ODataApi/Staff?$select=ID,Code,SectionCode,SectionID,StaffPerson&$expand=StaffPerson($select=Surname,PreferredName,EmailAddresses;$expand=EmailAddresses($select=EmailAddress;$filter=EmailAddressTypeDescription+eq+\'Internal+Staff+email\'))&$filter=InUse+eq+true+and+SchoolCode+eq+\'IS\'+and+CategoryCode+eq+\'TEA001\'';
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
        Storage::put('courses.csv', $courses_csv_content);
        Storage::put('classes.csv', $classes_csv_content);
        Storage::put('students.csv', $student_csv_result);
        Storage::put('roster.csv', $roster_csv_result);
        Storage::put('staff.csv', $staff_csv_result);

        $this->info('Done!');
    }
}
