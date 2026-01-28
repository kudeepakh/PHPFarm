<?php

namespace PHPFrarm\Modules\Auth\DTO;

/**
 * Registration Request DTO
 * 
 * Supports unified registration with email AND/OR phone
 * At least one identifier (email or phone) is required
 */
class RegisterRequestDTO
{
    public ?string $email;
    public ?string $phone;
    public string $password;
    public ?string $firstName;
    public ?string $lastName;

    public function __construct(array $data)
    {
        $this->email = !empty($data['email']) ? trim($data['email']) : null;
        $this->phone = !empty($data['phone']) ? trim($data['phone']) : null;
        $this->password = $data['password'] ?? '';
        // Support both camelCase and snake_case for flexibility
        $this->firstName = $data['firstName'] ?? $data['first_name'] ?? null;
        $this->lastName = $data['lastName'] ?? $data['last_name'] ?? null;
    }

    public function validate(): array
    {
        $errors = [];

        // At least one identifier is required
        $hasEmail = !empty($this->email);
        $hasPhone = !empty($this->phone);
        
        if (!$hasEmail && !$hasPhone) {
            $errors[] = 'At least one identifier (email or phone) is required';
        }

        // Validate email format if provided
        if ($hasEmail && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        // Validate phone format if provided
        if ($hasPhone && !preg_match('/^\+?[1-9]\d{6,14}$/', preg_replace('/[\s\-\(\)]/', '', $this->phone))) {
            $errors[] = 'Invalid phone number format';
        }

        if (empty($this->password) || strlen($this->password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        return $errors;
    }
}
