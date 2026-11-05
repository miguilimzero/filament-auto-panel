<?php

namespace Miguilim\FilamentAutoPanel\Models;

use Illuminate\Database\Eloquent\Model;

class DatabaseSession extends Model
{
    protected $table = 'sessions';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $hidden = ['id', 'payload'];

    protected $casts = [
        'last_activity' => 'integer',
    ];
}
