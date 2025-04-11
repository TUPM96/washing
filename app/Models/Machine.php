<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Client;

class Machine extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'name',
        'location_id',
        'qr_code',
        'token',
        'program_code',
        'time_remaining',
        'key',
        'last_active_time',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($machine) {
            if (empty($machine->token)) {
                $accessToken = self::fetchAccessToken();
                $deviceId = self::addDevice($accessToken, $machine->name);
                $machine->token = self::fetchToken($deviceId, $accessToken);
            }
        });
    }


    public static function fetchAccessToken()
    {
        $client = new Client();
        $response = $client->request('POST', 'http://re.saveapp.cc:8080/api/auth/login', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'username' => 'tenant@thingsboard.org',
                'password' => 'tenant',
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['token'] ?? null;
    }

    public static function addDevice($accessToken, $deviceName)
    {
        $client = new Client();
        $response = $client->request('POST', 'http://re.saveapp.cc:8080/api/device', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Authorization' => 'Bearer ' . $accessToken,
            ],
            'json' => [
                'name' => $deviceName . "-" . time(),
                'type' => 'default',
                'label' => 'label',
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['id']['id'] ?? null;
    }

    public static function fetchToken($deviceId, $accessToken)
    {
        $client = new Client();
        $response = $client->request('GET', "http://re.saveapp.cc:8080/api/device/{$deviceId}/credentials", [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['credentialsId'] ?? null;
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function machinePlans()
    {
        return $this->hasMany(MachinePlan::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function machineHistories()
    {
        return $this->hasMany(MachineHistory::class);
    }

    public function qrCode()
    {
        return $this->hasOne(QrCode::class, 'terminalCode', 'key');
    }
}
