<?php

namespace App\Console\Commands;

use App\Admin\Models\AdminPermission;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class GeneratePermissionSeeder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate permissions';


    /**
     * Seeder Name
     *
     * @var string
     */
    private $seederName = 'AdminPermission';

    /**
     * Execute the console command.
     *
     * @param BaseSeed $baseSeed
     * @throws FileNotFoundException
     */
    public function handle(BaseSeed $baseSeed)
    {
        $adminPermissions = AdminPermission::all();
        if ($adminPermissions->isNotEmpty()) {
            $adminPermissionContent = $baseSeed->getSeederContent($this->seederName);
            $records = [];

            /** @var $adminPermission AdminPermission */
            foreach ($adminPermissions as $adminPermission) {
                $records[$adminPermission->slug] = [
                    "id" => $adminPermission->id,
                    "name" => $adminPermission->name,
                    "http_method" => $adminPermission->http_method,
                    "http_path" => $adminPermission->http_path
                ];
            }

            $this->putRecordsToSeeder($baseSeed, $adminPermissionContent, $records);
        }
        $this->info("Permissions successful updated");
    }

    /**
     * Put records to seeder file
     *
     * @param BaseSeed $baseSeed
     * @param $content
     * @param $records
     */
    private function putRecordsToSeeder(BaseSeed $baseSeed, $content, $records)
    {
        $recordsTemplate = "\n            \$records = [\n";
        foreach ($records as $slug => $record) {
            $recordsTemplate .= $this->getRecordTemplate($slug, $record);
        }
        $recordsTemplate .= "            ];\n            ";
        $recordsTemplate = $baseSeed->replaceBetween(
            $content,
            '//start-record',
            '//end-record',
            $recordsTemplate
        );

        $baseSeed->putSeederFile($this->seederName, $recordsTemplate);
    }

    /**
     * Get record seeder template
     *
     * @param $slug
     * @param $record
     * @return string
     */
    private function getRecordTemplate($slug, $record)
    {
        return "                \"$slug\" => [
                    \"id\" => \"{$record["id"]}\",
                    \"name\" => \"{$record["name"]}\",
                    \"http_method\" => \"{$record["http_method"]}\",
                    \"http_path\" => \"{$record["http_path"]}\"
                ],\n";
    }
}
