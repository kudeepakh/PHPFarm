<?php

namespace PHPFrarm\Modules\Auth\DTO;

/**
 * OTP Request DTO
 */
class OTPRequestDTO
{
    public string $identifier;
    public string $type;
    public string $purpose;

    public function __construct(array $data)
    {
        $this->identifier = $data['identifier'] ?? '';
        $rawType = $data['type'] ?? '';
        $this->type = $rawType === 'sms' ? 'phone' : $rawType;
        $this->purpose = $data['purpose'] ?? 'login';
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->identifier)) {
            $errors[] = 'Identifier is required';
        }

        if (empty($this->type) || !in_array($this->type, ['email', 'phone'], true)) {
            $errors[] = 'Type must be email, phone, or sms';
        }

        return $errors;
    }
}
