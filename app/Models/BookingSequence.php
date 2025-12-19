<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingSequence extends Model
{
    protected $fillable = [
        'sequence_date',
        'last_sequence',
    ];
}
