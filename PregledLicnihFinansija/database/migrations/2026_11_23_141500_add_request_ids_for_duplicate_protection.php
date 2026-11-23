<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transakcije', function (Blueprint $table) {
            $table->string('request_id', 64)->nullable()->after('valuta');
            $table->index(['klijent_id', 'request_id']);
        });

        Schema::table('subskripcije', function (Blueprint $table) {
            $table->string('request_id', 64)->nullable()->after('aktivna');
            $table->index(['klijent_id', 'request_id']);
        });

        Schema::table('ponavljajuce_transakcije', function (Blueprint $table) {
            $table->string('request_id', 64)->nullable()->after('aktivna');
            $table->index(['klijent_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transakcije', function (Blueprint $table) {
            $table->dropIndex(['klijent_id', 'request_id']);
            $table->dropColumn('request_id');
        });

        Schema::table('subskripcije', function (Blueprint $table) {
            $table->dropIndex(['klijent_id', 'request_id']);
            $table->dropColumn('request_id');
        });

        Schema::table('ponavljajuce_transakcije', function (Blueprint $table) {
            $table->dropIndex(['klijent_id', 'request_id']);
            $table->dropColumn('request_id');
        });
    }
};
