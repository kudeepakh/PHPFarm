import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ArrowLeft, Mail, User, Phone, Eye, EyeOff, Loader2, CheckCircle } from 'lucide-react';
import toast from 'react-hot-toast';

import { authService } from '@/services/auth';

// Validation schemas
const registrationSchema = z.object({
  firstName: z.string().min(2, 'First name must be at least 2 characters'),
  lastName: z.string().min(2, 'Last name must be at least 2 characters'),
  email: z.string().email('Please enter a valid email address').optional().or(z.literal('')),
  phone: z.string().regex(/^\+[1-9]\d{1,14}$/, 'Please enter a valid phone number with country code (e.g., +1234567890)').optional().or(z.literal('')),
  password: z.string().min(8, 'Password must be at least 8 characters'),
  confirmPassword: z.string(),
  agreeToTerms: z.boolean().refine(val => val === true, 'You must agree to the terms and conditions'),
}).refine((data) => data.password === data.confirmPassword, {
  message: "Passwords don't match",
  path: ["confirmPassword"],
}).refine((data) => {
  // At least one of email or phone must be provided
  const hasEmail = data.email && data.email.trim() !== '';
  const hasPhone = data.phone && data.phone.trim() !== '';
  return hasEmail || hasPhone;
}, {
  message: "Please provide either an email address or phone number (or both)",
  path: ["email"],
});

const otpSchema = z.object({
  otp: z.string().length(6, 'OTP must be 6 digits'),
});

type RegistrationForm = z.infer<typeof registrationSchema>;
type OTPForm = z.infer<typeof otpSchema>;

type Step = 'register' | 'verify-email' | 'verify-phone';

const RegistrationPage: React.FC = () => {
  const navigate = useNavigate();
  const [step, setStep] = useState<Step>('register');
  const [currentEmail, setCurrentEmail] = useState('');
  const [currentPhone, setCurrentPhone] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  // Registration form
  const registrationForm = useForm<RegistrationForm>({
    resolver: zodResolver(registrationSchema),
    defaultValues: {
      firstName: '',
      lastName: '',
      email: '',
      phone: '',
      password: '',
      confirmPassword: '',
      agreeToTerms: false,
    },
  });

  // OTP verification form
  const otpForm = useForm<OTPForm>({
    resolver: zodResolver(otpSchema),
    defaultValues: { otp: '' },
  });

  const handleRegistration = async (data: RegistrationForm) => {
    setIsLoading(true);
    try {
      // Build request with email and/or phone (at least one required)
      const requestData: any = {
        password: data.password,
        first_name: data.firstName,
        last_name: data.lastName
      };
      
      // Add email if provided
      if (data.email && data.email.trim() !== '') {
        requestData.email = data.email.trim();
      }
      
      // Add phone if provided
      if (data.phone && data.phone.trim() !== '') {
        requestData.phone = data.phone.trim();
      }

      await authService.register(requestData);
      
      // Handle verification based on what identifiers were provided
      const hasEmail = data.email && data.email.trim() !== '';
      const hasPhone = data.phone && data.phone.trim() !== '';
      
      if (hasEmail) {
        // Email registration - show "check your email" message
        setCurrentEmail(data.email!);
        setStep('verify-email');
        toast.success('Registration successful! Please check your email to verify your account.');
      } else if (hasPhone) {
        // Phone-only registration - show OTP verification
        setCurrentPhone(data.phone!);
        setStep('verify-phone');
        toast.success('Registration successful! Please enter the OTP sent to your phone.');
      }
    } catch (error: any) {
      toast.error(error.message || 'Registration failed');
    } finally {
      setIsLoading(false);
    }
  };

  const handlePhoneVerification = async (data: OTPForm) => {
    setIsLoading(true);
    try {
      await authService.verifyPhoneRegistration({
        phone: currentPhone,
        otp: data.otp
      });
      toast.success('Phone verified! Please login to continue.');
      navigate('/login');
    } catch (error: any) {
      toast.error(error.message || 'Phone verification failed');
    } finally {
      setIsLoading(false);
    }
  };

  const handleResendVerification = async () => {
    setIsLoading(true);
    try {
      if (currentEmail) {
        await authService.resendVerificationEmail({ email: currentEmail });
        toast.success('Verification email sent again');
      } else if (currentPhone) {
        // Resend phone OTP
        await authService.registerWithPhone({ 
          phone: currentPhone,
          first_name: '',
          last_name: '',
          password: ''
        });
        toast.success('OTP sent to your phone again');
      }
    } catch (error: any) {
      toast.error(error.message || 'Failed to resend verification');
    } finally {
      setIsLoading(false);
    }
  };

  const getStepTitle = () => {
    switch (step) {
      case 'register': return 'Create Account';
      case 'verify-email': return 'Check Your Email';
      case 'verify-phone': return 'Verify Phone';
      default: return 'Create Account';
    }
  };

  const getStepDescription = () => {
    switch (step) {
      case 'register': return 'Join PHPFrarm and start managing your APIs';
      case 'verify-email': return `We've sent a verification link to ${currentEmail}`;
      case 'verify-phone': return `Enter the OTP sent to ${currentPhone}`;
      default: return '';
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        {/* Header */}
        <div className="text-center">
          <div className="flex items-center justify-center mb-6">
            <div className="h-12 w-12 enterprise-gradient rounded-lg flex items-center justify-center">
              <span className="text-white font-bold text-lg">PF</span>
            </div>
          </div>
          <h2 className="text-3xl font-extrabold text-gray-900">
            {getStepTitle()}
          </h2>
          <p className="mt-2 text-sm text-gray-600">
            {getStepDescription()}
          </p>
        </div>

        {/* Back to Login Link */}
        <div className="flex justify-center">
          <Link 
            to="/login" 
            className="inline-flex items-center text-sm text-primary hover:underline"
          >
            <ArrowLeft className="mr-1 h-4 w-4" />
            Back to Login
          </Link>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-8">
          {/* Step 1: Registration Form */}
          {step === 'register' && (
            <form onSubmit={registrationForm.handleSubmit(handleRegistration)} className="space-y-6">
              {/* Name Fields */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label htmlFor="firstName" className="block text-sm font-medium text-gray-700 mb-1">
                    First Name
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <User className="h-4 w-4 text-gray-400" />
                    </div>
                    <input
                      {...registrationForm.register('firstName')}
                      type="text"
                      className="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                      placeholder="John"
                    />
                  </div>
                  {registrationForm.formState.errors.firstName && (
                    <p className="mt-1 text-xs text-red-600">
                      {registrationForm.formState.errors.firstName.message}
                    </p>
                  )}
                </div>

                <div>
                  <label htmlFor="lastName" className="block text-sm font-medium text-gray-700 mb-1">
                    Last Name
                  </label>
                  <input
                    {...registrationForm.register('lastName')}
                    type="text"
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="Doe"
                  />
                  {registrationForm.formState.errors.lastName && (
                    <p className="mt-1 text-xs text-red-600">
                      {registrationForm.formState.errors.lastName.message}
                    </p>
                  )}
                </div>
              </div>

              {/* Email */}
              <div>
                <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                  Email Address <span className="text-gray-500">(or provide phone below)</span>
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Mail className="h-4 w-4 text-gray-400" />
                  </div>
                  <input
                    {...registrationForm.register('email')}
                    type="email"
                    className="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="john.doe@company.com"
                  />
                </div>
                {registrationForm.formState.errors.email && (
                  <p className="mt-1 text-xs text-red-600">
                    {registrationForm.formState.errors.email.message}
                  </p>
                )}
              </div>

              {/* Phone (Optional - or provide email above) */}
              <div>
                <label htmlFor="phone" className="block text-sm font-medium text-gray-700 mb-1">
                  Phone Number <span className="text-gray-500">(or provide email above)</span>
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Phone className="h-4 w-4 text-gray-400" />
                  </div>
                  <input
                    {...registrationForm.register('phone')}
                    type="tel"
                    className="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="+1234567890"
                  />
                </div>
                <p className="mt-1 text-xs text-gray-500">
                  You can provide email, phone, or both. At least one is required.
                </p>
                {registrationForm.formState.errors.phone && (
                  <p className="mt-1 text-xs text-red-600">
                    {registrationForm.formState.errors.phone.message}
                  </p>
                )}
              </div>

              {/* Password */}
              <div>
                <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                  Password
                </label>
                <div className="relative">
                  <input
                    {...registrationForm.register('password')}
                    type={showPassword ? 'text' : 'password'}
                    className="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="At least 8 characters"
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
                {registrationForm.formState.errors.password && (
                  <p className="mt-1 text-xs text-red-600">
                    {registrationForm.formState.errors.password.message}
                  </p>
                )}
              </div>

              {/* Confirm Password */}
              <div>
                <label htmlFor="confirmPassword" className="block text-sm font-medium text-gray-700 mb-1">
                  Confirm Password
                </label>
                <div className="relative">
                  <input
                    {...registrationForm.register('confirmPassword')}
                    type={showConfirmPassword ? 'text' : 'password'}
                    className="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="Confirm your password"
                  />
                  <button
                    type="button"
                    className="absolute inset-y-0 right-0 pr-3 flex items-center"
                    onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                  >
                    {showConfirmPassword ? (
                      <EyeOff className="h-4 w-4 text-gray-400" />
                    ) : (
                      <Eye className="h-4 w-4 text-gray-400" />
                    )}
                  </button>
                </div>
                {registrationForm.formState.errors.confirmPassword && (
                  <p className="mt-1 text-xs text-red-600">
                    {registrationForm.formState.errors.confirmPassword.message}
                  </p>
                )}
              </div>

              {/* Terms Agreement */}
              <div className="flex items-start">
                <div className="flex items-center h-5">
                  <input
                    {...registrationForm.register('agreeToTerms')}
                    type="checkbox"
                    className="focus:ring-primary h-4 w-4 text-primary border-gray-300 rounded"
                  />
                </div>
                <div className="ml-3 text-sm">
                  <label htmlFor="agreeToTerms" className="text-gray-600">
                    I agree to the{' '}
                    <a href="#" className="text-primary hover:underline">
                      Terms of Service
                    </a>{' '}
                    and{' '}
                    <a href="#" className="text-primary hover:underline">
                      Privacy Policy
                    </a>
                  </label>
                </div>
              </div>
              {registrationForm.formState.errors.agreeToTerms && (
                <p className="text-xs text-red-600">
                  {registrationForm.formState.errors.agreeToTerms.message}
                </p>
              )}

              <button
                type="submit"
                disabled={isLoading}
                className="w-full admin-button-primary disabled:opacity-50 disabled:cursor-not-allowed py-3"
              >
                {isLoading ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin mr-2" />
                    Creating Account...
                  </>
                ) : (
                  'Create Account'
                )}
              </button>
            </form>
          )}

          {/* Step 2a: Email Verification - Check Email Message */}
          {step === 'verify-email' && (
            <div className="space-y-6 text-center">
              <div className="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center">
                <CheckCircle className="w-8 h-8 text-green-600" />
              </div>
              
              <div>
                <h3 className="text-lg font-semibold text-gray-900 mb-2">
                  Verification Email Sent!
                </h3>
                <p className="text-gray-600 mb-4">
                  We've sent a verification link to:
                </p>
                <p className="font-medium text-gray-900 bg-gray-100 py-2 px-4 rounded-lg inline-block">
                  {currentEmail}
                </p>
              </div>

              <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-left">
                <p className="text-sm text-blue-800">
                  <strong>Next steps:</strong>
                </p>
                <ol className="text-sm text-blue-700 mt-2 space-y-1 list-decimal list-inside">
                  <li>Check your email inbox (and spam folder)</li>
                  <li>Click the verification link in the email</li>
                  <li>Come back here and login to your account</li>
                </ol>
              </div>

              <div className="pt-4 space-y-3">
                <Link
                  to="/login"
                  className="w-full inline-flex items-center justify-center admin-button-primary py-3"
                >
                  Go to Login
                </Link>
                
                <button
                  type="button"
                  onClick={handleResendVerification}
                  disabled={isLoading}
                  className="w-full text-sm text-primary hover:underline disabled:opacity-50"
                >
                  {isLoading ? 'Sending...' : "Didn't receive the email? Resend"}
                </button>
              </div>
            </div>
          )}

          {/* Step 2b: Phone Verification - OTP Input */}
          {step === 'verify-phone' && (
            <form onSubmit={otpForm.handleSubmit(handlePhoneVerification)} className="space-y-6">
              <div className="text-center mb-6">
                <p className="text-sm text-gray-600">
                  Enter the OTP sent to:
                </p>
                <p className="font-medium text-gray-900">{currentPhone}</p>
              </div>

              <div>
                <label htmlFor="otp" className="block text-sm font-medium text-gray-700 mb-2">
                  Verification Code
                </label>
                <input
                  {...otpForm.register('otp')}
                  type="text"
                  maxLength={6}
                  className="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent text-center text-lg tracking-widest"
                  placeholder="000000"
                />
                {otpForm.formState.errors.otp && (
                  <p className="mt-1 text-sm text-red-600">
                    {otpForm.formState.errors.otp.message}
                  </p>
                )}
              </div>

              <div className="flex space-x-3">
                <button
                  type="button"
                  onClick={() => setStep('register')}
                  className="flex-1 admin-button-secondary py-3"
                >
                  Back
                </button>
                <button
                  type="submit"
                  disabled={isLoading}
                  className="flex-1 admin-button-primary disabled:opacity-50 disabled:cursor-not-allowed py-3"
                >
                  {isLoading ? (
                    <>
                      <Loader2 className="h-4 w-4 animate-spin mr-2" />
                      Verifying...
                    </>
                  ) : (
                    'Verify & Create Account'
                  )}
                </button>
              </div>

              <div className="text-center">
                <button
                  type="button"
                  onClick={handleResendVerification}
                  disabled={isLoading}
                  className="text-sm text-primary hover:underline disabled:opacity-50"
                >
                  Didn't receive the code? Resend
                </button>
              </div>
            </form>
          )}
        </div>

        {/* Already have account link */}
        <div className="text-center">
          <p className="text-sm text-gray-600">
            Already have an account?{' '}
            <Link to="/login" className="font-medium text-primary hover:underline">
              Sign in here
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
};

export default RegistrationPage;