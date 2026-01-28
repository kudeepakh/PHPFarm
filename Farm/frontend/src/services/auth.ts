import api from './api';
import {
  User,
  AuthTokens,
  LoginRequest,
  RegisterRequest,
  PhoneLoginRequest,
  OTPVerificationRequest,
  ApiResponse,
} from '@/types/api';

export const authService = {
  // Unified Registration (email AND/OR phone)
  register: async (data: RegisterRequest): Promise<ApiResponse<{ 
    user_id: string; 
    email?: string; 
    phone?: string;
    identifiers: Array<{ type: string; value: string; is_primary: boolean }>;
  }>> => {
    return api.post('/auth/register', data);
  },

  // Email/Password Login
  login: async (data: LoginRequest): Promise<ApiResponse<AuthTokens & { user: User }>> => {
    const response = await api.post('/auth/login', data);
    
    // Store tokens - handle both 'token' and 'access_token' from backend
    if (response.success) {
      const accessToken = response.data.token || response.data.access_token;
      localStorage.setItem('access_token', accessToken);
      localStorage.setItem('refresh_token', response.data.refresh_token);
      localStorage.setItem('user', JSON.stringify(response.data.user));
    }
    
    return response;
  },

  // Phone Registration Initiation
  registerWithPhone: async (data: PhoneLoginRequest & { first_name: string; last_name: string; password: string }) => {
    return api.post('/auth/register/phone', data);
  },

  // Phone Registration Verification
  verifyPhoneRegistration: async (data: OTPVerificationRequest) => {
    return api.post('/auth/register/phone/verify', data);
  },

  // Phone Login Initiation
  initiatePhoneLogin: async (data: PhoneLoginRequest) => {
    return api.post('/auth/login/phone', data);
  },

  // Phone Login Verification
  verifyPhoneLogin: async (data: OTPVerificationRequest): Promise<ApiResponse<AuthTokens & { user: User }>> => {
    const response = await api.post('/auth/login/phone/verify', data);
    
    // Store tokens - handle both 'token' and 'access_token' from backend
    if (response.success) {
      const accessToken = response.data.token || response.data.access_token;
      localStorage.setItem('access_token', accessToken);
      localStorage.setItem('refresh_token', response.data.refresh_token);
      localStorage.setItem('user', JSON.stringify(response.data.user));
    }
    
    return response;
  },

  // Token Refresh
  refreshToken: async (refreshToken: string): Promise<ApiResponse<{ access_token: string; expires_in: number }>> => {
    return api.post('/auth/refresh', { refresh_token: refreshToken });
  },

  // Logout
  logout: async (): Promise<ApiResponse<{ message: string }>> => {
    try {
      const response = await api.post('/auth/logout');
      return response;
    } finally {
      // Always clear local storage
      localStorage.removeItem('access_token');
      localStorage.removeItem('refresh_token');
      localStorage.removeItem('user');
    }
  },

  // Get current user from localStorage
  getCurrentUser: (): User | null => {
    const userStr = localStorage.getItem('user');
    return userStr ? JSON.parse(userStr) : null;
  },

  // Email OTP Login Initiation
  initiateEmailOTPLogin: async (data: { email: string }): Promise<ApiResponse<{ message: string; email: string }>> => {
    return api.post('/auth/otp/request', {
      identifier: data.email,
      type: 'email',
      purpose: 'login'
    });
  },

  // Email OTP Login Verification
  verifyEmailOTPLogin: async (data: { email: string; otp: string }): Promise<ApiResponse<AuthTokens & { user: User }>> => {
    const response = await api.post('/auth/otp/verify', {
      identifier: data.email,
      otp: data.otp,
      type: 'email',
      purpose: 'login'
    });
    
    // Store tokens - handle both 'token' and 'access_token' from backend
    if (response.success) {
      const accessToken = response.data.token || response.data.access_token;
      localStorage.setItem('access_token', accessToken);
      localStorage.setItem('refresh_token', response.data.refresh_token);
      localStorage.setItem('user', JSON.stringify(response.data.user));
    }
    
    return response;
  },

  // Resend verification email
  resendVerificationEmail: async (data: { email: string }): Promise<ApiResponse<{ message: string }>> => {
    return api.post('/auth/otp/request', {
      identifier: data.email,
      type: 'email',
      purpose: 'registration'
    });
  },

  // Email verification for registration
  verifyRegistrationEmail: async (data: { email: string; otp: string }): Promise<ApiResponse<{ message: string }>> => {
    return api.post('/auth/otp/verify', {
      identifier: data.email,
      otp: data.otp,
      type: 'email',
      purpose: 'registration'
    });
  },

  // Forgot Password - Request OTP
  requestPasswordReset: async (data: { email: string }): Promise<ApiResponse<{ message: string }>> => {
    return api.post('/auth/otp/request', {
      identifier: data.email,
      type: 'email',
      purpose: 'reset_password'
    });
  },

  // Reset Password with OTP
  resetPasswordWithOTP: async (data: { email: string; otp: string; new_password: string }): Promise<ApiResponse<{ message: string }>> => {
    return api.post('/auth/password/reset', {
      identifier: data.email,
      otp: data.otp,
      new_password: data.new_password
    });
  },

  // Check if user is authenticated
  isAuthenticated: (): boolean => {
    const token = localStorage.getItem('access_token');
    const user = localStorage.getItem('user');
    return !!(token && user);
  },

  // Social Authentication
  initiateSocialLogin: (provider: string, redirectUri?: string): void => {
    const baseUrl = window.location.origin;
    const redirect = redirectUri || `${baseUrl}/auth/callback`;
    const authUrl = `/api/v1/auth/social/${provider}?redirect_uri=${encodeURIComponent(redirect)}`;
    window.location.href = authUrl;
  },

  // Handle social auth callback
  handleSocialCallback: async (code: string, state: string, provider: string): Promise<ApiResponse<AuthTokens & { user: User }>> => {
    const response = await api.get(`/auth/social/${provider}/callback?code=${code}&state=${state}`);
    
    // Store tokens
    if (response.success) {
      localStorage.setItem('access_token', response.data.access_token);
      localStorage.setItem('refresh_token', response.data.refresh_token);
      localStorage.setItem('user', JSON.stringify(response.data.user));
    }
    
    return response;
  },

  // Clear authentication
  clearAuth: (): void => {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    localStorage.removeItem('user');
  },
};