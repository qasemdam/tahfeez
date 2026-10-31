<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Review\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Review::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer'],
            'review_date' => ['required', 'date_format:Y-m-d'],
            'amount_source' => ['required', Rule::in(Review::SOURCES)],
            'amount_pages' => ['nullable', 'numeric', 'min:0'],
            'surah_id' => ['nullable', 'integer', 'min:1'],
            'from_ayah' => ['nullable', 'integer', 'min:1'],
            'to_ayah' => ['nullable', 'integer', 'min:1'],
            'from_page' => ['nullable', 'integer', 'min:1'],
            'to_page' => ['nullable', 'integer', 'min:1'],
            'grade' => ['nullable', Rule::in(Review::GRADES)],
            'hifz_errors' => ['nullable', 'integer', 'min:0'],
            'tajweed_errors' => ['nullable', 'integer', 'min:0'],
            'reading_errors' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $source = $this->input('amount_source');

            if ($source === Review::SOURCE_MANUAL && ! $this->filled('amount_pages')) {
                $validator->errors()->add('amount_pages', 'المقدار مطلوب في الوضع اليدوي.');
            }

            if ($source === Review::SOURCE_CALCULATED) {
                foreach (['surah_id', 'from_ayah', 'to_ayah'] as $field) {
                    if (! $this->filled($field)) {
                        $validator->errors()->add($field, 'هذا الحقل مطلوب للحساب التلقائي.');
                    }
                }
            }

            if ($this->filled('from_ayah') && $this->filled('to_ayah')
                && (int) $this->input('from_ayah') > (int) $this->input('to_ayah')) {
                $validator->errors()->add('from_ayah', 'بداية النطاق بعد نهايته.');
            }

            if ($this->filled('from_page') && $this->filled('to_page')
                && (int) $this->input('from_page') > (int) $this->input('to_page')) {
                $validator->errors()->add('from_page', 'بداية الصفحة بعد نهايتها.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'اختر الطالب.',
            'review_date.required' => 'تاريخ المراجعة مطلوب.',
            'amount_source.required' => 'مصدر المقدار مطلوب.',
        ];
    }
}
