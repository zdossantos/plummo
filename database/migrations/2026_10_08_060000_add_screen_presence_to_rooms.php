<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', fn (Blueprint $table) => $table->timestamp('screen_seen_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn('screen_seen_at'));
    }
};
