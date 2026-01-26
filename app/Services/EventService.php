<?php

namespace App\Services;

use App\Models\Event;

class EventService
{
    public function calculateTickets($event_id)
    {
        // Logic to run background service and return list of accounts
        $event = Event::find($event_id);
    }
}