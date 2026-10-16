<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RestoreConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-restore-points') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string'],
        ];
    }

    /**
     * Require the administrator to type the snapshot name, as required by
     * config/restore-points.php (require_confirmation_text).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $snapshot = $this->route('snapshot');

            if ($snapshot !== null && $this->input('confirmation') !== $snapshot->name) {
                $validator->errors()->add('confirmation', 'Type the restore point name exactly to confirm.');
            }
        });
    }
}
