<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Machine;
use App\Models\MachineHistory;
use App\Models\TelegramUser;
use App\Models\Transaction;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;

class WebhookController extends Controller
{
    public function handle($key, Request $request)
    {
        Log::info('Webhook Data:', $request->all());

        // Extract the data from the request
        $data = $request->input('values')[0];

        // Convert the date format
        $transactionTime = Carbon::createFromFormat('d/m/Y H:i:s', $data[1])->format('Y-m-d H:i:s');

        // Retrieve the machine_id from the key
        $machine = Machine::where('key', $key)->first();
        $machine_id = $machine ? $machine->id : null;

        $matchingPlan = $machine ? $machine->machinePlans()->where('price', $data[2])->first() : null;
        if (!$machine) {
            $actions = 'Khong tim thay may';
        } elseif (!$matchingPlan) {
            $actions = 'Khong tim thay chuong trinh';
        } else {
            $actions = 'Chuong trinh: ' . $matchingPlan->name . " | May: " . $machine->name;
        }


        // Check for duplicate transaction
        $existingTransaction = Transaction::where('third_party', $data[3])->first();

        if (!$existingTransaction) {
            // Create a new Transaction record
            Transaction::create([
                'type' => $data[6], // Assuming 'Giao dịch đến' is the type
                'third_party' => $data[3], // Assuming 'FT25016914099981' is the third party
                'amount' => $data[2], // Assuming '2000' is the amount
                'description' => $data[9], // Assuming 'SQRQiZUteqHDw FT25006763528284   Magiao dich  Trace101029 Trace 10102' is the description
                'transaction_time' => $transactionTime, // Assuming '05/01/2025 06:34:46' is the transaction time
                'machine_id' => $machine_id,
                'actions' => $actions,
            ]);
        } else {
            Log::info('Duplicate transaction detected:', ['third_party' => $data[3]]);
        }

        // Send a message to Telegram if a matching plan is found
        if ($matchingPlan && $data[6] == 'Giao dịch đến' && !$existingTransaction) {
            $telegramToken = $matchingPlan->machine->location->telegram_bot_token ?? "";
            $chatId = $matchingPlan->machine->location->telegram_chat_id ?? "";
            $machineName = $machine->name;
            $location = $machine->location->name;
            $price = $data[2];

            if ($machine->program_code != 0) {
                $message = "⚠️ Máy $machineName đang giặt đồ, vui lòng chờ đợi.";
                $this->sendMessageToTelegram($telegramToken, $chatId, $message);
                return response()->json(['status' => 'success']);
            }

            $programCode = $matchingPlan->id;
            $time = $matchingPlan->minute * 60;
            $timeRemaining = $time;

            $message = "🚀 Đang khởi động máy Máy giặt $machineName tại $location, chương trình số $programCode với giá $price...";
            $this->sendMessageToTelegram($telegramToken, $chatId, $message);

            $mqtt = new MqttClient(env('MQTT_HOST', 'localhost'), env('MQTT_PORT', '1884'), uniqid());
            $connectionSettings = (new ConnectionSettings)
                ->setUsername(env('MQTT_USERNAME', 'user1'))
                ->setPassword(env('MQTT_PASSWORD', '12345678'));

            $mqtt->connect($connectionSettings, true);
            $mqtt->publish($machine->token . "/run", json_encode([
                'program_code' => $programCode,
                'time' => $time,
                'time_remaining' => $timeRemaining,
            ]), 1);
            $mqtt->disconnect();
        }

        return response()->json(['status' => 'success']);
    }

    public function handleTelegram(Request $request)
    {
        $data = $request->all();

        $telegramToken = "7638256748:AAEnd3Wqy8IAsYdbcTOKyRFh-NLa_8TVmA8";

        // Log the incoming request data
        Log::info('Telegram Webhook Data:', $data);

        $telegramUserId = $data['message']['from']['id'] ?? ($data['edited_message']['from']['id'] ?? null);

        if($telegramUserId == null) {
            return response()->json(['status' => 'success']);
        }
        $telegramUser = TelegramUser::where('telegram_id', $telegramUserId)->first();
        if ($telegramUser) {
            $telegramUser->update([
                'first_name' => $data['message']['from']['first_name'] ?? "",
                'last_name' => $data['message']['from']['last_name'] ?? "",
                'username' => $data['message']['from']['username'] ?? "",
            ]);
        } else {
            $telegramUser = TelegramUser::create([
                'telegram_id' => $telegramUserId,
                'first_name' => $data['message']['from']['first_name'] ?? "",
                'last_name' => $data['message']['from']['last_name'] ?? "",
                'username' => $data['message']['from']['username'] ?? "",
            ]);
        }

        $chatId = $data['message']['chat']['id'] ?? $data['edited_message']['chat']['id'];

        // Check if the user is an admin
        if (!$telegramUser->is_admin) {
            $message = "⚠️ Bạn không có quyền thực hiện lệnh này.";

            // Send a message back to the chat_id
            $this->sendMessageToTelegram($telegramToken, $chatId, $message);
        } else {
            $command = $data['message']['text'] ?? ($data['edited_message']['text'] ?? null);
            $commands = explode('-', $command);

            $location = $commands[0] ?? '';

            if ($location && count($commands) == 1) {
                $locationM = Location::where('name', $location)->first();
                if ($locationM) {
                    $locationMachine = $locationM->machines;
                    $message = "Danh sách máy tại $location: \n";
                    foreach ($locationMachine as $machine) {
                        $programCode = $machine->program_code;

                        $currentTime = Carbon::now('Asia/Ho_Chi_Minh')->timestamp;
                        $lastActiveTime = $machine->last_active_time;

                        Log::info('Current time:', ['currentTime' => $currentTime]);
                        Log::info('Last active time:', ['lastActiveTime' => $lastActiveTime]);

                        $timeDifference = $currentTime - $lastActiveTime;
                        Log::info('Time difference:', ['timeDifference' => $timeDifference]);

                        if (abs($timeDifference) > 30) {
                            // Machine is offline
                            $message .= "⚠️ Máy {$machine->name} đã offline.\n";
                        } else {
                            if ($programCode == 0) {
                                $message .= "✅ Máy {$machine->name} sẵn sàng, bạn có thể sử dụng.\n";
                            } else {
                                $plan = $machine->machinePlans()->where('id', $programCode)->first();
                                if ($plan) {
                                    $timeRemaining = round($machine->time_remaining / 60);
                                    $timeUsed = round($plan->minute - $timeRemaining);
                                    $message .= "⏳ Máy {$machine->name} đang giặt đồ. Thời gian đã giặt: {$timeUsed} phút. Bạn vui lòng chờ khoảng {$timeRemaining} phút nữa nhé.\n";
                                }
                            }
                        }
                    }
                    $this->sendMessageToTelegram($telegramToken, $chatId, $message);
                } else {
                    $message = "⚠️ Không tìm thấy máy tại $location.";
                    $this->sendMessageToTelegram($telegramToken, $chatId, $message);
                }
            } else if ($location && count($commands) == 3) {
                $machineName = $commands[1];
                $price = $commands[2];
                $machine = Location::where('name', $location)->first()->machines()->where('name', $machineName)->first();
                Log::info($machineName);
                if ($machine) {
                    Log::info($price);
                    Log::info($machine->machinePlans()->get()->toArray());
                    Log::info($machine->machinePlans()->where('price', $price)->first());
                    $plan = $machine->machinePlans()->where('price', $price)->first();
                    if ($plan) {
                        $programCode = $plan->program_code;
                        $currentProgramCode = $machine->program_code;
                        $time = $plan ? $plan->minute * 60 : 0;
                        $timeRemaining = $plan ? $time : 0;

                        $currentTime = Carbon::now('Asia/Ho_Chi_Minh')->timestamp;
                        $lastActiveTime = $machine->last_active_time;

                        Log::info('Current time:', ['currentTime' => $currentTime]);
                        Log::info('Last active time:', ['lastActiveTime' => $lastActiveTime]);

                        $timeDifference = $currentTime - $lastActiveTime;
                        Log::info('Time difference:', ['timeDifference' => $timeDifference]);

                        if (abs($timeDifference) > 30) {
                            // Machine is offline
                            $message = "⚠️ Máy {$machine->name} đã offline.\n";
                            $this->sendMessageToTelegram($telegramToken, $chatId, $message);
                        } else {
                            if ($currentProgramCode == 0) {
                                $message = "🚀 Đang khởi động máy Máy giặt $machineName tại $location, chương trình số $programCode với giá $price...";
                                $this->sendMessageToTelegram($telegramToken, $chatId, $message);

                                $mqtt = new MqttClient(env('MQTT_HOST', 'localhost'), env('MQTT_PORT', '1884'), uniqid());
                                $connectionSettings = (new ConnectionSettings)
                                    ->setUsername(env('MQTT_USERNAME', 'user1'))
                                    ->setPassword(env('MQTT_PASSWORD', '12345678'));

                                $mqtt->connect($connectionSettings, true);
                                $mqtt->publish($machine->token . "/run", json_encode([
                                    'program_code' => $programCode,
                                    'time' => $time,
                                    'notify_slack' => false,
                                    'time_remaining' => $timeRemaining,
                                ]), 1);
                                $mqtt->disconnect();
                            } else {
                                $plan = $machine->machinePlans()->where('id', $currentProgramCode)->first();
                                if ($plan) {
                                    $timeRemaining = round($machine->time_remaining / 60);
                                    $timeUsed = round($plan->minute - $timeRemaining);
                                    $message = "⏳ Máy {$machine->name} đang giặt đồ. Thời gian đã giặt: {$timeUsed} phút. Bạn vui lòng chờ khoảng {$timeRemaining} phút nữa nhé.\n";
                                    $this->sendMessageToTelegram($telegramToken, $chatId, $message);
                                }
                            }
                        }
                    } else if($price == 'qr') {
                        $qrText = $machine->qrCode->qr;
                        $qrImage = QrCodeGenerator::format('png')->size(1000)->generate($qrText);

                        // Save the QR code image to the storage
                        $filename = 'qr_codes/' . uniqid() . '.png';
                        Storage::disk('public')->put($filename, $qrImage);

                        // Get the URL of the saved image
                        $qrImageUrl = url(asset(Storage::url($filename)));
                        $this->sendImageToTelegram($telegramToken, $chatId, $qrImageUrl, '');

                    } else {
                        $message = "⚠️ Không tìm thấy chương trình với giá $price cho máy $machineName.";
                        $this->sendMessageToTelegram($telegramToken, $chatId, $message);
                    }
                } else {
                    $message = "⚠️ Không tìm thấy máy $machineName tại $location.";
                    $this->sendMessageToTelegram($telegramToken, $chatId, $message);
                }
            } else {
                $message = "⚠️ Lệnh không hợp lệ.";
                $this->sendMessageToTelegram($telegramToken, $chatId, $message);
            }

            Log::info('Telegram Commands:', ['location' => $location]);
        }

        return response()->json(['status' => 'success']);
    }

    public function handleAdmin(Request $request)
    {
        Log::info('Telegram Webhook Data:', $request->all());

        $token = $request->input('token');
        $programCode = $request->input('program_code') ?? 0;
        $machine = Machine::where('token', $token)->first();
        $location = $machine ? $machine->location : null;
        $telegramChatId = $location ? $location->telegram_chat_id : null;
        $telegramToken = $location ? $location->telegram_bot_token : null;

        if ($machine) {
            Log::info('Machine found:', $machine->toArray());
            $machineName = $machine->name;
            $locationName = $location ? $location->name : 'unknown';
            if ($programCode != 0) {
                $machine->program_code = $programCode;
                $machine->time_remaining = $machine->machinePlans()->where('id', $programCode)->first()->minute * 60;
                $machine->save();
                $message = "✅ Máy giặt {$machineName} tại {$locationName} đã khởi động thành công.";
                MachineHistory::create([
                    'machine_id' => $machine->id,
                    'status' => 'Bật',
                    'changed_at' => now(),
                ]);
            }

//            else {
//                $machine->program_code = 0;
//                $machine->time_remaining = 0;
//                $machine->save();
//                $message = "✅ Máy giặt {$machineName} tại {$locationName} đã dừng.";
//                MachineHistory::create([
//                    'machine_id' => $machine->id,
//                    'status' => 'Tắt',
//                    'changed_at' => now(),
//                ]);
//            }
            $this->sendMessageToTelegram($telegramToken, $telegramChatId, $message);
        } else {
            Log::info('No machine found with the given token.');
        }
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

    private function sendImageToTelegram($telegramToken, $chatId, $imageUrl, $caption = '')
    {
        $telegramApiUrl = "https://api.telegram.org/bot{$telegramToken}/sendPhoto";
        $response = Http::post($telegramApiUrl, [
            'chat_id' => $chatId,
            'photo' => $imageUrl,
            'caption' => $caption,
        ]);

        return $response->json();
    }

    public function handleTokenGenerate(Request $request)
    {
        \Log::info('Token Generate Request:', $request->all());
        // Extract the Authorization header
        $authorizationHeader = $request->header('Authorization');
        if (!$authorizationHeader || !str_starts_with($authorizationHeader, 'Basic ')) {
            return response()->json(['status' => 'error', 'message' => 'Invalid Authorization header.'], 401);
        }

        // Decode the base64 encoded username:password
        $base64Credentials = substr($authorizationHeader, 6);
        $credentials = base64_decode($base64Credentials);
        list($username, $password) = explode(':', $credentials, 2);

        // Validate the username and password (this is just an example, implement your own validation logic)
//        if ($username !== 'your_username' || $password !== 'your_password') {
//            return response()->json(['status' => 'error', 'message' => 'Invalid credentials.'], 401);
//        }

        // Generate the JWT token
        $key = 'your_secret_key';
        $payload = [
            'iss' => config('app.url'), // Issuer
            'aud' => config('app.url'), // Audience
            'iat' => time(), // Issued at
            'exp' => time() + 300, // Expiration time (5 minutes)
            'sub' => $username, // Subject
        ];

        $jwt = JWT::encode($payload, $key, 'HS256');

        return response()->json([
            'access_token' => $jwt,
            'token_type' => 'Bearer',
            'expires_in' => 300
        ]);
    }
    public function handleTransactionSync(Request $request)
    {
        \Log::info('Transaction Sync Request:', $request->all());

        // Extract the data from the request
        $data = $request->all();

        // Convert the transaction time from milliseconds to a Carbon instance
        $transactionTime = Carbon::createFromTimestampMs($data['transactiontime'])->format('Y-m-d H:i:s');

        // Retrieve the machine_id from the terminalCode
        $machine = Machine::where('key', $data['terminalCode'])->first();
        $machine_id = $machine ? $machine->id : null;

        $matchingPlan = $machine ? $machine->machinePlans()->where('price', $data['amount'])->first() : null;
        if (!$machine) {
            $actions = 'Khong tim thay may';
        } elseif (!$matchingPlan) {
            $actions = 'Khong tim thay chuong trinh';
            $webHookUrl = $machine->location->slack_error_webhook ?? "";
            $message = "Machine: {$machine->name}, Charged Amount: {$data['amount']}, Charged Time: " . now()->toDayDateTimeString() . ", Connection Status: Connected, Start Status: Ready To Go, Run Status: Idle";
            $this->sendToSlack($webHookUrl, $message);
        } else {
            $actions = 'Chuong trinh: ' . $matchingPlan->name . " | May: " . $machine->name;
//            $webHookUrl = $machine->location->slack_success_webhook ?? "";
//            $message = "Machine: {$machine->name}, Charged Amount: {$data['amount']}, Charged Time: " . now()->toDayDateTimeString() . ", Connection Status: Connected, Start Status: Ready To Go, Run Status: Running";
//            $this->sendToSlack($webHookUrl, $message);
        }

        // Create a new Transaction record
        $existingTransaction = Transaction::where('third_party', $data['referencenumber'])->first();

        if (!$existingTransaction) {
            Transaction::create([
                'type' => $data['transType'], // Assuming 'C' is the type
                'third_party' => $data['referencenumber'], // Assuming 'FT25017122341033' is the third party
                'amount' => $data['amount'], // Assuming '2000' is the amount
                'description' => $data['content'], // Assuming 'QABCQS5566' is the description
                'transaction_time' => $transactionTime, // Converted transaction time
                'machine_id' => $machine_id,
                'actions' => $actions,
            ]);
        } else {
            \Log::info('Duplicate transaction detected:', ['third_party' => $data['referencenumber']]);
        }

        // Send a message to Telegram if a matching plan is found
        if ($matchingPlan && $data['transType'] == 'C' && !$existingTransaction) {
            $telegramToken = $matchingPlan->machine->location->telegram_bot_token ?? "";
            $chatId = $matchingPlan->machine->location->telegram_chat_id ?? "";
            $machineName = $machine->name;
            $location = $machine->location->name;
            $price = $data['amount'];

            $programCode = $matchingPlan->program_code;
            $currentProgramCode = $machine->program_code;
            $time = $matchingPlan ? $matchingPlan->minute * 60 : 0;
            $timeRemaining = $matchingPlan ? $time : 0;

            $currentTime = Carbon::now('Asia/Ho_Chi_Minh')->timestamp;
            $lastActiveTime = $machine->last_active_time;

            Log::info('Current time:', ['currentTime' => $currentTime]);
            Log::info('Last active time:', ['lastActiveTime' => $lastActiveTime]);

            $timeDifference = $currentTime - $lastActiveTime;
            Log::info('Time difference:', ['timeDifference' => $timeDifference]);

            if (abs($timeDifference) > 30) {
                // Machine is offline
                $message = "⚠️ Máy {$machine->name} đã offline.\n";
                $this->sendMessageToTelegram($telegramToken, $chatId, $message);
            } else {
                if ($currentProgramCode == 0) {
                    $message = "🚀 Đang khởi động máy Máy giặt $machineName tại $location, chương trình số $programCode với giá $price...";
                    $this->sendMessageToTelegram($telegramToken, $chatId, $message);

                    $mqtt = new MqttClient(env('MQTT_HOST', 'localhost'), env('MQTT_PORT', '1884'), uniqid());
                    $connectionSettings = (new ConnectionSettings)
                        ->setUsername(env('MQTT_USERNAME', 'user1'))
                        ->setPassword(env('MQTT_PASSWORD', '12345678'));

                    $mqtt->connect($connectionSettings, true);
                    $mqtt->publish($machine->token . "/run", json_encode([
                        'program_code' => $programCode,
                        'time' => $time,
                        'notify_slack' => true,
                        'time_remaining' => $timeRemaining,
                    ]), 1);
                    $mqtt->disconnect();
                } else {
                    $plan = $machine->machinePlans()->where('id', $currentProgramCode)->first();
                    if ($plan) {
                        $timeRemaining = round($machine->time_remaining / 60);
                        $timeUsed = round($plan->minute - $timeRemaining);
                        $message = "⏳ Máy {$machine->name} đang giặt đồ. Thời gian đã giặt: {$timeUsed} phút. Bạn vui lòng chờ khoảng {$timeRemaining} phút nữa nhé.\n";
                        $this->sendMessageToTelegram($telegramToken, $chatId, $message);
                    }
                }
            }
        }

        \Log::info('Transaction Sync Request:', $request->all());

        return response()->json([
            'error' => false,
            'errorReason' => '',
            'toastMessage' => '',
            'object' => [
                'reftransactionid' => time() . rand(1000, 9999) . '',
            ]
        ]);
    }

    public function sendToSlack($webhookUrl, $message) {
        $response = Http::post($webhookUrl, [
            'text' => $message,
        ]);

        return $response->json();
    }
}
