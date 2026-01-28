import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Mail, Phone, Eye, EyeOff, Loader2 } from 'lucide-react';
import toast from 'react-hot-toast';

import { authService } from '@/services/auth';
import { useAuth } from '@/hooks/useAuth';
import { LoginRequest, PhoneLoginRequest, OTPVerificationRequest } from '@/types/api';

// Validation schemas
const emailLoginSchema = z.object({
  identifier: z.string().email('Please enter a valid email address'),
  password: z.string().min(8, 'Password must be at least 8 characters'),
});

const phoneLoginSchema = z.object({
  phone: z.string().regex(/^\+[1-9]\d{1,14}$/, 'Please enter a valid phone number with country code'),
});

const otpSchema = z.object({
  otp: z.string().length(6, 'OTP must be 6 digits'),
});

type EmailLoginForm = z.infer<typeof emailLoginSchema>;
type PhoneLoginForm = z.infer<typeof phoneLoginSchema>;
type OTPForm = z.infer<typeof otpSchema>;

interface EmailOTPForm {
  email: string;
}

const LoginPage: React.FC = () => {
  const navigate = useNavigate();
  const { login, setAuthenticatedUser } = useAuth();
  const [loginMethod, setLoginMethod] = useState<'email' | 'phone' | 'email-otp'>('email');
  const [phoneStep, setPhoneStep] = useState<'initiate' | 'verify'>('initiate');
  const [emailOTPStep, setEmailOTPStep] = useState<'initiate' | 'verify'>('initiate');
  const [showPassword, setShowPassword] = useState(false);
  const [currentPhone, setCurrentPhone] = useState('');
  const [currentEmail, setCurrentEmail] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  // Email login form
  const emailForm = useForm<EmailLoginForm>({
    resolver: zodResolver(emailLoginSchema),
    defaultValues: {
      identifier: '',
      password: '',
    },
  });

  // Phone login form
  const phoneForm = useForm<PhoneLoginForm>({
    resolver: zodResolver(phoneLoginSchema),
    defaultValues: {
      phone: '',
    },
  });

  // OTP verification form
  const otpForm = useForm<OTPForm>({
    resolver: zodResolver(otpSchema),
    defaultValues: {
      otp: '',
    },
  });

  // Email OTP form
  const emailOTPForm = useForm<EmailOTPForm>({
    resolver: zodResolver(z.object({
      email: z.string().email('Please enter a valid email address'),
    })),
    defaultValues: {
      email: '',
    },
  });

  const handleEmailLogin = async (data: EmailLoginForm) => {
    setIsLoading(true);
    console.log('LoginPage - Starting email login with:', data);
    try {
      await login(data);
      console.log('LoginPage - Login function completed successfully');
      toast.success('Login successful!');
      // No need to navigate, PublicRoute will handle redirect
    } catch (error: any) {
      console.error('LoginPage - Login failed:', error);
      toast.error(error.message || 'Login failed');
    } finally {
      setIsLoading(false);
    }
  };

  const handlePhoneInitiate = async (data: PhoneLoginForm) => {
    setIsLoading(true);
    try {
      const response = await authService.initiatePhoneLogin(data);
      setCurrentPhone(data.phone);
      setPhoneStep('verify');
      toast.success('OTP sent to your phone');
    } catch (error: any) {
      toast.error(error.message || 'Failed to send OTP');
    } finally {
      setIsLoading(false);
    }
  };

  const handleOTPVerify = async (data: OTPForm) => {
    setIsLoading(true);
    try {
      const response = await authService.verifyPhoneLogin({
        phone: currentPhone,
        otp: data.otp,
      });
      if (response.success && response.data?.user) {
        setAuthenticatedUser(response.data.user);
        toast.success('Login successful!');
        // No need to navigate, PublicRoute will handle redirect
      }
    } catch (error: any) {
      toast.error(error.message || 'OTP verification failed');
    } finally {
      setIsLoading(false);
    }
  };

  const handleForgotPassword = () => {
    navigate('/forgot-password');
  };

  const handleSocialLogin = (provider: 'google' | 'microsoft' | 'facebook' | 'github' | 'linkedin' | 'twitter') => {
    try {
      // Use the backend social auth endpoint
      authService.initiateSocialLogin(provider);
    } catch (error: any) {
      toast.error(`Failed to initiate ${provider} login`);
      console.error(`Social login error with ${provider}:`, error);
    }
  };

  const handleCreateAccount = () => {
    navigate('/register');
  };

  const handleEmailOTPInitiate = async (data: EmailOTPForm) => {
    setIsLoading(true);
    try {
      const response = await authService.initiateEmailOTPLogin({ email: data.email });
      setCurrentEmail(data.email);
      setEmailOTPStep('verify');
      toast.success('OTP sent to your email');
    } catch (error: any) {
      toast.error(error.message || 'Failed to send email OTP');
    } finally {
      setIsLoading(false);
    }
  };

  const handleEmailOTPVerify = async (data: OTPForm) => {
    setIsLoading(true);
    try {
      const response = await authService.verifyEmailOTPLogin({
        email: currentEmail,
        otp: data.otp,
      });
      if (response.success && response.data?.user) {
        setAuthenticatedUser(response.data.user);
        toast.success('Login successful!');
        // No need to navigate, PublicRoute will handle redirect
      }
    } catch (error: any) {
      toast.error(error.message || 'Email OTP verification failed');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* Login Method Tabs */}
      <div className="flex rounded-lg bg-gray-100 p-1">
        <button
          type="button"
          className={`flex-1 flex items-center justify-center px-2 py-2 text-xs font-medium rounded-md transition-colors ${
            loginMethod === 'email'
              ? 'bg-white text-gray-900 shadow-sm'
              : 'text-gray-500 hover:text-gray-900'
          }`}
          onClick={() => {
            setLoginMethod('email');
            setPhoneStep('initiate');
          }}
        >
          <Mail className="mr-1 h-4 w-4" />
          Email
        </button>
        <button
          type="button"
          className={`flex-1 flex items-center justify-center px-2 py-2 text-xs font-medium rounded-md transition-colors ${
            loginMethod === 'phone'
              ? 'bg-white text-gray-900 shadow-sm'
              : 'text-gray-500 hover:text-gray-900'
          }`}
          onClick={() => {
            setLoginMethod('phone');
            setPhoneStep('initiate');
          }}
        >
          <Phone className="mr-1 h-4 w-4" />
          Phone OTP
        </button>
        <button
          type="button"
          className={`flex-1 flex items-center justify-center px-2 py-2 text-xs font-medium rounded-md transition-colors ${
            loginMethod === 'email-otp'
              ? 'bg-white text-gray-900 shadow-sm'
              : 'text-gray-500 hover:text-gray-900'
          }`}
          onClick={() => {
            setLoginMethod('email-otp');
            setEmailOTPStep('initiate');
          }}
        >
          <Mail className="mr-1 h-4 w-4" />
          Email OTP
        </button>
      </div>

      {/* Email Login Form */}
      {loginMethod === 'email' && (
        <form onSubmit={emailForm.handleSubmit(handleEmailLogin)} className="space-y-4">
          <div>
            <label htmlFor="identifier" className="block text-sm font-medium text-gray-700 mb-1">
              Email Address
            </label>
            <input
              {...emailForm.register('identifier')}
              type="email"
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
              placeholder="admin@company.com"
            />
            {emailForm.formState.errors.identifier && (
              <p className="mt-1 text-xs text-red-600">
                {emailForm.formState.errors.identifier.message}
              </p>
            )}
          </div>

          <div>
            <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
              Password
            </label>
            <div className="relative">
              <input
                {...emailForm.register('password')}
                type={showPassword ? 'text' : 'password'}
                className="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                placeholder="Enter your password"
              />
              <button
                type="button"
                className="absolute inset-y-0 right-0 pr-3 flex items-center"
                onClick={() => setShowPassword(!showPassword)}
              >
                {showPassword ? (
                  <EyeOff className="h-4 w-4 text-gray-400" />
                ) : (
                  <Eye className="h-4 w-4 text-gray-400" />
                )}
              </button>
            </div>
            {emailForm.formState.errors.password && (
              <p className="mt-1 text-xs text-red-600">
                {emailForm.formState.errors.password.message}
              </p>
            )}
          </div>

          <button
            type="submit"
            disabled={isLoading}
            className="w-full admin-button-primary disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isLoading ? (
              <Loader2 className="h-4 w-4 animate-spin mr-2" />
            ) : null}
            Sign In
          </button>
        </form>
      )}

      {/* Additional Options for Email Login */}
      {loginMethod === 'email' && (
        <div className="space-y-3">
          <div className="text-center">
            <button
              type="button"
              className="text-sm text-primary hover:underline"
              onClick={() => handleForgotPassword()}
            >
              Forgot your password?
            </button>
          </div>
          
          <div className="relative">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-gray-300" />
            </div>
            <div className="relative flex justify-center text-sm">
              <span className="px-2 bg-white text-gray-500">Or continue with</span>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3 mb-3">
            <button
              type="button"
              className="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
              onClick={() => handleSocialLogin('google')}
            >
              <svg className="h-5 w-5 mr-2" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
              Google
            </button>
            <button
              type="button"
              className="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
              onClick={() => handleSocialLogin('microsoft')}
            >
              <svg className="h-5 w-5 mr-2" viewBox="0 0 24 24">
                <path fill="#f25022" d="M1 1h10v10H1z"/>
                <path fill="#00a4ef" d="M13 1h10v10H13z"/>
                <path fill="#7fba00" d="M1 13h10v10H1z"/>
                <path fill="#ffb900" d="M13 13h10v10H13z"/>
              </svg>
              Microsoft
            </button>
          </div>

          <div className="grid grid-cols-2 gap-3 mb-3">
            <button
              type="button"
              className="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
              onClick={() => handleSocialLogin('facebook')}
            >
              <svg className="h-5 w-5 mr-2" viewBox="0 0 24 24">
                <path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
              Facebook
            </button>
            <button
              type="button"
              className="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
              onClick={() => handleSocialLogin('github')}
            >
              <svg className="h-5 w-5 mr-2" viewBox="0 0 24 24">
                <path fill="#181717" d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
              </svg>
              GitHub
            </button>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <button
              type="button"
              className="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
              onClick={() => handleSocialLogin('linkedin')}
            >
              <svg className="h-5 w-5 mr-2" viewBox="0 0 24 24">
                <path fill="#0A66C2" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
              </svg>
              LinkedIn
            </button>
            <button
              type="button"
              className="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
              onClick={() => handleSocialLogin('twitter')}
            >
              <svg className="h-5 w-5 mr-2" viewBox="0 0 24 24">
                <path fill="#000000" d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z"/>
              </svg>
              Twitter
            </button>
          </div>
        </div>
      )}

      {/* Phone Login Form */}
      {loginMethod === 'phone' && phoneStep === 'initiate' && (
        <form onSubmit={phoneForm.handleSubmit(handlePhoneInitiate)} className="space-y-4">
          <div>
            <label htmlFor="phone" className="block text-sm font-medium text-gray-700 mb-1">
              Phone Number
            </label>
            <input
              {...phoneForm.register('phone')}
              type="tel"
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
              placeholder="+1234567890"
            />
            {phoneForm.formState.errors.phone && (
              <p className="mt-1 text-xs text-red-600">
                {phoneForm.formState.errors.phone.message}
              </p>
            )}
            <p className="mt-1 text-xs text-gray-500">
              Include country code (e.g., +1 for US)
            </p>
          </div>

          <button
            type="submit"
            disabled={isLoading}
            className="w-full admin-button-primary disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isLoading ? (
              <Loader2 className="h-4 w-4 animate-spin mr-2" />
            ) : null}
            Send OTP
          </button>
        </form>
      )}

      {/* Email OTP Initiate Form */}
      {loginMethod === 'email-otp' && emailOTPStep === 'initiate' && (
        <form onSubmit={emailOTPForm.handleSubmit(handleEmailOTPInitiate)} className="space-y-4">
          <div>
            <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
              Email Address
            </label>
            <input
              {...emailOTPForm.register('email')}
              type="email"
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
              placeholder="user@company.com"
            />
            {emailOTPForm.formState.errors.email && (
              <p className="mt-1 text-xs text-red-600">
                {emailOTPForm.formState.errors.email.message}
              </p>
            )}
            <p className="mt-1 text-xs text-gray-500">
              We'll send a 6-digit code to your email
            </p>
          </div>

          <button
            type="submit"
            disabled={isLoading}
            className="w-full admin-button-primary disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isLoading ? (
              <Loader2 className="h-4 w-4 animate-spin mr-2" />
            ) : null}
            Send Email OTP
          </button>
        </form>
      )}

      {/* Phone OTP Verification Form */}
      {loginMethod === 'phone' && phoneStep === 'verify' && (
        <form onSubmit={otpForm.handleSubmit(handleOTPVerify)} className="space-y-4">
          <div className="text-center">
            <p className="text-sm text-gray-600">
              Enter the 6-digit code sent to
            </p>
            <p className="font-medium text-gray-900">{currentPhone}</p>
          </div>

          <div>
            <label htmlFor="otp" className="block text-sm font-medium text-gray-700 mb-1">
              Verification Code
            </label>
            <input
              {...otpForm.register('otp')}
              type="text"
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent text-center text-lg tracking-widest"
              placeholder="123456"
              maxLength={6}
            />
            {otpForm.formState.errors.otp && (
              <p className="mt-1 text-xs text-red-600">
                {otpForm.formState.errors.otp.message}
              </p>
            )}
          </div>

          <div className="flex space-x-3">
            <button
              type="button"
              className="flex-1 admin-button-secondary"
              onClick={() => setPhoneStep('initiate')}
            >
              Back
            </button>
            <button
              type="submit"
              disabled={isLoading}
              className="flex-1 admin-button-primary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isLoading ? (
                <Loader2 className="h-4 w-4 animate-spin mr-2" />
              ) : null}
              Verify
            </button>
          </div>

          <div className="text-center">
            <button
              type="button"
              className="text-sm text-primary hover:underline"
              onClick={() => handlePhoneInitiate({ phone: currentPhone })}
            >
              Resend OTP
            </button>
          </div>
        </form>
      )}

      {/* Email OTP Verification Form */}
      {loginMethod === 'email-otp' && emailOTPStep === 'verify' && (
        <form onSubmit={otpForm.handleSubmit(handleEmailOTPVerify)} className="space-y-4">
          <div className="text-center">
            <p className="text-sm text-gray-600">
              Enter the 6-digit code sent to
            </p>
            <p className="font-medium text-gray-900">{currentEmail}</p>
          </div>

          <div>
            <label htmlFor="otp" className="block text-sm font-medium text-gray-700 mb-1">
              Verification Code
            </label>
            <input
              {...otpForm.register('otp')}
              type="text"
              maxLength={6}
              className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent text-center text-lg tracking-widest"
              placeholder="000000"
            />
            {otpForm.formState.errors.otp && (
              <p className="mt-1 text-xs text-red-600">
                {otpForm.formState.errors.otp.message}
              </p>
            )}
          </div>

          <div className="flex space-x-3">
            <button
              type="button"
              onClick={() => setEmailOTPStep('initiate')}
              className="flex-1 admin-button-secondary"
            >
              Back
            </button>
            <button
              type="submit"
              disabled={isLoading}
              className="flex-1 admin-button-primary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isLoading ? (
                <Loader2 className="h-4 w-4 animate-spin mr-2" />
              ) : null}
              Verify
            </button>
          </div>

          <div className="text-center">
            <button
              type="button"
              className="text-sm text-primary hover:underline"
              onClick={() => handleEmailOTPInitiate({ email: currentEmail })}
            >
              Resend Email OTP
            </button>
          </div>
        </form>
      )}

      {/* Registration Link */}
      <div className="mt-6 text-center">
        <p className="text-sm text-gray-600">
          Don't have an account?{' '}
          <button
            type="button"
            className="font-medium text-primary hover:underline"
            onClick={handleCreateAccount}
          >
            Create one now
          </button>
        </p>
      </div>
    </div>
  );
};

export default LoginPage;