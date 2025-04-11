<?php

namespace App\Admin\Controllers;

use App\Models\TelegramUser;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class TelegramUserController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Telegram Users';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new TelegramUser);

        $grid->column('id', __('ID'))->sortable();
        $grid->column('telegram_id', __('Telegram ID'))->sortable();
        $grid->column('username', __('Username'));
        $grid->column('first_name', __('First Name'));
        $grid->column('last_name', __('Last Name'));
        $grid->column('is_admin', __('Is Admin'))->display(function ($isAdmin) {
            return $isAdmin ? 'Yes' : 'No';
        });

        $grid->disableCreateButton();

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(TelegramUser::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('telegram_id', __('Telegram ID'));
        $show->field('first_name', __('First Name'));
        $show->field('last_name', __('Last Name'));
        $show->field('username', __('Username'));
        $show->field('is_admin', __('Is Admin'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new TelegramUser);

        $form->display('id', __('ID'));
        $form->text('telegram_id', __('Telegram ID'))->required();
        $form->text('first_name', __('First Name'));
        $form->text('last_name', __('Last Name'));
        $form->text('username', __('Username'));
        $form->switch('is_admin', __('Is Admin'));

        return $form;
    }
}
