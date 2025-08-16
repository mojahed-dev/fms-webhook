<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('alert_id')->constrained('alerts')->cascadeOnDelete();
            $t->string('to_msisdn');
            $t->string('template_code');
            $t->string('language')->default('ar');
            $t->string('status')->default('pending'); // pending|sent|failed|delivered|read
            $t->string('provider_msg_id')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->index(['status','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('messages'); }
};
