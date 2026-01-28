import React, { useEffect, useState } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { CheckCircle, XCircle, Loader2, Mail } from 'lucide-react';
import api from '@/services/api';

type VerificationStatus = 'loading' | 'success' | 'error' | 'already_verified';

interface VerificationResult {
  verified: boolean;
  email: string;
  user_id: string;
}

const VerifyEmailPage: React.FC = () => {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [status, setStatus] = useState<VerificationStatus>('loading');
  const [email, setEmail] = useState<string>('');
  const [errorMessage, setErrorMessage] = useState<string>('');

  // Get params from URL
  const token = searchParams.get('token');
  const success = searchParams.get('success');
  const error = searchParams.get('error');

  useEffect(() => {
    // If redirected from backend with success/error params
    if (success === 'true') {
      setStatus('success');
      setEmail(searchParams.get('email') || '');
      return;
    }

    if (error) {
      setStatus('error');
      setErrorMessage(decodeURIComponent(error));
      return;
    }

    // If we have a token, verify it via API
    if (token) {
      verifyEmail(token);
    } else {
      setStatus('error');
      setErrorMessage('No verification token provided');
    }
  }, [token, success, error]);

  const verifyEmail = async (verificationToken: string) => {
    try {
      setStatus('loading');
      const response = await api.post<VerificationResult>('/auth/verify-email', {
        token: verificationToken,
      });

      if (response.success) {
        setStatus('success');
        setEmail(response.data.email);
      } else {
        setStatus('error');
        setErrorMessage(response.message || 'Verification failed');
      }
    } catch (err: any) {
      setStatus('error');
      const message = err.response?.data?.message || err.message || 'Verification failed';
      
      if (message.toLowerCase().includes('already')) {
        setStatus('already_verified');
        setErrorMessage('This email has already been verified');
      } else if (message.toLowerCase().includes('expired')) {
        setErrorMessage('Verification link has expired. Please request a new one.');
      } else if (message.toLowerCase().includes('invalid')) {
        setErrorMessage('Invalid verification link. Please check your email.');
      } else {
        setErrorMessage(message);
      }
    }
  };

  const renderContent = () => {
    switch (status) {
      case 'loading':
        return (
          <div className="text-center">
            <div className="mx-auto w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-6">
              <Loader2 className="w-8 h-8 text-blue-600 animate-spin" />
            </div>
            <h2 className="text-2xl font-bold text-gray-900 mb-2">
              Verifying your email...
            </h2>
            <p className="text-gray-600">
              Please wait while we verify your email address.
            </p>
          </div>
        );

      case 'success':
        return (
          <div className="text-center">
            <div className="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-6">
              <CheckCircle className="w-8 h-8 text-green-600" />
            </div>
            <h2 className="text-2xl font-bold text-gray-900 mb-2">
              Email Verified!
            </h2>
            <p className="text-gray-600 mb-6">
              {email ? (
                <>Your email <span className="font-medium text-gray-900">{email}</span> has been successfully verified.</>
              ) : (
                'Your email has been successfully verified.'
              )}
            </p>
            <Link
              to="/login"
              className="inline-flex items-center justify-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
            >
              Continue to Login
            </Link>
          </div>
        );

      case 'already_verified':
        return (
          <div className="text-center">
            <div className="mx-auto w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-6">
              <Mail className="w-8 h-8 text-blue-600" />
            </div>
            <h2 className="text-2xl font-bold text-gray-900 mb-2">
              Already Verified
            </h2>
            <p className="text-gray-600 mb-6">
              This email has already been verified. You can proceed to login.
            </p>
            <Link
              to="/login"
              className="inline-flex items-center justify-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
            >
              Go to Login
            </Link>
          </div>
        );

      case 'error':
        return (
          <div className="text-center">
            <div className="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-6">
              <XCircle className="w-8 h-8 text-red-600" />
            </div>
            <h2 className="text-2xl font-bold text-gray-900 mb-2">
              Verification Failed
            </h2>
            <p className="text-gray-600 mb-6">
              {errorMessage || 'We were unable to verify your email address.'}
            </p>
            <div className="space-y-3">
              <Link
                to="/register"
                className="inline-flex items-center justify-center w-full px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
              >
                Register Again
              </Link>
              <Link
                to="/login"
                className="inline-flex items-center justify-center w-full px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors"
              >
                Back to Login
              </Link>
            </div>
          </div>
        );

      default:
        return null;
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full">
        {renderContent()}
      </div>
    </div>
  );
};

export default VerifyEmailPage;
