<?php

namespace App\Admin\Controllers;

use App\Models\Location;
use App\Models\TelegramUser;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class LocationController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Locations';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Location);

        $grid->column('id', __('ID'))->sortable();
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->like('name', __('Name'));
        });
        $grid->column('name', __('Name'))->display(function ($name) {
            $machineCount = $this->machines()->count();
            $adminCount = $this->locationAdmins()->count();
            return "{$name} ({$machineCount} machines, {$adminCount} admins)";
        });
        $grid->column('map', __('Map'))->display(function () {
            $url = "https://www.google.com/maps?q={$this->latitude},{$this->longitude}";
            return "<a href='{$url}' target='_blank'>View on Map</a>";
        });
        $grid->column('latitude', __('Latitude'));
        $grid->column('longitude', __('Longitude'));

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(Location::with('locationAdmins')->findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('name', __('Name'));
        $show->field('latitude', __('Latitude'));
        $show->field('longitude', __('Longitude'));
        $show->field('telegram_bot_token', __('Telegram Bot Token'));
        $show->field('telegram_chat_id', __('Telegram Chat ID'));
        $show->field('slack_success_webhook', __('Slack Success Webhook'));
        $show->field('slack_error_webhook', __('Slack Error Webhook'));

        $show->locationAdmins('Location Admins', function ($locationAdmin) {
            $locationAdmin->resource('/admin/telegram-users');

            $locationAdmin->id();
            $locationAdmin->telegram_id();
            $locationAdmin->username();
            $locationAdmin->first_name();
            $locationAdmin->last_name();

            $locationAdmin->disableCreateButton();
            $locationAdmin->disableActions();
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
        $form = new Form(new Location);

        $form->display('id', __('ID'));
        $form->text('name', __('Name'))->required();
        $form->decimal('latitude', __('Latitude'))->required();
        $form->decimal('longitude', __('Longitude'))->required();
        $form->text('telegram_bot_token', __('Telegram Bot Token'));
        $form->text('telegram_chat_id', __('Telegram Chat ID'));
        $form->text('slack_success_webhook', __('Slack Success Webhook'));
        $form->text('slack_error_webhook', __('Slack Error Webhook'));
        $form->multipleSelect('locationAdmins', __('Location Admins'))->options(
            TelegramUser::all()->mapWithKeys(function ($user) {
                return [$user->id => "{$user->telegram_id} ({$user->username})"];
            })
        );

        $form->saved(function (Form $form) {
            $location = $form->model();
            $locationAdmins = array_filter(request('locationAdmins', []));
            $location->locationAdmins()->sync($locationAdmins);
        });

        return $form;
    }
}
