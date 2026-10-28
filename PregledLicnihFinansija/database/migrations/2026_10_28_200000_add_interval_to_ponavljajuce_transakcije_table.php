<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ponavljajuce_transakcije', function (Blueprint $table) {
            $table->unsignedSmallInteger('interval_vrednost')->default(1)->after('ucestalost');
        });
    }

    public function down(): void
    {
        Schema::table('ponavljajuce_transakcije', function (Blueprint $table) {
            $table->dropColumn('interval_vrednost');
        });
    }
};
