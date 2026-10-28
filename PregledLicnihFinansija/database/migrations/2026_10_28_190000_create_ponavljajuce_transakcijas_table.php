<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ponavljajuce_transakcije', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klijent_id')->constrained('klijenti')->onDelete('cascade');
            $table->foreignId('kategorija_id')->constrained('kategorije')->onDelete('cascade');
            $table->double('kolicina');
            $table->enum('ucestalost', ['nedeljno', 'mesecno', 'godisnje']);
            $table->date('sledeci_datum');
            $table->boolean('aktivna')->default(true);
            $table->timestamps();

            $table->index(['aktivna', 'sledeci_datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ponavljajuce_transakcije');
    }
};
