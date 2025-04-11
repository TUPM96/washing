<?php

namespace App\Admin\Controllers;

use App\Models\Location;
use App\Models\Machine;
use App\Models\QrCode;
use Encore\Admin\Admin;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;

class MachineController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Machines';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Machine);

        $grid->model()->orderBy('id', 'desc'); // Sort by id in descending order

        $grid->column('id', __('ID'))->sortable();
        $grid->column('name', __('Name'));
        $grid->column('key', __('Store ID'));
        $grid->column('token', __('Thingboard Token'));
        $grid->column('location.name', __('Location'));
        $grid->column('qrCode.qr', __('QR Code'))->display(function ($qr) {
            if ($qr) {
                return QrCodeGenerator::size(100)->generate($qr);
            }
            return '';
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Machine::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('name', __('Name'));
        $show->field('key', __('Store ID'));
        $show->field('token', __('Thingboard Token'));
        $show->field('program_code', __('Program Code'));
        $show->field('time_remaining', __('Time Remaining'));
        $show->field('telegram_webhook_url', __('VietQR Webhook URL'))->as(function () {
            return url('/hook/' . $this->key);
        });
        $show->field('location.name', __('Location'));
        $show->field('qrCode.qr', __('QR Code'))->as(function ($qr) {
            if ($qr) {
                return QrCodeGenerator::size(100)->generate($qr);
            }
            return '';
        })->unescape();

        $machinePlans = Machine::findOrFail($id)->machinePlans;
        $buttons = '';

        foreach ($machinePlans as $plan) {
            $buttons .= '<button onclick="toggleMachinePlan(' . $plan->id . ')">' . $plan->name . '</button> ';
        }


        Admin::html('
    <style>
        #machine-plan-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding-bottom: 30px;
        }
        #machine-plan-buttons button {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        #machine-plan-buttons button:hover {
            background-color: #45a049;
        }
    </style>
    <div id="machine-plan-buttons">
        ' . $buttons . '
    </div>
    <script>
        function toggleMachinePlan(planId) {
            // Implement the logic to toggle the machine plan
            console.log("Toggling machine plan with ID:", planId);
            // Example AJAX request to toggle the machine plan
            fetch("/api/toggle-machine-plan/" + planId, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "' . csrf_token() . '"
                }
            })
            .then(response => response.json())
            .then(data => {
                console.log("Response:", data);
            })
            .catch(error => {
                console.error("Error:", error);
            });
        }
    </script>
');

        $show->machinePlans('Machine Plans', function ($machinePlan) {
            $machinePlan->resource('/admin/machine-plans');

            $machinePlan->id();
            $machinePlan->program_code();
            $machinePlan->name("Plan Name");
            $machinePlan->price();
            $machinePlan->minute();
            $machinePlan->note();

            $machinePlan->disableActions();
            $machinePlan->disableCreateButton();
            $machinePlan->disableExport();
        });

        $show->machineHistories('Machine History', function ($machineHistory) {
            $machineHistory->resource('/admin/machine-histories');

            $machineHistory->model()->orderBy('id', 'desc');

            $machineHistory->id();
            $machineHistory->status();
            $machineHistory->changed_at();

            $machineHistory->disableActions();
            $machineHistory->disableCreateButton();
            $machineHistory->disableExport();
        });




        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Machine);

        $form->display('id', __('ID'));
        $form->text('name', __('Name'))->required();
        $form->select('key', __('Store ID'))
            ->options(QrCode::all()->pluck('terminalCode', 'terminalCode'))
            ->required();
        $form->text('token', __('Thingboard Token'));
        $form->select('location_id', __('Location'))->options(Location::all()->pluck('name', 'id'))->required();
        $form->hasMany('machinePlans', function (Form\NestedForm $form) {
            $form->text('program_code', __('Code'))->required();
            $form->text('name', __('Name'))->required();
            $form->decimal('price', __('Price'))->required();
            $form->number('minute', __('Minute'))->required();
            $form->textarea('note', __('Note'));
        });

        return $form;
    }
}
