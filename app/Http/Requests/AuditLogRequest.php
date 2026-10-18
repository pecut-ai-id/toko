<?php

namespace App\Http\Requests;

use App\Enums\AuditAction;
use App\Http\Requests\Concerns\FiltersByPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogRequest extends FormRequest
{
    use FiltersByPeriod;

    /**
     * Otorisasi ditangani `AuditLogPolicy` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->periodRules(),
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'user_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->periodMessages(),
            'action.enum' => 'Jenis aktivitas tidak dikenal.',
            'user_id.integer' => 'Filter pengguna tidak valid.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return $this->periodAfter();
    }

    public function auditAction(): ?AuditAction
    {
        $value = $this->query('action');

        return is_string($value) ? AuditAction::tryFrom($value) : null;
    }

    public function userId(): ?int
    {
        $value = $this->query('user_id');

        return is_numeric($value) ? (int) $value : null;
    }
}
