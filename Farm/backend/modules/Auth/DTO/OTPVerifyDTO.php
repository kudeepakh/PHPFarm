<?php

namespace PHPFrarm\Modules\Auth\DTO;

/**
 * OTP Verification DTO
 */
class OTPVerifyDTO
{
    public string $identifier;
    public string $otp;
    public string $purpose;

    public function __construct(array $data)
    {
        $this->identifier = $data['identifier'] ?? '';
        $this->otp = $data['otp'] ?? '';
        $this->purpose = $data['purpose'] ?? 'login';
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->identifier)) {
            $errors[] = 'Identifier is required';
        }

        if (empty($this->otp)) {
            $errors[] = 'OTP is required';
        }

        $allowedPurposes = ['registration', 'login', 'password_reset', 'verification', 'phone_verification', 'email_verification', 'two_factor'];
        if (empty($this->purpose) || !in_array($this->purpose, $allowedPurposes, true)) {
            $errors[] = 'Purpose must be one of: ' . implode(', ', $allowedPurposes);
        }

        return $errors;
    }
}
