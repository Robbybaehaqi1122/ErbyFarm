<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('organization')->nullable()->after('email');
            $table->string('phone')->nullable()->after('organization');
            $table->text('address')->nullable()->after('phone');
            $table->string('state')->nullable()->after('address');
            $table->string('zip_code')->nullable()->after('state');
            $table->string('country')->nullable()->after('zip_code');
            $table->string('language')->nullable()->after('country');
            $table->string('timezone')->nullable()->after('language');
            $table->string('currency')->nullable()->after('timezone');
            $table->string('avatar_url')->nullable()->after('currency');
            $table->boolean('is_active')->default(true)->after('avatar_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'organization',
                'phone',
                'address',
                'state',
                'zip_code',
                'country',
                'language',
                'timezone',
                'currency',
                'avatar_url',
                'is_active',
            ]);
        });
    }
};
