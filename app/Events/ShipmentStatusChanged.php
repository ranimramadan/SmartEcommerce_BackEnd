<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
// app/Events/ShipmentStatusChanged.php
// namespace App\Events;
// use App\Models\Shipment;
// use Illuminate\Foundation\Events\Dispatchable;

// class ShipmentStatusChanged
// {
//     use Dispatchable;
//     public function __construct(public Shipment $shipment, public string $eventCode) {}
// }
// <?php
// namespace App\Notifications;

// use App\Models\Shipment;
// use Illuminate\Bus\Queueable;
// use Illuminate\Notifications\Notification;

// class ShipmentStatusChanged extends Notification
// {
//     use Queueable;

//     public function __construct(public Shipment $shipment, public string $eventCode) {}

//     public function via($notifiable): array
//     {
//         return ['database']; // أو ['mail','database']
//     }

//     public function toArray($notifiable): array
//     {
//         return [
//             'shipment_id'  => $this->shipment->id,
//             'status'       => $this->shipment->status,
//             'event_code'   => $this->eventCode,
//             'order_number' => $this->shipment->order?->order_number,
//             'tracking'     => $this->shipment->tracking_number,
//         ];
//     }
// }
