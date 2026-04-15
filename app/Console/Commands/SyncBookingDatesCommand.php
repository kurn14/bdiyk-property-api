<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:sync-dates')]
#[Description('Sync start_date and end_date for current bookings from their schedules')]
class SyncBookingDatesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Finding bookings with empty start_date or end_date...');
        
        $bookings = \App\Models\Booking::whereNull('start_date')->orWhereNull('end_date')->get();
        
        $count = 0;
        foreach ($bookings as $booking) {
            $minStart = $booking->schedules()->min('start_time');
            $maxEnd = $booking->schedules()->max('end_time');
            
            if ($minStart && $maxEnd) {
                $booking->withoutEvents(function() use ($booking, $minStart, $maxEnd) {
                    $booking->update([
                        'start_date' => $minStart,
                        'end_date' => $maxEnd,
                    ]);
                });
                $count++;
            }
        }
        
        $this->info("Successfully synced dates for {$count} bookings.");
    }
}
