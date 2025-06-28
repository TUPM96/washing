<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('log:everyminute', function () {
    $machines = \App\Models\Machine::all();

    foreach ($machines as $machine) {
        $response = \Http::get("https://thingboard.saveapp.cc/api/v1/{$machine->token}/attributes?clientKeys=program_code,time_remaining");
        \Log::info("Fetched attributes for machine ID {$machine->id}");
        if ($response->successful()) {
            $attributes = $response->json();
            \Log::info("Attributes for machine ID {$machine->id}: " . json_encode($attributes));
            $machine->program_code = $attributes['client']['program_code'] ?? 0;
            $machine->time_remaining = $attributes['client']['time_remaining'] ?? 0;
            $machine->save();
            \Log::info($machine);
        } else {
            \Log::error("Failed to fetch attributes for machine ID {$machine->id}");
        }
    }

    \Log::info('Updated program_code and time_remaining for all machines.');
})->purpose('Log a message every minute and update machine attributes')->everyMinute();
