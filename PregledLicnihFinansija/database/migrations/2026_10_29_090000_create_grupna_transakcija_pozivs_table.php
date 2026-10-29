<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupna_transakcija_pozivi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupna_transakcija_id')->constrained('grupne_transakcije')->cascadeOnDelete();
            $table->foreignId('pozivalac_id')->constrained('klijenti')->cascadeOnDelete();
            $table->foreignId('pozvani_id')->constrained('klijenti')->cascadeOnDelete();
            $table->string('status')->default('na_cekanju');
            $table->timestamp('istice_at')->nullable();
            $table->timestamp('odgovoreno_at')->nullable();
            $table->timestamps();

            $table->index(['pozvani_id', 'status']);
            $table->index(['grupna_transakcija_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupna_transakcija_pozivi');
    }
};
