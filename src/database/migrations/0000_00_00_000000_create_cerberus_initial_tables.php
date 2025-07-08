<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cerberus_user_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->morphs('authenticatable');
            $table->string('device_fingerprint', 100)->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('os_version', 30)->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('is_trusted')->default(false);

            $table->timestamps();
        });

        Schema::create('cerberus_user_device_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_id');
            $table->foreign('device_id')->references('id')->on('cerberus_user_devices')->cascadeOnDelete();
            $table->string('access_token');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cerberus_user_device_sessions');
        Schema::dropIfExists('cerberus_user_devices');
    }
};
