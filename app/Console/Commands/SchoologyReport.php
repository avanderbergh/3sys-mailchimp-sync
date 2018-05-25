<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Avanderbergh\Schoology\SchoologyApi;
use Illuminate\Support\Facades\Storage;

class SchoologyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:schoology';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Report on Schoology';

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
        $schoology_key = env('CONSUMER_KEY');
        $schoology_secret = env('CONSUMER_SECRET');
        $schoology = new SchoologyApi($schoology_key,$schoology_secret,null,null,null, true);
        $courses = [];
        $result = $schoology->apiResult('courses');
        while (property_exists($result->links, 'next')){
            $courses = array_merge($courses, $result->course);
            $result = $schoology->apiResult('courses'.substr($result->links->next, strpos($result->links->next, '?')));
        }
        $courses = array_merge($courses, $result->course);
        $bar = $this->output->createProgressBar(count($courses));
        foreach ($courses as $course){
            $course->sections = [];
            $result = $schoology->apiResult(sprintf('courses/%s/sections',$course->id));
            while (property_exists($result->links, 'next')){
                $course->sections = array_merge($course->sections, $result->section);
                $result = $schoology->apiResult(sprintf('courses/%s/sections',$course->id).substr($result->links->next, strpos($result->links->next, '?')));
            }
            $course->sections = array_merge($course->sections, $result->section);
            foreach ($course->sections as $section){
                $section->teachers = [];
                $section->students = 0;
                $result = $schoology->apiResult(sprintf('sections/%s/enrollments',$section->id));
                while (property_exists($result->links, 'next')){
                    foreach ($result->enrollment as $enrollment){
                        if ($enrollment->admin){
                            $section->teachers[] = $enrollment->name_display;
                        } else {
                            $section->students++;
                        }
                    }
                    $result = $schoology->apiResult(sprintf('sections/%s/enrollments',$section->id) . substr($result->links->next, strpos($result->links->next, '?')));
                }
                foreach ($result->enrollment as $enrollment){
                    if ($enrollment->admin){
                        $section->teachers[] = $enrollment->name_display;
                    } else {
                        $section->students++;
                    }
                }
                $result = $schoology->apiResult(sprintf('sections/%s/documents',$section->id));
                $section->documents = count($result->document);
                $result = $schoology->apiResult(sprintf('sections/%s/assignments',$section->id));
                $section->assignments = count($result->assignment);
                $result = $schoology->apiResult(sprintf('sections/%s/pages',$section->id));
                $section->pages = count($result->page);
                $result = $schoology->apiResult(sprintf('sections/%s/events',$section->id));
                $section->events = count($result->event);
                $result = $schoology->apiResult(sprintf('sections/%s/updates',$section->id));
                $section->updates = count($result->update);
            }
            $bar->advance();
        }
        $bar->finish();
        $csv_output = 'Course,Section,Teacher,Students,Assignments,Events,Pages,Files/Links,Updates,Link to Course' . PHP_EOL;
        foreach ($courses as $course){
            foreach ($course->sections as $section){
                foreach ($section->teachers as $teacher){
                    $csv_output .= $course->title . ','
                        . $section->section_title . ','
                        . $teacher . ','
                        . $section->students . ','
                        . $section->assignments . ','
                        . $section->events . ','
                        . $section->pages . ','
                        . $section->documents . ','
                        . $section->updates . ','
                        . 'https://isd.schoology.com/course/'. $section->id . PHP_EOL;
                }
            }
        }
        Storage::put('schoology_report.csv', $csv_output);
    }
}
