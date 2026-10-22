<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mô phỏng tình huống (thách thức nâng cao): kịch bản và lần chạy; gắn khóa ngoại alerts.scenario_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 190);
            $table->text('description')->nullable();
            $table->foreignId('hazard_type_id')->constrained('hazard_types')->restrictOnDelete();
            $table->unsignedTinyInteger('severity');
            $table->json('zone_codes');
            $table->unsignedSmallInteger('duration_hours')->default(24);
            $table->json('payload')->nullable();                        // {name:{vi,en}, description:{vi,en}, steps:[...]}
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['ready', 'running', 'finished'])->default('ready');
            $table->timestamps();
        });

        Schema::create('simulation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained('simulation_scenarios')->cascadeOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('alerts_created')->default(0);
            $table->unsignedInteger('matches_created')->default(0);
            $table->unsignedInteger('notifications_created')->default(0);
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->index(['scenario_id', 'started_at']);
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->foreign('scenario_id')->references('id')->on('simulation_scenarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropForeign(['scenario_id']);
        });
        Schema::dropIfExists('simulation_runs');
        Schema::dropIfExists('simulation_scenarios');
    }
};
