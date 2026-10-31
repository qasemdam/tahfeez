<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->whenLoaded('teacher', fn () => $this->teacher?->name),
            'students_count' => $this->whenCounted(
                'activeStudents',
                fn () => (int) $this->active_students_count,
            ),
            'students' => $this->whenLoaded('activeStudents', fn () => $this->activeStudents
                ->map(fn ($student) => [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'status' => $student->status,
                    'grade_level' => $student->grade_level,
                    'joined_at' => $student->pivot?->joined_at,
                ])
                ->values()),
            'notes' => $this->notes,
        ];
    }
}
