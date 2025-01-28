<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('documents', function (Blueprint $table) {
            // Sesuaikan dengan tipe data id di tabel pegawai (int)
            $table->integer('pegawai_id')->nullable();
            
            // Tambahkan foreign key yang mereferensi ke id pegawai
            $table->foreign('pegawai_id')
                  ->references('id')
                  ->on('pegawai')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['pegawai_id']);
            $table->dropColumn('pegawai_id');
        });
    }
}; 