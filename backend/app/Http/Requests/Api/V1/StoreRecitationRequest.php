<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Recitation\Models\Recitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRecitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Recitation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer'],
            'recitation_date' => ['required', 'date_format:Y-m-d'],
            'surah_id' => ['required', 'integer', 'min:1'],
            'from_ayah' => ['required', 'integer', 'min:1'],
            'to_ayah' => ['required', 'integer', 'min:1'],
            'from_page' => ['nullable', 'integer', 'min:1'],
            'to_page' => ['nullable', 'integer', 'min:1'],
            'grade' => ['nullable', Rule::in(Recitation::GRADES)],
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
            'recitation_date.required' => 'تاريخ التسميع مطلوب.',
            'surah_id.required' => 'السورة مطلوبة.',
            'from_ayah.required' => 'بداية النطاق مطلوبة.',
            'to_ayah.required' => 'نهاية النطاق مطلوبة.',
        ];
    }
}
