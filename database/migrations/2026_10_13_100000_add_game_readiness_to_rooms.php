<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->json('game_setup')->nullable();
            $table->unsignedInteger('setup_revision')->default(0);
        });
        Schema::table('room_players', function (Blueprint $table): void {
            $table->unsignedInteger('ready_revision')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('room_players', fn (Blueprint $table) => $table->dropColumn('ready_revision'));
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn(['game_setup', 'setup_revision']));
    }
};
