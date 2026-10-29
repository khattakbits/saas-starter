<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentView extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }
}
