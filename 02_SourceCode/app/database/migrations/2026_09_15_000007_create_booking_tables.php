<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đặt lịch tham quan (có giữ chỗ hết hạn) và giao dịch thanh toán thử nghiệm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();
            $table->foreignId('slot_id')->constrained('tour_slots')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('quantity');
            $table->unsignedInteger('unit_price_vnd');
            $table->unsignedInteger('total_vnd');
            $table->enum('status', ['held', 'pending_payment', 'paid', 'cancelled', 'expired', 'refunded'])->default('held');
            $table->dateTime('hold_expires_at')->nullable();
            $table->string('contact_name', 120);
            $table->string('contact_phone', 20);
            $table->string('contact_email', 190);
            $table->string('note', 500)->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['status', 'hold_expires_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('gateway', 32);                       // sandbox | vnpay_sandbox
            $table->string('txn_ref', 64)->unique();
            $table->unsignedInteger('amount_vnd');
            $table->char('currency', 3)->default('VND');
            $table->enum('status', ['initiated', 'succeeded', 'failed', 'cancelled', 'refunded'])->default('initiated');
            $table->json('gateway_payload')->nullable();
            $table->string('signature', 128)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bookings');
    }
};
