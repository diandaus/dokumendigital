<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tracking_tte_session', function (Blueprint $table) {
            if (!Schema::hasColumn('tracking_tte_session', 'payload')) {
                $table->text('payload')->nullable();
            }
            if (!Schema::hasColumn('tracking_tte_session', 'last_activity')) {
                $table->integer('last_activity')->nullable();
            }
            if (!Schema::hasColumn('tracking_tte_session', 'user_id')) {
                $table->string('user_id')->nullable();
            }
            if (!Schema::hasColumn('tracking_tte_session', 'ip_address')) {
                $table->string('ip_address', 45)->nullable();
            }
            if (!Schema::hasColumn('tracking_tte_session', 'user_agent')) {
                $table->text('user_agent')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('tracking_tte_session', function (Blueprint $table) {
            $table->dropColumn(['payload', 'last_activity', 'user_id', 'ip_address', 'user_agent']);
        });
    }
}; 