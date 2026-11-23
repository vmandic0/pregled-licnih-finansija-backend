<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subskripcije', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klijent_id')->constrained('klijenti')->onDelete('cascade');
            $table->foreignId('kategorija_id')->constrained('kategorije')->onDelete('cascade');
            $table->string('naziv');
            $table->double('iznos');
            $table->string('valuta', 3);
            $table->unsignedTinyInteger('dan_naplate');
            $table->date('sledeci_datum');
            $table->boolean('aktivna')->default(true);
            $table->timestamps();

            $table->index(['aktivna', 'sledeci_datum']);
        });

        Schema::table('transakcije', function (Blueprint $table) {
            $table->string('valuta', 3)->nullable()->after('datum');
        });
    }

    public function down(): void
    {
        Schema::table('transakcije', function (Blueprint $table) {
            $table->dropColumn('valuta');
        });
        Schema::dropIfExists('subskripcije');
    }
};
