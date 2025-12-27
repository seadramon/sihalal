<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class JotformSync extends Model
{
    use HasUuids;

    protected $table = 'jotform_syncs';

    protected $keyType = 'string';
    
    public $incrementing = false;

    protected $fillable = [
        'nama_lengkap',
        'email',
        'nama_sppg',
        'alamat_sppg',
        'status_submit',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];
}
