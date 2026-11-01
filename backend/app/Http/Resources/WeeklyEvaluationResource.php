<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyEvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $result = is_array($this->result_snapshot) ? $this->result_snapshot : [];

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
            'week_key' => $this->week_key?->toDateString(),
            'week_start_date' => $this->week_start_date?->toDateString(),
            'week_end_date' => $this->week_end_date?->toDateString(),
            'weekly_evaluation_start_day' => $this->weekly_evaluation_start_day,
            'evaluation_profile_version_id' => $this->evaluation_profile_version_id,
            'evaluation_profile_version' => $this->evaluation_profile_version,
            'template_version_id' => $this->template_version_id,
            'required_pages' => $this->required_pages,
            'actual_pages' => $this->actual_pages,
            'achievement_percentage' => $this->achievement_percentage,
            'weighted_score' => $this->weighted_score,
            'is_complete' => $this->is_complete,
            'status' => $this->status,
            'blocked_criteria' => $result['blocked_criteria'] ?? [],
            'components' => $result['components'] ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
