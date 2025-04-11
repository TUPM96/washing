<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateRPMData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate_rpm_data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
//        $this->call("seed:roles");
//        $this->call("seed:permissions");
        $this->call("seed:menus");
    }
}
