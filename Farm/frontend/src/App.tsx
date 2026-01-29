import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'react-hot-toast';

import { AuthProvider } from '@/hooks/useAuth';
import AuthLayout from '@/layouts/AuthLayout';
import DashboardLayout from '@/layouts/DashboardLayout';
import LoginPage from '@/features/auth/LoginPage';
import ForgotPasswordPage from '@/features/auth/ForgotPasswordPage';
import RegistrationPage from '@/features/auth/RegistrationPage';
import VerifyEmailPage from '@/features/auth/VerifyEmailPage';
import DashboardHome from '@/features/dashboard/DashboardHome';
import SystemHealth from '@/features/system/SystemHealth';
import CacheManagement from '@/features/cache/CacheManagement';
import SecurityCenter from '@/features/security/SecurityCenter';
import UserManagement from '@/features/users/UserManagement';
import RoleManagement from '@/features/roles/RoleManagement';
import LogsViewer from '@/features/logs/LogsViewer';
import { useAuth } from '@/hooks/useAuth';
import './index.css';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 3,
      staleTime: 5 * 60 * 1000,
      gcTime: 10 * 60 * 1000,
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: 1,
    },
  },
});

interface ProtectedRouteProps {
  children: React.ReactNode;
}

const ProtectedRoute: React.FC<ProtectedRouteProps> = ({ children }) => {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
};

const PublicRoute: React.FC<ProtectedRouteProps> = ({ children }) => {
  const { user, isLoading } = useAuth();

  console.log('PublicRoute - user:', user, 'isLoading:', isLoading);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  if (user) {
    console.log('PublicRoute - User detected, redirecting to dashboard');
    return <Navigate to="/dashboard" replace />;
  }

  return <>{children}</>;
};

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <div className="min-h-screen bg-background">
            <Routes>
              {/* Public Routes */}
              <Route path="/login" element={
                <PublicRoute>
                  <AuthLayout>
                    <LoginPage />
                  </AuthLayout>
                </PublicRoute>
              } />

              <Route path="/register" element={
                <PublicRoute>
                  <AuthLayout>
                    <RegistrationPage />
                  </AuthLayout>
                </PublicRoute>
              } />

              <Route path="/forgot-password" element={
                <PublicRoute>
                  <AuthLayout>
                    <ForgotPasswordPage />
                  </AuthLayout>
                </PublicRoute>
              } />

              <Route path="/verify-email" element={
                <VerifyEmailPage />
              } />

              {/* Protected Routes */}
              <Route path="/dashboard" element={
                <ProtectedRoute>
                  <DashboardLayout>
                    <DashboardHome />
                  </DashboardLayout>
                </ProtectedRoute>
              } />

              <Route path="/system" element={
                <ProtectedRoute>
                  <DashboardLayout>
                    <SystemHealth />
                  </DashboardLayout>
                </ProtectedRoute>
              } />

              <Route path="/cache" element={
                <ProtectedRoute>
                  <DashboardLayout>
                    <CacheManagement />
                  </DashboardLayout>
                </ProtectedRoute>
              } />

              <Route path="/security" element={
                <ProtectedRoute>
                  <DashboardLayout>
                    <SecurityCenter />
                  </DashboardLayout>
                </ProtectedRoute>
              } />

              <Route path="/users" element={
                <ProtectedRoute>
                  <DashboardLayout>
                    <UserManagement />
                  </DashboardLayout>
                </ProtectedRoute>
              } />

              <Route path="/roles" element={
                <ProtectedRoute>
                  <DashboardLayout>
                    <RoleManagement />
                  </DashboardLayout>
                </ProtectedRoute>
              } />

              <Route path="/logs" element={
                <ProtectedRoute>
                  <DashboardLayout>
                    <LogsViewer />
                  </DashboardLayout>
                </ProtectedRoute>
              } />

              {/* Default redirects */}
              <Route path="/" element={<Navigate to="/dashboard" replace />} />
              <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Routes>

            {/* Toast notifications */}
            <Toaster
              position="top-right"
              toastOptions={{
                duration: 4000,
                style: {
                  background: '#363636',
                  color: '#fff',
                },
              }}
            />
          </div>
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  );
}

export default App;