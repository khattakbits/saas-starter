<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;

class DeletedDocument extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'original_docstatus' => 'integer'];
    }
}
