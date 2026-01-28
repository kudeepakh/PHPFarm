// PHPFrarm API Types
export interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T;
  correlation_id: string;
  timestamp: string;
}

export interface ApiError {
  success: false;
  error: string;
  message: string;
  data?: any;
  correlation_id: string;
  timestamp: string;
}

// Authentication Types
export interface User {
  user_id: string;
  email?: string;
  phone?: string;
  first_name: string;
  last_name: string;
  status: 'active' | 'suspended' | 'locked';
  email_verified: boolean;
  phone_verified: boolean;
  created_at: string;
  updated_at: string;
  version: number;
}

export interface AuthTokens {
  access_token: string;
  refresh_token: string;
  token_type: string;
  expires_in: number;
}

export interface LoginRequest {
  identifier: string;
  password: string;
}

export interface RegisterRequest {
  email?: string;      // Optional - at least one of email/phone required
  phone?: string;      // Optional - at least one of email/phone required
  password: string;
  first_name: string;
  last_name: string;
}

export interface PhoneLoginRequest {
  phone: string;
}

export interface OTPVerificationRequest {
  phone: string;
  otp: string;
}

// System Types
export interface SystemHealth {
  status: 'ok' | 'degraded' | 'down';
  service: string;
  timestamp: string;
  checks?: {
    [key: string]: {
      status: 'ok' | 'error';
      response_time: number;
    };
  };
}

export interface CacheStatistics {
  hits: number;
  misses: number;
  hit_ratio: number;
  memory_usage: string;
  total_keys: number;
  evictions: number;
}

export interface SecurityEvent {
  event_id: string;
  type: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  ip_address: string;
  description: string;
  action_taken: string;
  timestamp: string;
}

// Role and Permission Types
export interface Role {
  role_id: string;
  name: string;
  description: string;
  priority: number;
  is_system_role: boolean;
  version: number;
  created_at: string;
  updated_at?: string;
}

export interface Permission {
  permission_id: string;
  name: string;
  description: string;
  resource: string;
  action: string;
}

// Traffic Management Types
export interface RateLimitStats {
  endpoint: string;
  requests: number;
  blocked: number;
  limit: number;
  window: number;
}

export interface QuotaStatus {
  client_id: string;
  daily_limit: number;
  daily_used: number;
  daily_remaining: number;
  monthly_limit: number;
  monthly_used: number;
  monthly_remaining: number;
}

// Version Conflict Types
export interface VersionConflict {
  expected_version: number;
  current_version: number;
  conflict_type: string;
}

// Form Types
export interface FormFieldError {
  message: string;
  type: string;
}

export interface ValidationErrors {
  [field: string]: string[];
}