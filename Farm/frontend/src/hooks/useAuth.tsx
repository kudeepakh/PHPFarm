import { useState, useEffect, useContext, createContext, ReactNode } from 'react';
import { authService } from '@/services/auth';
import { User } from '@/types/api';

interface AuthContextType {
  user: User | null;
  isLoading: boolean;
  login: (credentials: { identifier: string; password: string }) => Promise<void>;
  setAuthenticatedUser: (user: User) => void;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  // Initialize auth state
  useEffect(() => {
    const initAuth = async () => {
      try {
        const token = localStorage.getItem('access_token');
        const storedUser = localStorage.getItem('user');
        if (token && storedUser) {
          // Try to use stored user first
          setUser(JSON.parse(storedUser));
          // Optionally validate with server
          try {
            const userInfo = await authService.getCurrentUser();
            setUser(userInfo);
          } catch (e) {
            // Token might be expired, but keep stored user for now
            console.warn('Could not refresh user info:', e);
          }
        }
      } catch (error) {
        // Token is invalid, clear it
        localStorage.removeItem('access_token');
        localStorage.removeItem('refresh_token');
        localStorage.removeItem('user');
      } finally {
        setIsLoading(false);
      }
    };

    initAuth();
  }, []);

  const login = async (credentials: { identifier: string; password: string }) => {
    const response = await authService.login(credentials);
    if (response.success && response.data?.user) {
      console.log('Login successful, setting user:', response.data.user);
      setUser(response.data.user);
      // Force a small delay to ensure state is updated before navigation
      await new Promise(resolve => setTimeout(resolve, 100));
    } else {
      console.error('Login response missing user data:', response);
      throw new Error('Login failed: Invalid response');
    }
  };

  const setAuthenticatedUser = (user: User) => {
    setUser(user);
  };

  const logout = async () => {
    try {
      await authService.logout();
    } catch (error) {
      // Even if logout fails, clear local state
      console.error('Logout error:', error);
    }
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    localStorage.removeItem('user');
    setUser(null);
  };

  const refreshUser = async () => {
    try {
      const userInfo = await authService.getCurrentUser();
      setUser(userInfo);
    } catch (error) {
      console.error('Failed to refresh user:', error);
      setUser(null);
    }
  };

  const value = {
    user,
    isLoading,
    login,
    setAuthenticatedUser,
    logout,
    refreshUser,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};