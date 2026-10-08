<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_players', function (Blueprint $table): void {
            $table->string('chat_message', 80)->nullable();
            $table->timestamp('chat_sent_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('room_players', fn (Blueprint $table) => $table->dropColumn(['chat_message', 'chat_sent_at']));
    }
};
