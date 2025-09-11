<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender_id' => (int) $this->sender_id,
            'receiver_id' => (int) $this->receiver_id,
            'message' => $this->message,
            'attachments' => $this->attachments,
            'is_read' => (bool) $this->is_read,
            // 'deleted_by_sender' => (bool) $this->deleted_by_sender,
            // 'deleted_by_receiver' => (bool) $this->deleted_by_receiver,
            'created_date' => $this->created_at ? $this->created_at->format('Y-m-d') : null,
            'created_time' => $this->created_at ? $this->created_at->format('h:i A') : null,
            // 'updated_date' => $this->updated_at ? $this->updated_at->format('Y-m-d') : null,
            // 'updated_time' => $this->updated_at ? $this->updated_at->format('h:i A') : null,
            'time_ago' => $this->created_at ? $this->created_at->diffForHumans() : null,
            'sent_by_me' => auth()->id() === $this->sender_id,
            'is_edited' => !is_null($this->edited_at),
        ];
    }
}
