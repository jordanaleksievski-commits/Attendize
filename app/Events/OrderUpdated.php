<?php

namespace App\Events;

use App\Models\Pos\PosOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PosOrder $order;

    public function __construct(PosOrder $order)
    {
        $this->order = $order->load(['items', 'table']);
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('pos.orders.' . $this->order->id);
    }

    public function broadcastWith(): array
    {
        return [
            'order' => $this->order->toArray(),
        ];
    }
}
