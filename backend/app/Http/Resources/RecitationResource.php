<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecitationResource extends JsonResource
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
            'teacher_id' => $this->teacher_id,
            'teacher_name' => $this->whenLoaded(
                'teacher',
                fn () => $this->teacher?->name,
            ),
            'recitation_date' => $this->recitation_date?->toDateString(),
            'surah_id' => $this->surah_id,
            'surah_name' => $this->surah_name,
            'from_ayah' => $this->from_ayah_number,
            'to_ayah' => $this->to_ayah_number,
            'from_ayah_id' => $this->from_ayah_id,
            'to_ayah_id' => $this->to_ayah_id,
            'from_page' => $this->from_page,
            'to_page' => $this->to_page,
            'grade' => $this->grade,
            'hifz_errors' => $this->hifz_errors,
            'tajweed_errors' => $this->tajweed_errors,
            'reading_errors' => $this->reading_errors,
            'notes' => $this->notes,
            'version' => $this->version,
        ];
    }
}
