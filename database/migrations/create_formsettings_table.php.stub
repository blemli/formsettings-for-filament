<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('formsettings-for-filament.table', 'formsettings'), function (Blueprint $table) {
            $table->id();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('key');
            $table->string('preset')->nullable();
            $table->json('settings');
            $table->boolean('published')->default(false);
            $table->timestamps();

            $table->index(['user_type', 'user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('formsettings-for-filament.table', 'formsettings'));
    }
};
