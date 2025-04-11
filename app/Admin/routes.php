<?php

use App\Admin\Controllers\LocationController;
use App\Admin\Controllers\MachineController;
use App\Admin\Controllers\OptionsController;
use App\Admin\Controllers\QrCodeController;
use App\Admin\Controllers\TelegramUserController;
use App\Admin\Controllers\TransactionController;
use Illuminate\Routing\Router;

Admin::routes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
    'as'            => config('admin.route.prefix') . '.',
], function (Router $router) {

    $router->get('/', 'HomeController@index')->name('home');

    $router->resource('options', OptionsController::class)->names('options');
    $router->resource('machines', MachineController::class)->names('machines');
    $router->resource('transactions', TransactionController::class)->names('transactions');
    $router->resource('locations', LocationController::class)->names('locations');
    $router->resource('telegram-users', TelegramUserController::class)->names('telegram-users');
    $router->resource('qrcode', QrCodeController::class)->names('qrcode');

});
