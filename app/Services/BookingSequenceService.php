<?php

namespace App\Services;

use App\Models\BookingSequence;
use Illuminate\Support\Facades\DB;

class BookingSequenceService
{
    public function generate(): string
    {
        return DB::transaction(function () {
            $today = now()->toDateString();
            
            $sequence = BookingSequence::lockForUpdate()
                ->where('sequence_date', $today)
                ->first();
            
            if (!$sequence) {
                $sequence = BookingSequence::create([
                    'sequence_date' => $today,
                    'last_sequence' => 0,
                ]);
            }
            
            $sequence->increment('last_sequence');
            
            // Format: BK-20240115-000001
            return sprintf(
                'BK-%s-%06d',
                str_replace('-', '', $today),
                $sequence->last_sequence
            );
        });
    }
}