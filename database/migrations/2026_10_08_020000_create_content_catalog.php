<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contents', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20)->index();
            $table->json('payload');
            $table->boolean('published')->default(false)->index();
            $table->timestamps();
        });
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });
        Schema::create('packs', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });
        Schema::create('content_tag', function (Blueprint $table): void {
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['content_id', 'tag_id']);
        });
        Schema::create('pack_tag', function (Blueprint $table): void {
            $table->foreignId('pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->restrictOnDelete();
            $table->primary(['pack_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pack_tag');
        Schema::dropIfExists('content_tag');
        Schema::dropIfExists('packs');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('contents');
    }
};
