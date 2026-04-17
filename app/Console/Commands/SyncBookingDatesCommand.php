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
            $booking->syncDates();
            $count++;
        }
        
        $this->info("Successfully synced dates for {$count} bookings.");
    }
}
