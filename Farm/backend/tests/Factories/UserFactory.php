<?php

namespace Farm\Backend\Tests\Factories;

/**
 * User Factory
 * 
 * Generates test user data with various states.
 * 
 * Usage:
 * ```php
 * $user = UserFactory::new()->create();
 * $admin = UserFactory::new()->withState('admin')->create();
 * $verified = UserFactory::new()->withState('verified')->create();
 * ```
 */
class UserFactory extends Factory
{
    /**
     * Define default user attributes
     * 
     * @return array
     */
    protected function definition(): array
    {
        return [
            'id' => $this->uuid(),
            'email' => $this->fakeEmail(),
            'phone' => $this->fakePhone(),
            'first_name' => $this->randomString(6),
            'last_name' => $this->randomString(8),
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'status' => 'active',
            'email_verified' => 0,
            'phone_verified' => 0,
            'token_version' => 0,
            'last_login_at' => null,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
            'deleted_at' => null
        ];
    }

    /**
     * Get model class name
     * 
     * @return string
     */
    protected function model(): string
    {
        return 'Farm\Backend\App\Models\User';
    }

    /**
     * State: Admin user
     * 
     * @return self
     */
    public function admin(): self
    {
        $this->attributes['role'] = 'admin';
        return $this;
    }

    /**
     * State: Verified user (both email and phone)
     * 
     * @return self
     */
    public function verified(): self
    {
        $this->attributes['email_verified'] = 1;
        $this->attributes['phone_verified'] = 1;
        return $this;
    }

    /**
     * State: Email verified only
     * 
     * @return self
     */
    public function emailVerified(): self
    {
        $this->attributes['email_verified'] = 1;
        return $this;
    }

    /**
     * State: Phone verified only
     * 
     * @return self
     */
    public function phoneVerified(): self
    {
        $this->attributes['phone_verified'] = 1;
        return $this;
    }

    /**
     * State: Suspended user
     * 
     * @return self
     */
    public function suspended(): self
    {
        $this->attributes['status'] = 'suspended';
        return $this;
    }

    /**
     * State: Locked user (too many failed login attempts)
     * 
     * @return self
     */
    public function locked(): self
    {
        $this->attributes['status'] = 'locked';
        return $this;
    }

    /**
     * State: Soft deleted user
     * 
     * @return self
     */
    public function deleted(): self
    {
        $this->attributes['deleted_at'] = $this->now();
        return $this;
    }

    /**
     * Set specific email
     * 
     * @param string $email
     * @return self
     */
    public function withEmail(string $email): self
    {
        $this->attributes['email'] = $email;
        return $this;
    }

    /**
     * Set specific phone
     * 
     * @param string $phone
     * @return self
     */
    public function withPhone(string $phone): self
    {
        $this->attributes['phone'] = $phone;
        return $this;
    }

    /**
     * Set specific password
     * 
     * @param string $password Plain text password
     * @return self
     */
    public function withPassword(string $password): self
    {
        $this->attributes['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        return $this;
    }

    /**
     * Set specific role
     * 
     * @param string $role
     * @return self
     */
    public function withRole(string $role): self
    {
        $this->attributes['role'] = $role;
        return $this;
    }

    /**
     * Create user and keep role in returned data (not persisted)
     *
     * @param array $attributes
     * @return array
     */
    public function create(array $attributes = []): array
    {
        $role = $attributes['role'] ?? $this->attributes['role'] ?? null;

        if (isset($attributes['password'])) {
            $provided = $attributes['password'];
            if (is_string($provided) && (str_starts_with($provided, '$2y$') || str_starts_with($provided, '$argon2'))) {
                $attributes['password_hash'] = $provided;
            } else {
                $attributes['password_hash'] = password_hash($provided, PASSWORD_BCRYPT);
            }
            unset($attributes['password']);
        }

        unset($attributes['role']);
        unset($this->attributes['role']);

        $user = parent::create($attributes);

        if ($role !== null) {
            $user['role'] = $role;
        }

        return $user;
    }
}
