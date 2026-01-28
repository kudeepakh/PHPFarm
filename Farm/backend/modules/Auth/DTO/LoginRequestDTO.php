<?php

namespace PHPFrarm\Modules\Auth\DTO;

/**
 * Login Request DTO
 */
class LoginRequestDTO
{
    public string $identifier;
    public string $password;

    public function __construct(array $data)
    {
        $this->identifier = $data['identifier'] ?? ($data['email'] ?? '');
        $this->password = $data['password'] ?? '';
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->identifier)) {
            $errors[] = 'Identifier is required';
        }

        if (empty($this->password)) {
            $errors[] = 'Password is required';
        }

        return $errors;
    }
}
