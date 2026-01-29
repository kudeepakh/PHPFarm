import axios, { AxiosInstance, AxiosError, AxiosRequestConfig } from 'axios';
import { ApiResponse, ApiError } from '@/types/api';

// Create axios instance with base configuration
const createApiClient = (): AxiosInstance => {
  // Use the backend service URL for Docker environment
  const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8080';
  const baseURL = process.env.NODE_ENV === 'production' 
    ? '/api/v1'  // In production, use relative URLs with nginx proxy
    : `${apiUrl}/api/v1`;  // In development, use configured backend URL
    
  const client = axios.create({
    baseURL,
    timeout: 30000, // 30 seconds
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
  });

  // Request interceptor to add auth token and correlation ID
  client.interceptors.request.use(
    (config) => {
      // Add correlation ID if not present
      if (!config.headers['X-Correlation-Id']) {
        config.headers['X-Correlation-Id'] = `req_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
      }

      // Add auth token
      const token = localStorage.getItem('access_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }

      // Add CSRF token for state-changing requests
      if (['post', 'put', 'delete', 'patch'].includes(config.method?.toLowerCase() || '')) {
        const csrfToken = localStorage.getItem('csrf_token');
        if (csrfToken) {
          config.headers['X-CSRF-Token'] = csrfToken;
        }
      }

      return config;
    },
    (error) => {
      return Promise.reject(error);
    }
  );

  // Response interceptor to handle token refresh and errors
  client.interceptors.response.use(
    (response) => {
      // Store correlation ID for debugging
      const correlationId = response.headers['x-correlation-id'];
      if (correlationId) {
        console.debug('API Response Correlation ID:', correlationId);
      }

      return response;
    },
    async (error: AxiosError<ApiError>) => {
      const originalRequest = error.config as AxiosRequestConfig & { _retry?: boolean };

      // Handle 401 errors - attempt token refresh
      if (error.response?.status === 401 && !originalRequest._retry) {
        originalRequest._retry = true;

        try {
          const refreshToken = localStorage.getItem('refresh_token');
          if (refreshToken) {
            const response = await axios.post('/api/v1/auth/refresh', {
              refresh_token: refreshToken,
            });

            const { access_token } = response.data.data;
            localStorage.setItem('access_token', access_token);

            // Retry original request
            if (originalRequest.headers) {
              originalRequest.headers.Authorization = `Bearer ${access_token}`;
            }

            return client(originalRequest);
          }
        } catch (refreshError) {
          // Refresh failed, redirect to login
          localStorage.removeItem('access_token');
          localStorage.removeItem('refresh_token');
          window.location.href = '/login';
        }
      }

      // Handle other errors
      const apiError: ApiError = error.response?.data || {
        success: false,
        error: 'NETWORK_ERROR',
        message: 'Network error occurred',
        correlation_id: 'unknown',
        timestamp: new Date().toISOString(),
      };

      return Promise.reject(apiError);
    }
  );

  return client;
};

export const apiClient = createApiClient();

// Generic API methods
export const api = {
  get: <T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> =>
    apiClient.get(url, config).then((response) => response.data),

  post: <T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> =>
    apiClient.post(url, data, config).then((response) => response.data),

  put: <T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> =>
    apiClient.put(url, data, config).then((response) => response.data),

  delete: <T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> =>
    apiClient.delete(url, config).then((response) => response.data),

  patch: <T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> =>
    apiClient.patch(url, data, config).then((response) => response.data),
};

export default api;