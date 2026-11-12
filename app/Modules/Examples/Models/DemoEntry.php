<?php

namespace App\Modules\Examples\Models;

use App\Core\Models\Concerns\IsDocument;
use Illuminate\Database\Eloquent\Model;
use KhattakBits\Core\Exceptions\DocumentException;

class DemoEntry extends Model
{
    use IsDocument;

    protected $table = 'demo_entries';

    protected $fillable = ['party', 'amount', 'posting_date', 'remarks'];

    protected function casts(): array
    {
        return [
            'docstatus' => 'integer',
            'amount' => 'decimal:2',
            'posting_date' => 'date',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected function beforeSubmit(): void
    {
        if ((float) $this->amount <= 0) {
            throw new DocumentException('Amount must be greater than zero before submitting.');
        }
    }
}
