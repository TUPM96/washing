<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Encore\Admin\Controllers\Dashboard;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;

class HomeController extends Controller
{
    public function index(Content $content)
    {
        $machines = Machine::with(['machinePlans', 'location'])->orderBy('id', 'desc')->paginate(10);

        return $content
            ->title('Dashboard')
            ->description('List of Machines')
            ->view('admin.machines', compact('machines'));
    }
}
