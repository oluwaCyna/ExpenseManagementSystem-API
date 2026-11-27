<?php

namespace App\Http\Resources;

use App\Traits\IncludeableResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    use IncludeableResource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at->toDateTimeString(),
            $this->mergeWhen($this->shouldInclude('company'), fn () => [
                'company' => new CompanyResource($this->whenLoaded('company')),
            ]),
            $this->mergeWhen($this->shouldInclude('expenses'), fn () => [
                'expenses' => ExpenseResource::collection($this->whenLoaded('expenses')),
            ]),
        ];
    }
}
