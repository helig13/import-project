<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImportProgressUpdated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $totalFileRows;
    public $skippedRows;
    public $duplicates;
    public $timeTaken;
    public $duplicatesCumulative;
    public $skippedRowsCumulative;
    public $totalFileRowsCumulative;


    public function __construct($totalFileRows, $skippedRows, $duplicates, $timeTaken, $duplicatesCumulative, $skippedRowsCumulative, $totalFileRowsCumulative)
    {
        $this->totalFileRows = $totalFileRows;
        $this->skippedRows = $skippedRows;
        $this->duplicates = $duplicates;
        $this->timeTaken = $timeTaken;
    }

    public function broadcastWith()
    {
        return [
            'totalFileRows' => $this->totalFileRows,
            'skippedRows' => $this->skippedRows,
            'duplicates' => $this->duplicates,
            'processingTime' => $this->timeTaken,
            'duplicatesCumulative' => $this->duplicates + Cache::get('duplicates'),
            'skippedRowsCumulative' => $this->skippedRows + Cache::get('skippedRows'),
            'totalFileRowsCumulative' => $this->totalFileRows + Cache::get('totalFileRows'),
        ];

    }


    public function broadcastOn()
    {
        return new Channel('import-progress');
    }
}
