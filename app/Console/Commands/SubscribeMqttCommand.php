<?php

namespace App\Console\Commands;

use App\Models\Machine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class SubscribeMqttCommand extends Command
{
    protected $signature = 'mqtt:subscribe';
    protected $description = 'Subscribe to an MQTT topic';

    protected $mqttClient;
    protected $connectionSettings;

    public function __construct()
    {
        parent::__construct();
        $host = env('MQTT_HOST', 'localhost');
        $port = env('MQTT_PORT', 1883);
        $clientId = env('MQTT_CLIENT_ID', uniqid());
        $this->mqttClient = new MqttClient($host, $port, $clientId);
        $username = env('MQTT_USERNAME', 'user');
        $password = env('MQTT_PASSWORD', 'user');

        $this->connectionSettings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password);
    }

    public function connect()
    {
        try {
            $this->mqttClient->connect($this->connectionSettings, true, 60); // 60 seconds keep-alive interval
        } catch (\Exception $e) {
            // Handle connection error
            echo "Connection failed: " . $e->getMessage();
        }
    }

    public function handle()
    {
        Log::info('SubscribeMqttCommand handle method called');

        $this->mqttClient->connect($this->connectionSettings, true);
        $this->mqttClient->subscribe('#', function (string $topic, string $message) {
            $this->info("Received message topic [{$topic}]: {$message}");
            Log::info("Received message topic [{$topic}]: {$message}");

            $this->checkCommand($topic, $message);

        }, 2);

        try {
            $this->mqttClient->loop(true);
        } catch (\Exception $e) {
            // Handle loop error and attempt reconnection
            echo "Error during loop: " . $e->getMessage();
            $this->reconnect();
        }
    }

    private function checkCommand(string $topic, string $message)
    {
        $topic_parts = explode('/', $topic);
        $token = $topic_parts[0];
        $type = $topic_parts[1];

        Log::info("Type: " . $type);

        $data = json_decode($message, true);
        $machine = Machine::where('token', $token)->first();

        if ($type === 'realtime' && isset($data['last_active_time'])) {
            $lastActiveTime = $data['last_active_time'];
            Log::info("Machine: " . $machine);
            if ($machine) {
                Log::info("Updating last active time for machine: " . $machine->id);
                Log::info("Last active time: " . $lastActiveTime);
                $machine->last_active_time = $lastActiveTime;
                $machine->save();
            }
        } elseif ($type === 'status' && isset($data['machine_status'])) {
            if ($machine) {
                $telegramToken = $machine->location->telegram_bot_token ?? "";
                $chatId = $machine->location->telegram_chat_id ?? "";
                Log::info("Telegram token: " . $telegramToken);
                Log::info("Chat ID: " . $chatId);
                Log::info("Machine: " . $machine);
                $machineName = $machine->name;
                $location = $machine->location->name;

                if ($data['machine_status'] === 'start') {
                    $notifySlack = $data['notify_slack'] ?? false;
                    Log::info("Notify slack: " . $notifySlack);
                    if($notifySlack) {
                        Log::info("Sending message to slack");
                        Log::info("Machine: " . $machine);
                        $webHookUrl = $machine->location->slack_success_webhook ?? "";
                        Log::info("Webhook URL: " . $webHookUrl);
                        $amount = $machine->machinePlans->where('program_code', $data['program_code'])->first()->price ?? 0;
                        if($amount) {
                            $message = "Machine: {$machine->name}, Charged Amount: {$amount}, Charged Time: " . now()->toDayDateTimeString() . ", Connection Status: Connected, Start Status: Ready To Go, Run Status: Running";
                            Log::info("Message: " . $message);
                            $this->sendToSlack($webHookUrl, $message);
                        }
                    }
                    $message = "✅ Máy giặt $machineName tại $location đã khởi động thành công.";
                } elseif ($data['machine_status'] === 'end') {
                    $message = "✅ Máy giặt $machineName tại $location đã hoàn tất giặt.";
                    $machine->program_code = 0;
                    $machine->time_remaining = 0;
                    $machine->save();
                }

                $this->sendMessageToTelegram($telegramToken, $chatId, $message);
            }
        } elseif ($type === 'running' && isset($data['machine_status'])) {
            if ($machine && $data['program_code'] != 0) {
                $machine->program_code = $data['program_code'];
                $machine->time_remaining = $data['time_remaining'];
                $machine->save();
            }
        }
    }

    private function reconnect()
    {
        $this->mqttClient->disconnect();
        sleep(5);
        $this->connect();
    }

    private function sendMessageToTelegram($telegramToken, $chatId, $message)
    {
        $telegramApiUrl = "https://api.telegram.org/bot{$telegramToken}/sendMessage";
        $response = Http::post($telegramApiUrl, [
            'chat_id' => $chatId,
            'text' => $message,
        ]);

        return $response->json();
    }

    public function sendToSlack($webhookUrl, $message) {
        try {
            Log::info("Sending message to Slack");
            Log::info("Webhook URL: " . $webhookUrl);
            Log::info("Message: " . $message);

            $response = Http::post($webhookUrl, [
                'text' => $message,
            ]);

            Log::info("Response status: " . $response->status());
            Log::info("Response body: " . $response->body());

            return $response->body();
        } catch (\Exception $e) {
            Log::error("Error sending message to Slack: " . $e->getMessage());
            return null;
        }
    }
}
