<?php

use App\Models\Event;
use App\Models\Winner;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('it exports winners of events that ended yesterday', function () {
    Storage::fake('public');

    // 1. Create necessary related models
    $branch = \App\Models\Branch::create([
        'branch_code' => 'BR001',
        'branch_name' => 'Main Branch',
        'region' => 'Region 1',
        'status' => 'ACTIVE',
    ]);

    $event = Event::create([
        'event_code' => 'EVT001',
        'event_name' => 'Yesterday Event',
        'event_ended_at' => Carbon::yesterday()->toDateString(),
        'status' => Event::STATUS_COMPLETED,
    ]);

    $eventPrize = \App\Models\EventPrize::create([
        'event_id' => $event->id,
        'prize_id' => 1,
        'prize_tier' => 1,
        'status' => 'ACTIVE',
    ]);

    $customer = \App\Models\Customer::create([
        'name' => 'John Doe',
        'cif' => 'CIF001',
        'email' => 'john@example.com',
        'branch_id' => $branch->id,
        'status' => 'ACTIVE',
    ]);

    $account = \App\Models\Account::create([
        'account_number' => 'ACC001',
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'account_type' => 'SAVINGS',
        'status' => 'ACTIVE',
    ]);

    $participant = \App\Models\Participant::create([
        'event_id' => $event->id,
        'account_id' => $account->id,
        'participant_name' => 'John Doe',
        'participant_cif' => 'CIF001',
        'participant_account_number' => 'ACC001',
        'participant_email' => 'john@example.com',
        'participant_phone_number' => '08123456789',
        'total_points_snapshot' => 10,
        'range_start' => 1,
        'range_end' => 10,
        'status' => 'ACTIVE',
    ]);

    // 2. Create some winners for the event
    Winner::create([
        'participant_id' => $participant->id,
        'event_prize_id' => $eventPrize->id,
        'event_code' => 'EVT001',
        'participant_name' => 'John Doe',
        'participant_cif' => 'CIF001',
        'participant_account_number' => 'ACC001',
        'participant_email' => 'john@example.com',
        'participant_phone_number' => '08123456789',
        'prize_name' => 'Prize A',
        'prize_tier' => '1',
        'winning_number' => '12345',
        'status' => Winner::STATUS_PENDING,
        'drawn_at' => now(),
    ]);

    $yesterday = Carbon::yesterday()->toDateString();

    // 3. Run the command
    $this->artisan('app:export-winners')
        ->expectsOutput("Checking for events that ended on: {$yesterday}")
        ->expectsOutputToContain("Exported winners for event 'Yesterday Event'")
        ->assertExitCode(0);

    // 4. Assert file exists
    $files = Storage::disk('public')->files('exports/winners');
    expect($files)->toHaveCount(1);
    expect($files[0])->toContain('winners_EVT001');

    // 5. Assert content
    $content = Storage::disk('public')->get($files[0]);
    expect($content)->toContain('John Doe');
    expect($content)->toContain('CIF001');
    expect($content)->toContain('Prize A');
});

test('it does not export if no events ended yesterday', function () {
    Storage::fake('public');

    // Create an event that ended today
    Event::create([
        'event_code' => 'EVT002',
        'event_name' => 'Today Event',
        'event_ended_at' => Carbon::today()->toDateString(),
        'status' => Event::STATUS_ACTIVE,
    ]);

    $yesterday = Carbon::yesterday()->toDateString();

    $this->artisan('app:export-winners')
        ->expectsOutput("Checking for events that ended on: {$yesterday}")
        ->expectsOutput("No events found that ended on {$yesterday}.")
        ->assertExitCode(0);

    $files = Storage::disk('public')->files('exports/winners');
    expect($files)->toHaveCount(0);
});
