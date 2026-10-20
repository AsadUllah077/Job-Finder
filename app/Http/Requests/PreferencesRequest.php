<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'keywords' => 'required|string|max:1000', 'locations' => 'nullable|string|max:1000', 'remote_only' => 'boolean', 'worldwide' => 'boolean',
            'minimum_salary' => 'nullable|numeric|min:0|max:999999999', 'salary_currency' => 'required|regex:/^[A-Z]{3}$/',
            'experience_level' => ['nullable', Rule::in(['junior', 'senior', 'internship'])],
            'preferred_job_types' => 'nullable|array|max:7', 'preferred_job_types.*' => Rule::in(['full_time', 'part_time', 'contract', 'freelance', 'permanent', 'internship']),
            'email_notifications_enabled' => 'boolean', 'alert_frequency' => ['required', Rule::in(['daily', 'immediate'])],
            'weights' => 'required|array:keywords,location,salary,remote', 'weights.keywords' => 'required|integer|min:1|max:100',
            'weights.location' => 'required|integer|min:0|max:100', 'weights.salary' => 'required|integer|min:0|max:100', 'weights.remote' => 'required|integer|min:0|max:100',
        ];
    }
}
