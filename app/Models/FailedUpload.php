<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedUpload extends Model
{
    protected $fillable = [
        'filename',
        'local_path',
        'target_directory',
        'error_message',
        'metadata',
        'status',
    ];

    protected $casts = [
        'metadata' => 'json',
    ];
}
