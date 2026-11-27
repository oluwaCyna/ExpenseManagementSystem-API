<?php

namespace App\Http\Resources;

use App\Traits\IncludeableResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    use IncludeableResource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'amount' => $this->amount,
            'category' => $this->category,
            $this->mergeWhen($this->shouldInclude('user'), fn () => [
                'user' => new UserResource($this->whenLoaded('user')),
            ]),
            $this->mergeWhen($this->shouldInclude('company'), fn () => [
                'company' => new CompanyResource($this->whenLoaded('company')),
            ]),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
