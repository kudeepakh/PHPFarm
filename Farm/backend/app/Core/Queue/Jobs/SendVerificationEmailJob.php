<?php

namespace PHPFrarm\Core\Queue\Jobs;

use PHPFrarm\Core\Queue\Job;
use PHPFrarm\Core\Logger;
use PHPFrarm\Modules\UserManagement\Services\EmailVerificationService;

/**
 * Send Verification Email Job
 * 
 * Async job to send email verification after user registration.
 * This prevents registration API from being slowed down by email sending.
 */
class SendVerificationEmailJob extends Job
{
    protected int $maxAttempts = 3;
    protected int $retryDelay = 120; // 2 minutes between retries

    public function __construct(array $payload = [])
    {
        parent::__construct($payload);
        
        // Validate required payload
        if (empty($payload['user_id']) || empty($payload['email'])) {
            throw new \InvalidArgumentException('SendVerificationEmailJob requires user_id and email in payload');
        }
    }

    /**
     * Execute the job - send verification email
     */
    public function handle(): bool
    {
        $userId = $this->payload['user_id'];
        $email = $this->payload['email'];
        $ipAddress = $this->payload['ip_address'] ?? null;

        Logger::info('Processing SendVerificationEmailJob', [
            'user_id' => $userId,
            'email' => $email,
            'attempt' => $this->attempts + 1,
        ]);

        try {
            // Create verification token and send email
            EmailVerificationService::createVerificationToken(
                $userId,
                $email,
                $ipAddress
            );

            Logger::info('Verification email sent successfully via job', [
                'user_id' => $userId,
                'email' => $email,
            ]);

            return true;
        } catch (\Exception $e) {
            Logger::error('SendVerificationEmailJob failed', [
                'user_id' => $userId,
                'email' => $email,
                'attempt' => $this->attempts + 1,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw for retry handling
        }
    }

    /**
     * Handle job failure after all retries exhausted
     */
    public function failed(\Exception $exception): void
    {
        parent::failed($exception);

        // Log to audit for manual intervention
        Logger::audit('Verification email permanently failed', [
            'user_id' => $this->payload['user_id'],
            'email' => $this->payload['email'],
            'error' => $exception->getMessage(),
            'action_required' => 'Manual email verification or resend needed',
        ]);

        // NOTE: Admin notification on email failure is optional
        // Can be implemented via: AdminNotificationService::emailFailure($this->email, $exception)
        // Configure admin alerts in config/notifications.php
    }
}
