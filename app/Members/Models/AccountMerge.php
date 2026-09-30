<?php

namespace App\Members\Models;

use Illuminate\Database\Eloquent\Model;

/** One account folded into another — the ledger App\Members\Support\AccountMerger::undo() reads. */
class AccountMerge extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'moved' => 'array',
        'removed' => 'array',
        'user_before' => 'array',
        'undone_at' => 'datetime',
    ];
}
