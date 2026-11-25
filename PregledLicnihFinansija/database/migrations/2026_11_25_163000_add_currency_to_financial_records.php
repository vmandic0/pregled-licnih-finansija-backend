<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ponavljajuce_transakcije', function (Blueprint $table) {
            $table->string('valuta', 3)->default('RSD');
        });

        Schema::table('krediti', function (Blueprint $table) {
            $table->string('valuta', 3)->default('RSD');
        });

        Schema::table('limiti', function (Blueprint $table) {
            $table->string('valuta', 3)->default('RSD');
        });

        Schema::table('grupne_transakcije', function (Blueprint $table) {
            $table->string('valuta', 3)->default('RSD');
        });

        Schema::table('udeli_u_grupnoj_transakciji', function (Blueprint $table) {
            $table->string('valuta', 3)->default('RSD');
        });

        // Existing transaction values were already treated as RSD by balance and display code.
        DB::table('transakcije')->update(['valuta' => 'RSD']);
    }

    public function down(): void
    {
        Schema::table('udeli_u_grupnoj_transakciji', function (Blueprint $table) {
            $table->dropColumn('valuta');
        });

        Schema::table('grupne_transakcije', function (Blueprint $table) {
            $table->dropColumn('valuta');
        });

        Schema::table('limiti', function (Blueprint $table) {
            $table->dropColumn('valuta');
        });

        Schema::table('krediti', function (Blueprint $table) {
            $table->dropColumn('valuta');
        });

        Schema::table('ponavljajuce_transakcije', function (Blueprint $table) {
            $table->dropColumn('valuta');
        });
    }
};
