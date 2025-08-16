<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('alerts', function (Blueprint $t) {
            $t->id();
            $t->string('idempotency_key')->unique();
            $t->string('event_id')->nullable();
            $t->string('vehicle_id');
            $t->string('customer_id')->nullable();
            $t->string('alert_type');
            $t->timestamp('occurred_at')->nullable();
            $t->json('payload')->nullable();
            $t->timestamps();
            $t->index(['alert_type','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('alerts'); }
};
