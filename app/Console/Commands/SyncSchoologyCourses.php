<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Avanderbergh\Schoology\SchoologyApi;

/**
 * Class SyncSchoologyCourses
 * @package App\Console\Commands
 */
class SyncSchoology extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:schoology';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
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
        $bar = $this->output->createProgressBar(sizeof($students));
        $student_csv = 'Code,Name,Surname' . PHP_EOL;
        foreach ($students as $k => $student) {
            $student_csv .= $student->Code . ',' . $student->Name->PreferredName . ',' . $student->Name->Surname . PHP_EOL;
            $bar->advance();
        }
        $bar->finish();
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
        $this->info('Fetching Subjects from WCBS');
        $response = $wcbs->getRequest('schools(1)/Subjects');
        $subjects = [];
        $result = json_decode($response->getBody()->getContents());
        foreach ($result->value as $value) {
            $subjects[$value->Id] = [
                'code' => $value->Code,
                'description' => $value->Description,
            ];
        }
        while (property_exists($result, '@odata.nextLink')) {
            $response = $wcbs->getUrl($result->{'@odata.nextLink'});
            $result = json_decode($response->getBody()->getContents());
            foreach ($result->value as $value) {
                $subjects[$value->Id] = [
                    'code' => $value->Code,
                    'description' => $value->Description,
                ];
            }
        }
        $schoology_key = env('CONSUMER_KEY');
        $schoology_secret = env('CONSUMER_SECRET');
        $schoology = new SchoologyApi($schoology_key,$schoology_secret,null,null,null, true);
        $bar = $this->output->createProgressBar(sizeof($subjects));
        foreach ($subjects as $k => $subject) {
            $active_sets = 0;
            $enrollments = [];
            $response = $wcbs->getRequest(sprintf('schools(1)/SubjectSets?$expand=Pupil($select=Code;$filter=SubSchool eq \'SRS\'),Tutor1($select=Code)&$filter=SubjectId eq %s and AcademicYear eq 2017 and NonTeaching eq false', $k));
            $decoded = json_decode($response->getBody()->getContents());
            $subject_sets = $decoded->value;
            $body = [
                'courses' => [
                    'course' => [
                        'title' => $subject['description'],
                        'course_code' => $subject['code'],
                        'sections' => [
                            'section' => []
                        ]
                    ]
                ]
            ];
            foreach ($subject_sets as $subject_set) {
                $has_tutor = false;
                $teacher = $subject_set->Tutor1;
                if ($teacher) {
                    if (property_exists($teacher, 'Code')) {
                        $has_tutor = true;
                        $enrollments[] = [
                            'course_code' => $subject['code'],
                            'section_code' => $subject_set->SetCode,
                            'school_uid' => $teacher->Code,
                            'admin' => 1,
                        ];
                    } else {
                        $this->error('No Tutor1 Property for ' . $subject['description'] . ':' . $subject_set->SetCode);
                    }
                } else {
                    $this->error('Tutor Empty for ' . $subject['description'] . ':' . $subject_set->SetCode);
                }
                if (property_exists($subject_set, 'Pupil')){
                    if (!empty($subject_set->Pupil)) {
                        if ($has_tutor) {
                            $active_sets++;
                            $body['courses']['course']['sections']['section'][] = [
                                'title' => $subject_set->Code,
                                'section_code' => $subject_set->SetCode,
                                'grading_periods' => [
                                    407562
                                ]
                            ];
                            foreach ($subject_set->Pupil as $student) {
                                $enrollments[] = [
                                    'course_code' => $subject['code'],
                                    'section_code' => $subject_set->SetCode,
                                    'school_uid' => $student->Code,
                                    'admin' => 0,
                                ];
                            }
                        } else {
                            $this->error('Couse has pupils but no Tutor ' . $subject['description'] . ':' . $subject_set->SetCode);
                        }
                    } else {
                        $this->error('Pupils Empty for ' . $subject['description'] . ':' . $subject_set->SetCode);
                    }
                } else {
                    $this->error('No Puplis Property for ' . $subject['description'] . ':' . $subject_set->SetCode);
                }
            }
            if ($active_sets > 0) {
                try {
                    $schoology->apiResult('courses?update_existing=1', 'POST', $body);
                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                }
                $enrollments_chunked = array_chunk($enrollments, 50);
                foreach ($enrollments_chunked as $enrollment_chunk) {
                    $body = [
                        'enrollments' => [
                            'enrollment' => $enrollment_chunk
                        ]
                    ];
                    try {
                        $response = $schoology->apiResult('enrollments/import/course/407562','POST', $body);
                    } catch (\Exception $e) {
                        $this->error($e->getMessage());
                    }
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->info('Done!');
    }
}
