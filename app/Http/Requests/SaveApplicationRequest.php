<?php

namespace App\Http\Requests;

use App\Models\SavedJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return ['notes' => 'nullable|string|max:10000', 'application_status' => ['required', Rule::in(SavedJob::STATUSES)], 'applied_at' => 'nullable|date', 'interview_at' => 'nullable|date'];
    }
}
