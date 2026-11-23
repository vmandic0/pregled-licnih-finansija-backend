<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transakcije', function (Blueprint $table) {
            $table->decimal('kolicina', 15, 2)->change();
        });
        Schema::table('subskripcije', function (Blueprint $table) {
            $table->decimal('iznos', 15, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('transakcije', function (Blueprint $table) {
            $table->double('kolicina')->change();
        });
        Schema::table('subskripcije', function (Blueprint $table) {
            $table->double('iznos')->change();
        });
    }
};
