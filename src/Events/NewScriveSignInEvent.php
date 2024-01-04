<?php

namespace KalnaLab\Scrive\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KalnaLab\Scrive\Resources\CompletionData\CompletionData;

class NewScriveSignInEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CompletionData $payload)
    {
        session(['ScriveCompletionData' => $payload]);
    }
}
