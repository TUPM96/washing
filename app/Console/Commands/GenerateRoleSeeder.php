<?php

namespace App\Console\Commands;

use App\Admin\Models\AdminRole;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class GenerateRoleSeeder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate roles';


    /**
     * Seeder Name
     *
     * @var string
     */
    private $seederName = 'AdminRole';

    /**
     * Execute the console command.
     *
     * @param BaseSeed $baseSeed
     * @throws FileNotFoundException
     */
    public function handle(BaseSeed $baseSeed)
    {
        $adminRoles = AdminRole::all();
        if ($adminRoles->isNotEmpty()) {
            $adminRoleContent = $baseSeed->getSeederContent($this->seederName);
            $records = [];

            /** @var $adminRole AdminRole */
            foreach ($adminRoles as $adminRole) {
                $records[$adminRole->slug] = [
                    "id" => $adminRole->id,
                    "name" => $adminRole->name,
                    "permissions" => $adminRole->permissions->pluck('slug')->toArray()
                ];
            }
            $this->putRecordsToSeeder($baseSeed, $adminRoleContent, $records);
        }
        $this->info("Roles successful updated");
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
        $permissionsContent = str_replace(',', ', ', json_encode($record['permissions']));
        return "                \"$slug\" => [
                    \"id\" => \"{$record["id"]}\",
                    \"name\" => \"{$record["name"]}\",
                    \"permissions\" => $permissionsContent
                ],\n";
    }
}
