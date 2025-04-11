<?php

namespace App\Admin\Controllers;

use App\Models\Transaction;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class TransactionController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Transactions';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Transaction);

        $grid->model()->orderBy('id', 'desc');

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->like('machine.name', __('Machine'));
        });

        $grid->column('id', __('ID'))->sortable();
        $grid->column('type', __('Type'));
        $grid->column('machine.name', __('Machine'));
        $grid->column('third_party', __('Bank Transaction ID'));
        $grid->column('amount', __('Amount'));
        $grid->column('description', __('Description'));
        $grid->column('transaction_time', __('Transaction Time'))->display(function ($transaction_time) {
            return \Carbon\Carbon::parse($transaction_time)->addHours(7)->toDateTimeString();
        });
        $grid->column('actions', __('Action'));

        $grid->disableActions();
        $grid->disableCreateButton();

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed   $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Transaction::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('type', __('Type'));
        $show->field('third_party', __('Third Party'));
        $show->field('amount', __('Amount'));
        $show->field('description', __('Description'));
        $show->field('actions', __('Action'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Transaction);

        $form->display('id', __('ID'));
        $form->text('type', __('Type'))->required();
        $form->text('third_party', __('Third Party'))->nullable();
        $form->decimal('amount', __('Amount'))->required();
        $form->textarea('description', __('Description'))->nullable();
        $form->display('created_at', __('Created At'));
        $form->display('updated_at', __('Updated At'));

        return $form;
    }
}
