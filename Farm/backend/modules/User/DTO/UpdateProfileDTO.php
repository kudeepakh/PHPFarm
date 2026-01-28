<?php

namespace PHPFrarm\Modules\User\DTO;

/**
 * Update Profile Request DTO
 */
class UpdateProfileDTO
{
    public ?string $firstName;
    public ?string $lastName;

    public function __construct(array $data)
    {
        // Support both camelCase (JSON) and snake_case (PHP) for flexibility
        $this->firstName = $data['firstName'] ?? $data['first_name'] ?? null;
        $this->lastName = $data['lastName'] ?? $data['last_name'] ?? null;
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->firstName !== null && strlen($this->firstName) < 2) {
            $errors[] = 'First name must be at least 2 characters';
        }

        if ($this->lastName !== null && strlen($this->lastName) < 2) {
            $errors[] = 'Last name must be at least 2 characters';
        }

        return $errors;
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->firstName !== null) $data['first_name'] = $this->firstName;
        if ($this->lastName !== null) $data['last_name'] = $this->lastName;
        return $data;
    }
}
