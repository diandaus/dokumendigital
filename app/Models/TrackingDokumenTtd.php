<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingDokumenTtd extends Model
{
    protected $table = 'tracking_dokumen_ttd';
    protected $primaryKey = 'id_tracking';
    public $timestamps = false;
    
    protected $fillable = [
        'no_rawat',
        'nama_dokumen',
        'tgl_kirim',
        'order_id',
        'status_ttd',
        'keterangan',
        'user_pengirim',
        'email_ttd'
    ];

    protected $casts = [
        'tgl_kirim' => 'datetime'
    ];
} 