<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchJobsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:200', 'company' => 'nullable|string|max:150', 'country' => 'nullable|string|max:100', 'city' => 'nullable|string|max:100', 'location' => 'nullable|string|max:150',
            'workplace' => ['nullable', Rule::in(['remote', 'hybrid', 'onsite', 'unknown'])], 'type' => ['nullable', Rule::in(['full_time', 'part_time', 'contract', 'freelance', 'internship', 'permanent', 'unknown'])],
            'experience' => ['nullable', Rule::in(['junior', 'senior', 'internship', 'unknown'])], 'source' => 'nullable|integer|exists:job_sources,id',
            'salary_min' => 'nullable|numeric|min:0|max:999999999', 'salary_max' => 'nullable|numeric|min:0|max:999999999|gte:salary_min',
            'currency' => 'nullable|string|regex:/^[A-Z]{3}$/', 'period' => ['nullable', Rule::in(['year', 'month', 'hour'])],
            'days' => ['nullable', Rule::in([1, 7, 30, 90])], 'saved' => ['nullable', Rule::in(['yes', 'no'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'relevance'])], 'page' => 'nullable|integer|min:1|max:10000',
        ];
    }
}
