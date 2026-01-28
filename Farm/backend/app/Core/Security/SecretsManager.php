<?php

namespace Farm\Backend\App\Core\Security;

/**
 * Secrets Manager
 * 
 * Manages application secrets with support for multiple backends:
 * - Environment variables (.env)
 * - HashiCorp Vault
 * - AWS Secrets Manager
 * - Azure Key Vault
 * 
 * Features:
 * - Secret rotation
 * - Caching with TTL
 * - Automatic refresh
 * - Fallback strategy
 * 
 * Usage:
 * ```php
 * $secrets = new SecretsManager();
 * 
 * // Get secret
 * $apiKey = $secrets->get('stripe_api_key');
 * 
 * // Set secret
 * $secrets->set('new_api_key', 'value');
 * 
 * // Rotate secret
 * $secrets->rotate('database_password', 'new_password');
 * ```
 */
class SecretsManager
{
    private const CACHE_TTL = 300; // 5 minutes
    
    private string $backend;
    private array $config;
    private array $cache = [];
    private array $cacheExpiry = [];
    
    /**
     * @param string $backend vault|aws|azure|env
     * @param array $config Backend-specific configuration
     */
    public function __construct(string $backend = 'env', array $config = [])
    {
        $this->backend = $backend;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        $this->validateBackend();
    }
    
    /**
     * Get secret value
     * 
     * @param string $key Secret key
     * @param mixed $default Default value if secret not found
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        // Check cache first
        if ($this->isCached($key)) {
            return $this->cache[$key];
        }
        
        try {
            $value = match($this->backend) {
                'vault' => $this->getFromVault($key),
                'aws' => $this->getFromAWS($key),
                'azure' => $this->getFromAzure($key),
                'env' => $this->getFromEnv($key),
                default => $default
            };
            
            if ($value !== null) {
                $this->cacheSecret($key, $value);
                return $value;
            }
            
            return $default;
            
        } catch (\Exception $e) {
            error_log("SecretManager: Failed to get secret '$key': " . $e->getMessage());
            
            // Fallback to environment variable via config
            $config = require __DIR__ . '/../../../config/secrets.php';
            if ($config['local']['fallback_to_env'] ?? false) {
                return env($key, $default);
            }
            return $default;
        }
    }
    
    /**
     * Set secret value
     * 
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function set(string $key, $value): bool
    {
        try {
            $result = match($this->backend) {
                'vault' => $this->setInVault($key, $value),
                'aws' => $this->setInAWS($key, $value),
                'azure' => $this->setInAzure($key, $value),
                'env' => $this->setInEnv($key, $value),
                default => false
            };
            
            if ($result) {
                $this->cacheSecret($key, $value);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("SecretManager: Failed to set secret '$key': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rotate secret (set new value and invalidate old)
     * 
     * @param string $key
     * @param mixed $newValue
     * @return bool
     */
    public function rotate(string $key, $newValue): bool
    {
        // Set new value
        if (!$this->set($key, $newValue)) {
            return false;
        }
        
        // Invalidate cache
        $this->invalidateCache($key);
        
        // Log rotation
        error_log("SecretManager: Rotated secret '$key'");
        
        return true;
    }
    
    /**
     * Delete secret
     * 
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        try {
            $result = match($this->backend) {
                'vault' => $this->deleteFromVault($key),
                'aws' => $this->deleteFromAWS($key),
                'azure' => $this->deleteFromAzure($key),
                'env' => false, // Cannot delete from .env at runtime
                default => false
            };
            
            if ($result) {
                $this->invalidateCache($key);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("SecretManager: Failed to delete secret '$key': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * List all secret keys
     * 
     * @return array
     */
    public function list(): array
    {
        try {
            return match($this->backend) {
                'vault' => $this->listFromVault(),
                'aws' => $this->listFromAWS(),
                'azure' => $this->listFromAzure(),
                'env' => array_keys($_ENV),
                default => []
            };
        } catch (\Exception $e) {
            error_log("SecretManager: Failed to list secrets: " . $e->getMessage());
            return [];
        }
    }
    
    // ==================== HASHICORP VAULT ====================
    
    private function getFromVault(string $key)
    {
        $url = $this->config['vault_url'] . '/v1/' . $this->config['vault_path'] . '/data/' . $key;
        
        $response = $this->httpRequest('GET', $url, [
            'X-Vault-Token: ' . $this->config['vault_token']
        ]);
        
        if (isset($response['data']['data']['value'])) {
            return $response['data']['data']['value'];
        }
        
        return null;
    }
    
    private function setInVault(string $key, $value): bool
    {
        $url = $this->config['vault_url'] . '/v1/' . $this->config['vault_path'] . '/data/' . $key;
        
        $response = $this->httpRequest('POST', $url, [
            'X-Vault-Token: ' . $this->config['vault_token'],
            'Content-Type: application/json'
        ], json_encode(['data' => ['value' => $value]]));
        
        return isset($response['data']);
    }
    
    private function deleteFromVault(string $key): bool
    {
        $url = $this->config['vault_url'] . '/v1/' . $this->config['vault_path'] . '/data/' . $key;
        
        $this->httpRequest('DELETE', $url, [
            'X-Vault-Token: ' . $this->config['vault_token']
        ]);
        
        return true;
    }
    
    private function listFromVault(): array
    {
        $url = $this->config['vault_url'] . '/v1/' . $this->config['vault_path'] . '/metadata?list=true';
        
        $response = $this->httpRequest('GET', $url, [
            'X-Vault-Token: ' . $this->config['vault_token']
        ]);
        
        return $response['data']['keys'] ?? [];
    }
    
    // ==================== AWS SECRETS MANAGER ====================
    
    private function getFromAWS(string $key)
    {
        // Requires AWS SDK
        // This is a placeholder implementation
        throw new \Exception('AWS Secrets Manager not implemented. Install aws/aws-sdk-php');
    }
    
    private function setInAWS(string $key, $value): bool
    {
        throw new \Exception('AWS Secrets Manager not implemented');
    }
    
    private function deleteFromAWS(string $key): bool
    {
        throw new \Exception('AWS Secrets Manager not implemented');
    }
    
    private function listFromAWS(): array
    {
        throw new \Exception('AWS Secrets Manager not implemented');
    }
    
    // ==================== AZURE KEY VAULT ====================
    
    private function getFromAzure(string $key)
    {
        // Requires Azure SDK
        throw new \Exception('Azure Key Vault not implemented. Install microsoft/azure-key-vault');
    }
    
    private function setInAzure(string $key, $value): bool
    {
        throw new \Exception('Azure Key Vault not implemented');
    }
    
    private function deleteFromAzure(string $key): bool
    {
        throw new \Exception('Azure Key Vault not implemented');
    }
    
    private function listFromAzure(): array
    {
        throw new \Exception('Azure Key Vault not implemented');
    }
    
    // ==================== ENVIRONMENT VARIABLES ====================
    
    private function getFromEnv(string $key)
    {
        return getenv($key) ?: null;
    }
    
    private function setInEnv(string $key, $value): bool
    {
        // Update .env file
        $envFile = __DIR__ . '/../../../.env';
        
        if (!file_exists($envFile)) {
            return false;
        }
        
        $content = file_get_contents($envFile);
        $pattern = "/^{$key}=.*/m";
        
        if (preg_match($pattern, $content)) {
            // Update existing
            $content = preg_replace($pattern, "{$key}={$value}", $content);
        } else {
            // Add new
            $content .= "\n{$key}={$value}";
        }
        
        return file_put_contents($envFile, $content) !== false;
    }
    
    // ==================== CACHE MANAGEMENT ====================
    
    private function isCached(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }
        
        if (!isset($this->cacheExpiry[$key])) {
            return false;
        }
        
        return time() < $this->cacheExpiry[$key];
    }
    
    private function cacheSecret(string $key, $value): void
    {
        $this->cache[$key] = $value;
        $this->cacheExpiry[$key] = time() + self::CACHE_TTL;
    }
    
    private function invalidateCache(string $key): void
    {
        unset($this->cache[$key]);
        unset($this->cacheExpiry[$key]);
    }
    
    private function clearCache(): void
    {
        $this->cache = [];
        $this->cacheExpiry = [];
    }
    
    // ==================== HELPERS ====================
    
    private function getDefaultConfig(): array
    {
        static $config = null;
        if ($config === null) {
            $config = require __DIR__ . '/../../../config/secrets.php';
        }
        
        return [
            'vault_url' => $config['vault']['url'] ?? 'http://localhost:8200',
            'vault_token' => $config['vault']['token'] ?? '',
            'vault_path' => $config['vault']['path'] ?? 'secret',
            'aws_region' => $config['aws']['region'] ?? 'us-east-1',
            'azure_vault_url' => $config['azure']['vault_url'] ?? ''
        ];
    }
    
    private function validateBackend(): void
    {
        if (!in_array($this->backend, ['vault', 'aws', 'azure', 'env'])) {
            throw new \InvalidArgumentException("Invalid backend: {$this->backend}");
        }
    }
    
    private function httpRequest(string $method, string $url, array $headers = [], string $body = null): array
    {
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new \Exception("HTTP request failed with status $httpCode");
        }
        
        return json_decode($response, true) ?: [];
    }
}
