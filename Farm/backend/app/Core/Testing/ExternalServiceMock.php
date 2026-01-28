<?php

namespace Farm\Backend\App\Core\Testing;

/**
 * External Service Mock
 * 
 * Pre-configured mocks for common external services:
 * - Payment processors (Stripe, PayPal)
 * - Email services (SendGrid, AWS SES)
 * - SMS services (Twilio)
 * - OAuth providers (Google, Facebook, GitHub)
 * 
 * Usage:
 * ```php
 * $stripe = ExternalServiceMock::stripe();
 * $stripe->mockChargeSuccess('ch_123', 5000);
 * ```
 */
class ExternalServiceMock
{
    /**
     * Create Stripe mock
     * 
     * @return StripeMock
     */
    public static function stripe(): StripeMock
    {
        return new StripeMock();
    }

    /**
     * Create SendGrid mock
     * 
     * @return SendGridMock
     */
    public static function sendGrid(): SendGridMock
    {
        return new SendGridMock();
    }

    /**
     * Create Twilio mock
     * 
     * @return TwilioMock
     */
    public static function twilio(): TwilioMock
    {
        return new TwilioMock();
    }

    /**
     * Create OAuth mock
     * 
     * @param string $provider
     * @return OAuthMock
     */
    public static function oauth(string $provider = 'google'): OAuthMock
    {
        return new OAuthMock($provider);
    }
}

/**
 * Stripe Mock
 */
class StripeMock
{
    private MockServer $server;

    public function __construct()
    {
        $this->server = new MockServer('https://api.stripe.com');
        $this->server->start();
    }

    /**
     * Mock successful charge
     * 
     * @param string $chargeId
     * @param int $amount
     * @return self
     */
    public function mockChargeSuccess(string $chargeId, int $amount): self
    {
        $this->server->when('POST', '/v1/charges')
            ->thenReturn(200, [
                'id' => $chargeId,
                'object' => 'charge',
                'amount' => $amount,
                'currency' => 'usd',
                'status' => 'succeeded',
                'paid' => true
            ]);
        
        return $this;
    }

    /**
     * Mock failed charge
     * 
     * @param string $errorCode
     * @param string $message
     * @return self
     */
    public function mockChargeFailure(string $errorCode, string $message): self
    {
        $this->server->when('POST', '/v1/charges')
            ->thenReturn(402, [
                'error' => [
                    'type' => 'card_error',
                    'code' => $errorCode,
                    'message' => $message
                ]
            ]);
        
        return $this;
    }

    /**
     * Mock customer creation
     * 
     * @param string $customerId
     * @param string $email
     * @return self
     */
    public function mockCustomerCreate(string $customerId, string $email): self
    {
        $this->server->when('POST', '/v1/customers')
            ->thenReturn(200, [
                'id' => $customerId,
                'object' => 'customer',
                'email' => $email,
                'created' => time()
            ]);
        
        return $this;
    }

    public function getServer(): MockServer
    {
        return $this->server;
    }
}

/**
 * SendGrid Mock
 */
class SendGridMock
{
    private MockServer $server;

    public function __construct()
    {
        $this->server = new MockServer('https://api.sendgrid.com');
        $this->server->start();
    }

    /**
     * Mock successful email send
     * 
     * @return self
     */
    public function mockSendSuccess(): self
    {
        $this->server->when('POST', '/v3/mail/send')
            ->thenReturn(202, [
                'message' => 'Queued. Thank you.'
            ]);
        
        return $this;
    }

    /**
     * Mock email send failure
     * 
     * @param string $error
     * @return self
     */
    public function mockSendFailure(string $error): self
    {
        $this->server->when('POST', '/v3/mail/send')
            ->thenReturn(400, [
                'errors' => [
                    ['message' => $error]
                ]
            ]);
        
        return $this;
    }

    /**
     * Assert email sent to recipient
     * 
     * @param string $email
     * @return void
     */
    public function assertSentTo(string $email): void
    {
        $requests = $this->server->getRequests();
        
        foreach ($requests as $request) {
            if ($request['method'] === 'POST' && $request['path'] === '/v3/mail/send') {
                $body = $request['body'];
                
                if (isset($body['personalizations'][0]['to'])) {
                    foreach ($body['personalizations'][0]['to'] as $recipient) {
                        if ($recipient['email'] === $email) {
                            return;
                        }
                    }
                }
            }
        }
        
        throw new \PHPUnit\Framework\AssertionFailedError(
            "Expected email to be sent to $email, but it wasn't"
        );
    }

    public function getServer(): MockServer
    {
        return $this->server;
    }
}

/**
 * Twilio Mock
 */
class TwilioMock
{
    private MockServer $server;

    public function __construct()
    {
        $this->server = new MockServer('https://api.twilio.com');
        $this->server->start();
    }

    /**
     * Mock successful SMS send
     * 
     * @param string $sid
     * @return self
     */
    public function mockSmsSuccess(string $sid = 'SM123'): self
    {
        $this->server->when('POST', '/2010-04-01/Accounts/*/Messages.json')
            ->thenReturn(201, [
                'sid' => $sid,
                'status' => 'queued',
                'price' => null,
                'price_unit' => 'USD'
            ]);
        
        return $this;
    }

    /**
     * Mock SMS send failure
     * 
     * @param int $errorCode
     * @param string $message
     * @return self
     */
    public function mockSmsFailure(int $errorCode, string $message): self
    {
        $this->server->when('POST', '/2010-04-01/Accounts/*/Messages.json')
            ->thenReturn(400, [
                'code' => $errorCode,
                'message' => $message
            ]);
        
        return $this;
    }

    /**
     * Assert SMS sent to phone number
     * 
     * @param string $phone
     * @return void
     */
    public function assertSentTo(string $phone): void
    {
        $requests = $this->server->getRequests();
        
        foreach ($requests as $request) {
            if ($request['method'] === 'POST' && strpos($request['path'], '/Messages.json') !== false) {
                if (isset($request['body']['To']) && $request['body']['To'] === $phone) {
                    return;
                }
            }
        }
        
        throw new \PHPUnit\Framework\AssertionFailedError(
            "Expected SMS to be sent to $phone, but it wasn't"
        );
    }

    public function getServer(): MockServer
    {
        return $this->server;
    }
}

/**
 * OAuth Provider Mock
 */
class OAuthMock
{
    private MockServer $server;
    private string $provider;

    public function __construct(string $provider = 'google')
    {
        $this->provider = $provider;
        $this->server = new MockServer($this->getProviderUrl($provider));
        $this->server->start();
    }

    /**
     * Get provider base URL
     * 
     * @param string $provider
     * @return string
     */
    private function getProviderUrl(string $provider): string
    {
        return match ($provider) {
            'google' => 'https://oauth2.googleapis.com',
            'facebook' => 'https://graph.facebook.com',
            'github' => 'https://api.github.com',
            default => 'https://oauth.example.com'
        };
    }

    /**
     * Mock successful token exchange
     * 
     * @param string $accessToken
     * @return self
     */
    public function mockTokenSuccess(string $accessToken): self
    {
        $this->server->when('POST', '/token')
            ->thenReturn(200, [
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600
            ]);
        
        return $this;
    }

    /**
     * Mock user info response
     * 
     * @param array $userInfo
     * @return self
     */
    public function mockUserInfo(array $userInfo): self
    {
        $path = match ($this->provider) {
            'google' => '/oauth2/v2/userinfo',
            'facebook' => '/me',
            'github' => '/user',
            default => '/userinfo'
        };
        
        $this->server->when('GET', $path)
            ->thenReturn(200, $userInfo);
        
        return $this;
    }

    public function getServer(): MockServer
    {
        return $this->server;
    }
}
