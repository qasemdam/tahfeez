<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student_name' => $this->whenLoaded(
                'student',
                fn () => $this->student?->full_name,
            ),
            'group_id' => $this->group_id,
            'group_name' => $this->whenLoaded(
                'group',
                fn () => $this->group?->name,
            ),
            'attendance_date' => $this->attendance_date?->toDateString(),
            'status' => $this->status,
            'late_minutes' => $this->late_minutes,
            'notes' => $this->notes,
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->whenLoaded(
                'teacher',
                fn () => $this->teacher?->name,
            ),
            'version' => $this->version,
        ];
    }
}
