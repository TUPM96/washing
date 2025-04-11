<?php

namespace App\Admin\Controllers;

use App\Models\QrCode;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;


class QrCodeController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'QR Codes';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new QrCode);

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->like('bankAccount', __('Bank Account'));
            $filter->like('bankCode', __('Bank Code'));
            $filter->like('userBankName', __('User Bank Name'));
            $filter->like('terminalCode', __('Terminal Code'));
        });

        $grid->column('id', __('ID'))->sortable();
        $grid->column('content', __('Content'));
        $grid->column('bankAccount', __('Bank Account'));
        $grid->column('bankCode', __('Bank Code'));
        $grid->column('userBankName', __('User Bank Name'));
        $grid->column('qr', __('QR'))->display(function ($qr) {
            return QrCodeGenerator::size(100)->generate($qr);
        });
        $grid->column('terminalCode', __('Store ID'));

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(QrCode::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('content', __('Content'));
        $show->field('bankAccount', __('Bank Account'));
        $show->field('bankCode', __('Bank Code'));
        $show->field('userBankName', __('User Bank Name'));
        $show->field('qr', __('QR'))->as(function ($qr) {
            return QrCodeGenerator::size(200)->generate($qr);
        })->unescape();
        $show->field('terminalCode', __('Store ID'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new QrCode);

        $form->display('id', __('ID'));
        $form->text('terminalCode', __('Store ID'))->required();
        $form->text('terminalName', __('Store Name'))->required();
        $form->text('bankAccount', __('Bank Account'))->required();
        $form->text('bankCode', __('Bank Code'))->required();
        $form->text('userBankName', __('User Bank Name'))->required();
        $form->text('content', __('Content'))->required();
        $form->hidden('qr', __('QR'))->readonly();

        $form->saving(function (Form $form) {
            $form->content = str_replace("\n", "", $form->content);
            $form->content = str_replace(" ", "", $form->content);
            $token = $this->tokenGenerate();
            $mid = $this->listMid($token);
            $checkSum = $this->calculateCheckSum('Y3VzdG9tZXItdnNvMTgyODJzcGluZXNoaW5lLXVzZXIyNDIwMQ==', $form->bankCode, $form->bankAccount);
            $this->synchronize($token, $mid, $checkSum, $form->bankCode, $form->bankAccount, $form->terminalCode, $form->terminalName);
            if($token) {
                $form->qr = $this->createQrCode($token, $form->content, $form->bankAccount, $form->bankCode, $form->userBankName, $form->terminalCode);
            }
        });

        return $form;
    }

    private function tokenGenerate()
    {
        $client = new Client();
        $headers = [
            'Authorization' => 'Basic Y3VzdG9tZXItdnNvMTgyODJzcGluZXNoaW5lLXVzZXIyNDIwMTpZM1Z6ZEc5dFpYSXRkbk52TVRneU9ESnpjR2x1WlhOb2FXNWxMWFZ6WlhJeU5ESXdNUT09',
            'Cookie' => 'JSESSIONID=52CDCB386BE817C496ED2922EE57DE6D'
        ];
        $request = new Request('POST', 'https://api.vietqr.org/vqr/api/token_generate', $headers);
        $res = $client->sendAsync($request)->wait();
        return json_decode($res->getBody(), true)['access_token'] ?? null;
    }

    private function createQrCode($token, $content, $bankAccount, $bankCode, $userBankName, $terminalCode)
    {
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'Cookie' => 'JSESSIONID=52CDCB386BE817C496ED2922EE57DE6D'
        ];
        $body = '{
            "content": "' . $content . '",
            "bankAccount": "' . $bankAccount . '",
            "bankCode": "' . $bankCode . '",
            "userBankName": "' . $userBankName . '",
            "transType": "C",
            "qrType": "1",
            "terminalCode": "' . $terminalCode . '"
        }';
        \Log::info('Sending cURL request', ['url' => 'https://api.vietqr.org/vqr/api/qr/generate-customer', 'headers' => $headers, 'body' => $body]);

        $request = new Request('POST', 'https://api.vietqr.org/vqr/api/qr/generate-customer', $headers, $body);
        $res = $client->sendAsync($request)->wait();
        return json_decode($res->getBody(), true)['qrCode'] ?? null;
    }

    private function listMid($token)
    {
        $client = new Client();
        $headers = [
            'Cookie' => 'JSESSIONID=500D7AA9F3D5A31F948B72D2AD29EED2; JSESSIONID=8E31B227D44EF03ED40D63992709C731',
            'Authorization' => 'Bearer ' . $token,
        ];
        $request = new Request('GET', 'https://api.vietqr.org/vqr/api/mid/list-mid?page=1&size=20', $headers);
        $res = $client->sendAsync($request)->wait();
        return json_decode($res->getBody(), true)['data'][0] ?? null;
    }

    private function synchronize($token, $mid, $checkSum, $bankCode, $bankAccount, $terminalCode, $terminalName, $terminalAddress = 'Vinh Phuc')
    {
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'Cookie' => 'JSESSIONID=500D7AA9F3D5A31F948B72D2AD29EED2'
        ];
        $body = json_encode([
            'terminals' => [
                [
                    'mid' => $mid['mid'],
                    "merchantName" => $mid['merchantName'],
                    'terminalName' => $terminalName,
                    'terminalCode' => $terminalCode,
                    'terminalAddress' => $terminalAddress,
                    'bankAccount' => $bankAccount,
                    'bankCode' => $bankCode,
                    'checkSum' => $checkSum,
                ]
            ]
        ]);

        // Log the cURL request details
        \Log::info('cURL Request:', [
            'url' => 'https://api.vietqr.org/vqr/api/tid/synchronize/v1',
            'headers' => $headers,
            'body' => $body
        ]);

        $request = new Request('POST', 'https://api.vietqr.org/vqr/api/tid/synchronize/v1', $headers, $body);
        $res = $client->sendAsync($request)->wait();
        echo $res->getBody();
    }



    private function calculateCheckSum($password, $bankCode, $bankAccount)
    {
        $stringToHash = $password . $bankCode . $bankAccount;
        return md5($stringToHash);
    }
}
