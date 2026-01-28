import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { 
  Activity, 
  Server, 
  Database, 
  Cpu, 
  HardDrive,
  Zap,
  AlertTriangle,
  CheckCircle,
  XCircle,
  RefreshCw
} from 'lucide-react';

import { apiClient } from '@/services/api';

interface SystemMetrics {
  cpu_usage: number;
  memory_usage: number;
  disk_usage: number;
  network_in: number;
  network_out: number;
  uptime: number;
  load_average: number[];
  processes: number;
}

interface HealthCheck {
  service: string;
  status: 'healthy' | 'warning' | 'critical';
  response_time: number;
  last_check: string;
  message?: string;
}

const SystemHealth: React.FC = () => {
  const { data: metrics, isLoading: metricsLoading, refetch: refetchMetrics } = useQuery<SystemMetrics>({
    queryKey: ['system-metrics'],
    queryFn: () => apiClient.get('/system/health').then(res => res.data.data),
    refetchInterval: 5000,
  });

  const { data: healthChecks, isLoading: healthLoading } = useQuery<HealthCheck[]>({
    queryKey: ['system-health-checks'],
    queryFn: () => apiClient.get('/system/health').then(res => res.data.data),
    refetchInterval: 10000,
  });

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'healthy': return 'text-green-600 bg-green-50';
      case 'warning': return 'text-yellow-600 bg-yellow-50';
      case 'critical': return 'text-red-600 bg-red-50';
      default: return 'text-gray-600 bg-gray-50';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'healthy': return <CheckCircle className="h-4 w-4" />;
      case 'warning': return <AlertTriangle className="h-4 w-4" />;
      case 'critical': return <XCircle className="h-4 w-4" />;
      default: return <Activity className="h-4 w-4" />;
    }
  };

  const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const formatUptime = (seconds: number) => {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return `${days}d ${hours}h ${minutes}m`;
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">System Health</h1>
          <p className="text-gray-600">Monitor system performance and service status</p>
        </div>
        <button
          onClick={() => refetchMetrics()}
          className="admin-button-secondary flex items-center space-x-2"
        >
          <RefreshCw className="h-4 w-4" />
          <span>Refresh</span>
        </button>
      </div>

      {/* System Metrics */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-medium text-gray-600">CPU Usage</h3>
            <Cpu className="h-5 w-5 text-gray-400" />
          </div>
          <div className="space-y-2">
            <div className="text-2xl font-bold">
              {metrics?.cpu_usage?.toFixed(1) || '0'}%
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
              <div 
                className={`h-2 rounded-full ${
                  (metrics?.cpu_usage || 0) > 80 ? 'bg-red-500' : 
                  (metrics?.cpu_usage || 0) > 60 ? 'bg-yellow-500' : 'bg-green-500'
                }`}
                style={{ width: `${Math.min(metrics?.cpu_usage || 0, 100)}%` }}
              />
            </div>
          </div>
        </div>

        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-medium text-gray-600">Memory Usage</h3>
            <Database className="h-5 w-5 text-gray-400" />
          </div>
          <div className="space-y-2">
            <div className="text-2xl font-bold">
              {metrics?.memory_usage?.toFixed(1) || '0'}%
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
              <div 
                className={`h-2 rounded-full ${
                  (metrics?.memory_usage || 0) > 85 ? 'bg-red-500' : 
                  (metrics?.memory_usage || 0) > 70 ? 'bg-yellow-500' : 'bg-green-500'
                }`}
                style={{ width: `${Math.min(metrics?.memory_usage || 0, 100)}%` }}
              />
            </div>
          </div>
        </div>

        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-medium text-gray-600">Disk Usage</h3>
            <HardDrive className="h-5 w-5 text-gray-400" />
          </div>
          <div className="space-y-2">
            <div className="text-2xl font-bold">
              {metrics?.disk_usage?.toFixed(1) || '0'}%
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
              <div 
                className={`h-2 rounded-full ${
                  (metrics?.disk_usage || 0) > 90 ? 'bg-red-500' : 
                  (metrics?.disk_usage || 0) > 75 ? 'bg-yellow-500' : 'bg-green-500'
                }`}
                style={{ width: `${Math.min(metrics?.disk_usage || 0, 100)}%` }}
              />
            </div>
          </div>
        </div>

        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-medium text-gray-600">System Uptime</h3>
            <Server className="h-5 w-5 text-gray-400" />
          </div>
          <div className="text-2xl font-bold">
            {metrics?.uptime ? formatUptime(metrics.uptime) : 'N/A'}
          </div>
        </div>
      </div>

      {/* Service Health Checks */}
      <div className="admin-card">
        <div className="p-6 border-b border-gray-200">
          <h3 className="text-lg font-semibold text-gray-900">Service Health Checks</h3>
        </div>
        <div className="p-6">
          {healthLoading ? (
            <div className="text-center py-8">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
              <p className="text-gray-500 mt-2">Loading health checks...</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {healthChecks?.map((check, index) => (
                <div key={index} className={`p-4 rounded-lg border ${getStatusColor(check.status)}`}>
                  <div className="flex items-center justify-between mb-2">
                    <span className="font-medium">{check.service}</span>
                    {getStatusIcon(check.status)}
                  </div>
                  <div className="text-sm opacity-80">
                    Response: {check.response_time}ms
                  </div>
                  <div className="text-xs opacity-60 mt-1">
                    Last check: {new Date(check.last_check).toLocaleTimeString()}
                  </div>
                  {check.message && (
                    <div className="text-xs mt-2 opacity-70">
                      {check.message}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* System Information */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="admin-card p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">System Load</h3>
          <div className="space-y-3">
            <div className="flex justify-between">
              <span className="text-gray-600">1 minute:</span>
              <span className="font-mono">{metrics?.load_average?.[0]?.toFixed(2) || '0.00'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">5 minutes:</span>
              <span className="font-mono">{metrics?.load_average?.[1]?.toFixed(2) || '0.00'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">15 minutes:</span>
              <span className="font-mono">{metrics?.load_average?.[2]?.toFixed(2) || '0.00'}</span>
            </div>
          </div>
        </div>

        <div className="admin-card p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Network Traffic</h3>
          <div className="space-y-3">
            <div className="flex justify-between">
              <span className="text-gray-600">Network In:</span>
              <span className="font-mono">{formatBytes(metrics?.network_in || 0)}/s</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Network Out:</span>
              <span className="font-mono">{formatBytes(metrics?.network_out || 0)}/s</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Active Processes:</span>
              <span className="font-mono">{metrics?.processes || 0}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default SystemHealth;