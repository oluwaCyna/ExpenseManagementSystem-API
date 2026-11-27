<?php

namespace App\Http\Resources;

use App\Traits\IncludeableResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    use IncludeableResource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'currency' => $this->currency,
            $this->mergeWhen($this->shouldInclude('users'), fn () => [
                'users' => UserResource::collection($this->whenLoaded('users')),
            ]),
            $this->mergeWhen($this->shouldInclude('expenses'), fn () => [
                'expenses' => ExpenseResource::collection($this->whenLoaded('expenses')),
            ]),
        ];
    }
}
