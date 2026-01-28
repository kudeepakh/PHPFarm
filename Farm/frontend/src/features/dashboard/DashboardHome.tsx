import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { 
  Activity, 
  Server, 
  Database, 
  Shield, 
  Users, 
  Zap,
  TrendingUp,
  AlertTriangle,
  CheckCircle,
  XCircle,
  Clock,
  Cpu
} from 'lucide-react';

import { apiClient } from '@/services/api';

interface SystemHealthData {
  status: 'healthy' | 'degraded' | 'down';
  databases: {
    mysql: boolean;
    mongodb: boolean;
    redis: boolean;
  };
  metrics: {
    uptime: number;
    memory_usage: number;
    cpu_usage: number;
    storage_usage: number;
    active_connections: number;
    error_rate: number;
    response_time: number;
  };
  last_check: string;
}

interface SystemStatsData {
  total_users: number;
  active_sessions: number;
  api_calls_today: number;
  cache_hit_rate: number;
  job_stats: {
    pending: number;
    failed: number;
  };
}

const DashboardHome: React.FC = () => {
  const navigate = useNavigate();

  // Fetch system health data
  const { data: healthData, isLoading: healthLoading, error: healthError } = useQuery<SystemHealthData>({
    queryKey: ['system-health'],
    queryFn: async () => {
      const response = await apiClient.get('/system/health');
      console.log('🔍 Health API Full Response:', response);
      console.log('🔍 Health API response.data:', response.data);
      console.log('🔍 Health API response.data.data:', response.data.data);
      return response.data.data;
    },
    refetchInterval: 30000, // Refresh every 30 seconds
  });

  // Fetch system statistics
  const { data: statsData, isLoading: statsLoading, error: statsError } = useQuery<SystemStatsData>({
    queryKey: ['system-stats'],
    queryFn: async () => {
      const response = await apiClient.get('/system/stats');
      console.log('🔍 Stats API Full Response:', response);
      console.log('🔍 Stats API response.data:', response.data);
      console.log('🔍 Stats API response.data.data:', response.data.data);
      return response.data.data;
    },
    refetchInterval: 60000, // Refresh every minute
  });

  console.log('📊 Dashboard State:', { healthData, statsData, healthLoading, statsLoading, healthError, statsError });

  const getStatusColor = (status?: string) => {
    switch (status) {
      case 'healthy':
        return 'text-green-600 bg-green-50 border-green-200';
      case 'degraded':
        return 'text-yellow-600 bg-yellow-50 border-yellow-200';
      case 'down':
        return 'text-red-600 bg-red-50 border-red-200';
      default:
        return 'text-gray-600 bg-gray-50 border-gray-200';
    }
  };

  const getStatusIcon = (status?: string) => {
    switch (status) {
      case 'healthy':
        return <CheckCircle className="h-5 w-5" />;
      case 'degraded':
        return <AlertTriangle className="h-5 w-5" />;
      case 'down':
        return <XCircle className="h-5 w-5" />;
      default:
        return <Clock className="h-5 w-5" />;
    }
  };

  const formatUptime = (seconds?: number) => {
    if (!seconds) return 'N/A';
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return `${days}d ${hours}h ${minutes}m`;
  };

  const formatNumber = (num?: number) => {
    if (typeof num !== 'number') return 'N/A';
    return new Intl.NumberFormat().format(num);
  };

  const formatPercentage = (value?: number) => {
    if (typeof value !== 'number') return 'N/A';
    return `${value.toFixed(1)}%`;
  };

  return (
    <div className="space-y-6">
      {/* Welcome Header */}
      <div className="bg-gradient-to-r from-primary to-secondary p-6 rounded-lg text-white">
        <h1 className="text-2xl font-bold mb-2">Welcome to PHPFrarm Dashboard</h1>
        <p className="text-primary-100">
          Monitor your enterprise API framework performance and system health
        </p>
      </div>

      {/* System Status Overview */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* Overall Health */}
        <div className={`p-6 rounded-lg border ${getStatusColor(healthData?.status)}`}>
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium opacity-80">System Status</p>
              <div className="flex items-center mt-2">
                {getStatusIcon(healthData?.status)}
                <span className="ml-2 text-lg font-semibold capitalize">
                  {healthData?.status || 'Loading...'}
                </span>
              </div>
            </div>
            <Activity className="h-8 w-8 opacity-60" />
          </div>
        </div>

        {/* Uptime */}
        <div className="admin-card p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">System Uptime</p>
              <p className="text-lg font-semibold text-gray-900 mt-2">
                {formatUptime(healthData?.metrics?.uptime)}
              </p>
            </div>
            <Server className="h-8 w-8 text-gray-400" />
          </div>
        </div>

        {/* Response Time */}
        <div className="admin-card p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Avg Response Time</p>
              <p className="text-lg font-semibold text-gray-900 mt-2">
                {healthData?.metrics?.response_time ? `${healthData.metrics.response_time}ms` : 'N/A'}
              </p>
            </div>
            <Zap className="h-8 w-8 text-gray-400" />
          </div>
        </div>
      </div>

      {/* Performance Metrics */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {/* CPU Usage */}
        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <p className="text-sm font-medium text-gray-600">CPU Usage</p>
            <Cpu className="h-5 w-5 text-gray-400" />
          </div>
          <div className="space-y-2">
            <div className="flex justify-between items-center">
              <span className="text-2xl font-bold text-gray-900">
                {formatPercentage(healthData?.metrics?.cpu_usage)}
              </span>
              <span className={`text-sm ${(healthData?.metrics?.cpu_usage || 0) > 80 ? 'text-red-600' : 'text-green-600'}`}>
                {(healthData?.metrics?.cpu_usage || 0) > 80 ? 'High' : 'Normal'}
              </span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
              <div 
                className={`h-2 rounded-full ${(healthData?.metrics?.cpu_usage || 0) > 80 ? 'bg-red-500' : 'bg-green-500'}`}
                style={{ width: `${Math.min(healthData?.metrics?.cpu_usage || 0, 100)}%` }}
              ></div>
            </div>
          </div>
        </div>

        {/* Memory Usage */}
        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <p className="text-sm font-medium text-gray-600">Memory Usage</p>
            <Database className="h-5 w-5 text-gray-400" />
          </div>
          <div className="space-y-2">
            <div className="flex justify-between items-center">
              <span className="text-2xl font-bold text-gray-900">
                {formatPercentage(healthData?.metrics?.memory_usage)}
              </span>
              <span className={`text-sm ${(healthData?.metrics?.memory_usage || 0) > 85 ? 'text-red-600' : 'text-green-600'}`}>
                {(healthData?.metrics?.memory_usage || 0) > 85 ? 'High' : 'Normal'}
              </span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
              <div 
                className={`h-2 rounded-full ${(healthData?.metrics?.memory_usage || 0) > 85 ? 'bg-red-500' : 'bg-green-500'}`}
                style={{ width: `${Math.min(healthData?.metrics?.memory_usage || 0, 100)}%` }}
              ></div>
            </div>
          </div>
        </div>

        {/* Storage Usage */}
        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <p className="text-sm font-medium text-gray-600">Storage Usage</p>
            <Server className="h-5 w-5 text-gray-400" />
          </div>
          <div className="space-y-2">
            <div className="flex justify-between items-center">
              <span className="text-2xl font-bold text-gray-900">
                {formatPercentage(healthData?.metrics?.storage_usage)}
              </span>
              <span className={`text-sm ${(healthData?.metrics?.storage_usage || 0) > 90 ? 'text-red-600' : 'text-green-600'}`}>
                {(healthData?.metrics?.storage_usage || 0) > 90 ? 'High' : 'Normal'}
              </span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
              <div 
                className={`h-2 rounded-full ${(healthData?.metrics?.storage_usage || 0) > 90 ? 'bg-red-500' : 'bg-green-500'}`}
                style={{ width: `${Math.min(healthData?.metrics?.storage_usage || 0, 100)}%` }}
              ></div>
            </div>
          </div>
        </div>

        {/* Error Rate */}
        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <p className="text-sm font-medium text-gray-600">Error Rate</p>
            <Shield className="h-5 w-5 text-gray-400" />
          </div>
          <div className="space-y-2">
            <div className="flex justify-between items-center">
              <span className="text-2xl font-bold text-gray-900">
                {formatPercentage(healthData?.metrics?.error_rate)}
              </span>
              <span className={`text-sm ${(healthData?.metrics?.error_rate || 0) > 5 ? 'text-red-600' : 'text-green-600'}`}>
                {(healthData?.metrics?.error_rate || 0) > 5 ? 'High' : 'Low'}
              </span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
              <div 
                className={`h-2 rounded-full ${(healthData?.metrics?.error_rate || 0) > 5 ? 'bg-red-500' : 'bg-green-500'}`}
                style={{ width: `${Math.min(healthData?.metrics?.error_rate || 0, 100)}%` }}
              ></div>
            </div>
          </div>
        </div>
      </div>

      {/* Activity Statistics */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {/* Total Users */}
        <div className="admin-card p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Total Users</p>
              <p className="text-2xl font-bold text-gray-900 mt-2">
                {formatNumber(statsData?.total_users)}
              </p>
            </div>
            <Users className="h-8 w-8 text-primary" />
          </div>
        </div>

        {/* Active Sessions */}
        <div className="admin-card p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Active Sessions</p>
              <p className="text-2xl font-bold text-gray-900 mt-2">
                {formatNumber(statsData?.active_sessions)}
              </p>
            </div>
            <Activity className="h-8 w-8 text-green-500" />
          </div>
        </div>

        {/* API Calls Today */}
        <div className="admin-card p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">API Calls Today</p>
              <p className="text-2xl font-bold text-gray-900 mt-2">
                {formatNumber(statsData?.api_calls_today)}
              </p>
            </div>
            <TrendingUp className="h-8 w-8 text-blue-500" />
          </div>
        </div>

        {/* Cache Hit Rate */}
        <div className="admin-card p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Cache Hit Rate</p>
              <p className="text-2xl font-bold text-gray-900 mt-2">
                {formatPercentage(statsData?.cache_hit_rate)}
              </p>
            </div>
            <Zap className="h-8 w-8 text-orange-500" />
          </div>
        </div>

        {/* Pending Jobs */}
        <div className="admin-card p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Pending Jobs</p>
              <p className="text-2xl font-bold text-gray-900 mt-2">
                {formatNumber(statsData?.job_stats?.pending)}
              </p>
            </div>
            <Clock className="h-8 w-8 text-yellow-500" />
          </div>
        </div>

        {/* Failed Jobs */}
        <div className="admin-card p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Failed Jobs</p>
              <p className="text-2xl font-bold text-red-600 mt-2">
                {formatNumber(statsData?.job_stats?.failed)}
              </p>
            </div>
            <XCircle className="h-8 w-8 text-red-500" />
          </div>
        </div>
      </div>

      {/* Quick Actions */}
      <div className="admin-card p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <button 
            onClick={() => navigate('/security')} 
            className="admin-button-primary text-center p-4 hover:opacity-90 transition-opacity"
          >
            <Shield className="h-6 w-6 mx-auto mb-2" />
            Security Center
          </button>
          <button 
            onClick={() => navigate('/cache')} 
            className="admin-button-secondary text-center p-4 hover:opacity-90 transition-opacity"
          >
            <Database className="h-6 w-6 mx-auto mb-2" />
            Cache Management
          </button>
          <button 
            onClick={() => navigate('/users')} 
            className="admin-button-secondary text-center p-4 hover:opacity-90 transition-opacity"
          >
            <Users className="h-6 w-6 mx-auto mb-2" />
            User Management
          </button>
          <button 
            onClick={() => navigate('/logs')} 
            className="admin-button-secondary text-center p-4 hover:opacity-90 transition-opacity"
          >
            <Activity className="h-6 w-6 mx-auto mb-2" />
            System Logs
          </button>
        </div>
      </div>

      {/* Last Updated */}
      <div className="text-center text-sm text-gray-500">
        Last updated: {healthData?.last_check ? new Date(healthData.last_check).toLocaleString() : 'Never'}
      </div>
    </div>
  );
};

export default DashboardHome;