<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use SoftDeletes;

    protected $table = 'settings';

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];

    public const GROUP_GENERAL = 'general';
    public const GROUP_DRAWING = 'drawing';

    public const GROUP = [
        self::GROUP_GENERAL => 'General',
        self::GROUP_DRAWING => 'Drawing',
    ];

    public const TYPE_STRING = 'string';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_JSON = 'json';
    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE = [
        self::TYPE_STRING => 'String',
        self::TYPE_INTEGER => 'Integer',
        self::TYPE_JSON => 'JSON',
        self::TYPE_BOOLEAN => 'Boolean',
    ];
}
