<?php

namespace App\Console\Commands;

use App\Admin\Models\AdminMenu;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class GenerateMenuSeeder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:menus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate menus';


    /**
     * Seeder Name
     *
     * @var string
     */
    private $seederName = 'AdminMenu';

    /**
     * Execute the console command.
     *
     * @param BaseSeed $baseSeed
     * @throws FileNotFoundException
     */
    public function handle(BaseSeed $baseSeed)
    {
        $adminMenus = AdminMenu::with('roles')->get();
        if ($adminMenus->isNotEmpty()) {
            $adminMenuContent = $baseSeed->getSeederContent($this->seederName);
            $records = [];

            /** @var $adminMenu AdminMenu */
            foreach ($adminMenus as $adminMenu) {
                $records[$adminMenu->id] = [
                    "parent_id" => $adminMenu->parent_id,
                    "order" => $adminMenu->order,
                    "title" => $adminMenu->title,
                    "icon" => $adminMenu->icon,
                    "uri" => $adminMenu->uri,
                    "permission" => $adminMenu->permission,
                    "roles" => $adminMenu->roles->pluck('slug')->toArray()
                ];
            }

            $this->putRecordsToSeeder($baseSeed, $adminMenuContent, $records);
        }
        $this->info("Menus successful updated");
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
        foreach ($records as $id => $record) {
            $recordsTemplate .= $this->getRecordTemplate($id, $record);
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
     * @param $id
     * @param $record
     * @return string
     */
    private function getRecordTemplate($id, $record)
    {
        $rolesContent = str_replace(',', ', ', json_encode($record['roles']));
        return "                \"$id\" => [
                    \"parent_id\" => {$record["parent_id"]},
                    \"order\" => {$record["order"]},
                    \"title\" => \"{$record["title"]}\",
                    \"icon\" => \"{$record["icon"]}\",
                    \"uri\" => \"{$record["uri"]}\",
                    \"permission\" => \"{$record["permission"]}\",
                    \"roles\" => $rolesContent
                ],\n";
    }
}
