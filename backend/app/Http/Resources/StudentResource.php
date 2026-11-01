<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'full_name' => $this->full_name,
            'status' => $this->status,
            'grade_level' => $this->grade_level,
            'guardian_name' => $this->guardian_name,
            'enrolled_at' => $this->enrolled_at?->toDateString(),
            'teacher_name' => $this->whenLoaded(
                'activeAssignment',
                fn () => $this->activeAssignment?->teacher?->name,
            ),
            'version' => $this->version,
        ];
    }
}
