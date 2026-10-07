<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 6)->unique();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamp('empty_since')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('room_players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('identity_hash', 64)->unique();
            $table->string('name', 30);
            $table->string('color', 20);
            $table->json('accessories');
            $table->unsignedInteger('score')->default(0);
            $table->timestamp('last_seen_at');
            $table->timestamp('connected_since', 6);
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->boolean('waiting')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_players');
        Schema::dropIfExists('rooms');
    }
};
