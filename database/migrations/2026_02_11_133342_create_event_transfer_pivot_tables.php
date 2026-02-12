<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('event_lottery_ticket');
        Schema::dropIfExists('event_participant');

        Schema::create('event_participant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('participant_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['event_id', 'participant_id']);
        });

        Schema::create('event_lottery_ticket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('lottery_ticket_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['event_id', 'lottery_ticket_id']);
        });

        // Seed initial data from existing event_id columns using bulk insert for efficiency
        $now = now();

        DB::table('participants')
            ->whereNotNull('event_id')
            ->select('id', 'event_id', 'id as participant_id', DB::raw("COALESCE(created_at, '{$now}') as created_at"), DB::raw("COALESCE(updated_at, '{$now}') as updated_at"))
            ->chunkById(1000, function ($rows) {
                // Remove the 'id' column before insert to avoid primary key conflict or unnecessary data
                $data = $rows->map(function ($row) {
                    $arr = (array) $row;
                    unset($arr['id']);
                    return $arr;
                })->toArray();
                DB::table('event_participant')->insert($data);
            }, 'id');

        DB::table('lottery_tickets')
            ->whereNotNull('event_id')
            ->select('id', 'event_id', 'id as lottery_ticket_id', DB::raw("COALESCE(created_at, '{$now}') as created_at"), DB::raw("COALESCE(updated_at, '{$now}') as updated_at"))
            ->chunkById(1000, function ($rows) {
                $data = $rows->map(function ($row) {
                    $arr = (array) $row;
                    unset($arr['id']);
                    return $arr;
                })->toArray();
                DB::table('event_lottery_ticket')->insert($data);
            }, 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_lottery_ticket');
        Schema::dropIfExists('event_participant');
    }
};
