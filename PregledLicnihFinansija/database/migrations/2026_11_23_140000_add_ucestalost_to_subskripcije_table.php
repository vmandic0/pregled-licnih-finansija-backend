<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subskripcije', function (Blueprint $table) {
            $table->enum('ucestalost', ['mesecno', 'godisnje'])->default('mesecno')->after('dan_naplate');
        });
    }

    public function down(): void
    {
        Schema::table('subskripcije', function (Blueprint $table) {
            $table->dropColumn('ucestalost');
        });
    }
};
