import React from 'react';

interface AuthLayoutProps {
  children: React.ReactNode;
}

const AuthLayout: React.FC<AuthLayoutProps> = ({ children }) => {
  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 to-slate-100">
      <div className="max-w-md w-full space-y-8 p-8">
        {/* Logo and Header */}
        <div className="text-center">
          <div className="enterprise-gradient inline-flex items-center justify-center w-16 h-16 rounded-full mb-4">
            <svg
              className="w-8 h-8 text-white"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <h1 className="text-3xl font-bold text-gray-900">PHPFrarm</h1>
          <p className="mt-2 text-gray-600">Enterprise API Management</p>
        </div>

        {/* Auth Form Container */}
        <div className="bg-white rounded-lg shadow-lg p-8 space-y-6">
          {children}
        </div>

        {/* Footer */}
        <div className="text-center text-sm text-gray-500">
          <p>&copy; 2026 PHPFrarm. All rights reserved.</p>
          <p className="mt-1">Secure • Scalable • Enterprise-Ready</p>
        </div>
      </div>
    </div>
  );
};

export default AuthLayout;