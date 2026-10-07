<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_content_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            // Preserve the source identifier even when an administrator deletes it.
            $table->unsignedBigInteger('content_id');
            $table->timestamp('revealed_at');
            $table->unique(['room_id', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_content_history');
    }
};
