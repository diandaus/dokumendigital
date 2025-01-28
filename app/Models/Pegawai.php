<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    protected $table = 'pegawai';
    protected $primaryKey = 'id';
    public $timestamps = false; // karena tidak ada kolom created_at dan updated_at
    
    protected $fillable = [
        'nik',
        'nama',
        'email',
        // ... field lainnya sesuai kebutuhan
    ];

    public function documents()
    {
        return $this->hasMany(Document::class, 'pegawai_id');
    }
} 