<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class VietQRController extends Controller
{
    public function getStoreQR()
    {
        $token = $this->tokenGenerate();
        $mid = $this->listMid($token);
        $checkSum = $this->calculateCheckSum('Y3VzdG9tZXItdnNvMTgyODJzcGluZXNoaW5lLXVzZXIyNDIwMQ==', 'MB', '0362388899');
        $this->synchronize($token, $mid, $checkSum);
    }

    private function tokenGenerate()
    {
        $client = new Client();
        $headers = [
            'Authorization' => 'Basic Y3VzdG9tZXItdnNvMTgyODJzcGluZXNoaW5lLXVzZXIyNDIwMTpZM1Z6ZEc5dFpYSXRkbk52TVRneU9ESnpjR2x1WlhOb2FXNWxMWFZ6WlhJeU5ESXdNUT09',
            'Cookie' => 'JSESSIONID=8E31B227D44EF03ED40D63992709C731'
        ];
        $request = new Request('POST', 'https://api.vietqr.org/vqr/api/token_generate', $headers);
        $res = $client->sendAsync($request)->wait();
        return json_decode($res->getBody(), true)['access_token'] ?? null;
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

    private function synchronize($token, $mid, $checkSum)
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
                    'terminalName' => 'protest2',
                    'terminalCode' => 'prodtest02',
                    'terminalAddress' => 'Vinh Phuc',
                    'bankAccount' => '0362388899',
                    'bankCode' => 'MB',
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
