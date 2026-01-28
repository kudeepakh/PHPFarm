import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { 
  Shield, 
  AlertTriangle, 
  Ban, 
  Eye,
  Clock,
  Globe,
  Lock,
  Unlock,
  RefreshCw,
  Search,
  Filter
} from 'lucide-react';
import toast from 'react-hot-toast';

import { apiClient } from '@/services/api';

interface SecurityEvent {
  id: string;
  type: 'login_attempt' | 'failed_auth' | 'rate_limit' | 'suspicious_activity' | 'blocked_ip';
  severity: 'low' | 'medium' | 'high' | 'critical';
  ip_address: string;
  user_agent: string;
  details: string;
  timestamp: string;
  user_id?: string;
  location?: string;
}

interface BlockedIP {
  ip_address: string;
  reason: string;
  blocked_at: string;
  expires_at: string;
  attempts: number;
}

interface SecuritySettings {
  max_login_attempts: number;
  lockout_duration: number;
  rate_limit_window: number;
  rate_limit_max: number;
  auto_block_enabled: boolean;
  geo_blocking_enabled: boolean;
  blocked_countries: string[];
}

const SecurityCenter: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'events' | 'blocked' | 'settings'>('events');
  const [searchTerm, setSearchTerm] = useState('');
  const [severityFilter, setSeverityFilter] = useState<string>('');
  const queryClient = useQueryClient();

  // Fetch security events
  const { data: eventsResponse, isLoading: eventsLoading } = useQuery({
    queryKey: ['security-events', searchTerm, severityFilter],
    queryFn: async () => {
      const response = await apiClient.get('/security/events', {
        params: { search: searchTerm, severity: severityFilter }
      });
      console.log('🔍 Security Events Response:', response.data);
      return response.data.data;
    },
    refetchInterval: 30000,
  });

  // Map API response to frontend format
  const events = (eventsResponse?.events || []).map((event: any) => ({
    id: event.id,
    type: event.event_type,
    severity: event.severity,
    ip_address: event.ip_address,
    user_agent: event.user_agent,
    details: event.description,
    timestamp: event.timestamp * 1000, // Convert Unix timestamp to milliseconds
    blocked: event.blocked
  }));

  // Fetch blocked IPs
  const { data: blockedResponse, isLoading: blockedLoading } = useQuery({
    queryKey: ['blocked-ips'],
    queryFn: async () => {
      const response = await apiClient.get('/security/blocked-ips');
      console.log('🔍 Blocked IPs Response:', response.data);
      return response.data.data;
    },
  });

  // Map blocked IPs response
  const blockedIPs = (blockedResponse?.blocked_ips || []).map((ip: any) => ({
    ip_address: ip.ip,
    reason: ip.reason,
    blocked_at: new Date(ip.blocked_at * 1000).toISOString(),
    expires_at: new Date(ip.expires_at * 1000).toISOString(),
    attempts: ip.request_count
  }));

  // Fetch security settings
  const { data: settings, isLoading: settingsLoading } = useQuery<SecuritySettings>({
    queryKey: ['security-settings'],
    queryFn: () => apiClient.get('/security/settings').then(res => res.data.data),
  });

  // Block IP mutation
  const blockIPMutation = useMutation({
    mutationFn: (data: { ip: string; reason: string; duration?: number }) => 
      apiClient.post('/security/block-ip', data),
    onSuccess: () => {
      toast.success('IP address blocked successfully');
      queryClient.invalidateQueries({ queryKey: ['blocked-ips'] });
    },
    onError: (error: any) => {
      toast.error(error.message || 'Failed to block IP');
    },
  });

  // Unblock IP mutation
  const unblockIPMutation = useMutation({
    mutationFn: (ip: string) => apiClient.delete(`/security/unblock-ip/${ip}`),
    onSuccess: () => {
      toast.success('IP address unblocked successfully');
      queryClient.invalidateQueries({ queryKey: ['blocked-ips'] });
    },
    onError: (error: any) => {
      toast.error(error.message || 'Failed to unblock IP');
    },
  });

  // Update settings mutation
  const updateSettingsMutation = useMutation({
    mutationFn: (settings: Partial<SecuritySettings>) => 
      apiClient.put('/security/settings', settings),
    onSuccess: () => {
      toast.success('Security settings updated successfully');
      queryClient.invalidateQueries({ queryKey: ['security-settings'] });
    },
    onError: (error: any) => {
      toast.error(error.message || 'Failed to update settings');
    },
  });

  const getSeverityColor = (severity: string) => {
    switch (severity) {
      case 'critical': return 'text-red-600 bg-red-50';
      case 'high': return 'text-orange-600 bg-orange-50';
      case 'medium': return 'text-yellow-600 bg-yellow-50';
      case 'low': return 'text-blue-600 bg-blue-50';
      default: return 'text-gray-600 bg-gray-50';
    }
  };

  const getSeverityIcon = (severity: string) => {
    switch (severity) {
      case 'critical': return <AlertTriangle className="h-4 w-4 text-red-600" />;
      case 'high': return <Shield className="h-4 w-4 text-orange-600" />;
      case 'medium': return <Eye className="h-4 w-4 text-yellow-600" />;
      case 'low': return <Clock className="h-4 w-4 text-blue-600" />;
      default: return <Shield className="h-4 w-4 text-gray-600" />;
    }
  };

  const getEventTypeColor = (type: string) => {
    switch (type) {
      case 'failed_auth': return 'bg-red-100 text-red-800';
      case 'rate_limit': return 'bg-yellow-100 text-yellow-800';
      case 'suspicious_activity': return 'bg-orange-100 text-orange-800';
      case 'blocked_ip': return 'bg-gray-100 text-gray-800';
      case 'login_attempt': return 'bg-blue-100 text-blue-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const handleBlockIP = (ip: string) => {
    const reason = prompt('Enter reason for blocking this IP:');
    if (reason) {
      blockIPMutation.mutate({ ip, reason });
    }
  };

  const handleUnblockIP = (ip: string) => {
    if (confirm(`Are you sure you want to unblock ${ip}?`)) {
      unblockIPMutation.mutate(ip);
    }
  };

  const handleUpdateSettings = (newSettings: Partial<SecuritySettings>) => {
    updateSettingsMutation.mutate(newSettings);
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Security Center</h1>
          <p className="text-gray-600">Monitor security events and manage access controls</p>
        </div>
        <div className="flex items-center space-x-2">
          <Shield className="h-6 w-6 text-red-600" />
          <span className="text-sm font-medium text-red-600">
            {events?.filter(e => e.severity === 'critical').length || 0} Critical Alerts
          </span>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex space-x-8">
          {[
            { id: 'events', label: 'Security Events', icon: AlertTriangle },
            { id: 'blocked', label: 'Blocked IPs', icon: Ban },
            { id: 'settings', label: 'Settings', icon: Shield },
          ].map(tab => {
            const Icon = tab.icon;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as any)}
                className={`flex items-center space-x-2 py-2 px-1 border-b-2 font-medium text-sm ${
                  activeTab === tab.id
                    ? 'border-primary text-primary'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
              >
                <Icon className="h-4 w-4" />
                <span>{tab.label}</span>
              </button>
            );
          })}
        </nav>
      </div>

      {/* Security Events Tab */}
      {activeTab === 'events' && (
        <div className="space-y-4">
          {/* Filters */}
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                type="text"
                placeholder="Search events..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-transparent"
              />
            </div>
            <select
              value={severityFilter}
              onChange={(e) => setSeverityFilter(e.target.value)}
              className="px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-transparent"
            >
              <option value="">All Severities</option>
              <option value="critical">Critical</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </select>
          </div>

          {/* Events List */}
          <div className="admin-card">
            <div className="p-6">
              {eventsLoading ? (
                <div className="text-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                  <p className="text-gray-500 mt-2">Loading security events...</p>
                </div>
              ) : events && events.length > 0 ? (
                <div className="space-y-4">
                  {events.map((event) => (
                    <div key={event.id} className="p-4 border border-gray-200 rounded-lg">
                      <div className="flex items-start justify-between">
                        <div className="flex items-start space-x-3">
                          {getSeverityIcon(event.severity)}
                          <div className="flex-1">
                            <div className="flex items-center space-x-2 mb-2">
                              <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${getEventTypeColor(event.type)}`}>
                                {event.type.replace('_', ' ')}
                              </span>
                              <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${getSeverityColor(event.severity)}`}>
                                {event.severity}
                              </span>
                            </div>
                            <p className="text-gray-900 font-medium mb-1">{event.details}</p>
                            <div className="text-sm text-gray-600 space-y-1">
                              <div className="flex items-center space-x-4">
                                <span><Globe className="h-3 w-3 inline mr-1" />{event.ip_address}</span>
                                <span><Clock className="h-3 w-3 inline mr-1" />{new Date(event.timestamp).toLocaleString()}</span>
                              </div>
                              <div className="text-xs text-gray-500 truncate">
                                {event.user_agent}
                              </div>
                            </div>
                          </div>
                        </div>
                        <button
                          onClick={() => handleBlockIP(event.ip_address)}
                          className="admin-button-danger-sm"
                        >
                          <Ban className="h-3 w-3 mr-1" />
                          Block IP
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8">
                  <Shield className="h-12 w-12 text-gray-300 mx-auto mb-4" />
                  <p className="text-gray-500">No security events found</p>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Blocked IPs Tab */}
      {activeTab === 'blocked' && (
        <div className="admin-card">
          <div className="p-6">
            {blockedLoading ? (
              <div className="text-center py-8">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                <p className="text-gray-500 mt-2">Loading blocked IPs...</p>
              </div>
            ) : blockedIPs && blockedIPs.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead>
                    <tr className="border-b border-gray-200">
                      <th className="text-left py-3 px-4 font-medium text-gray-600">IP Address</th>
                      <th className="text-left py-3 px-4 font-medium text-gray-600">Reason</th>
                      <th className="text-left py-3 px-4 font-medium text-gray-600">Attempts</th>
                      <th className="text-left py-3 px-4 font-medium text-gray-600">Blocked At</th>
                      <th className="text-left py-3 px-4 font-medium text-gray-600">Expires</th>
                      <th className="text-left py-3 px-4 font-medium text-gray-600">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {blockedIPs.map((ip, index) => (
                      <tr key={index} className="border-b border-gray-100 hover:bg-gray-50">
                        <td className="py-3 px-4 font-mono text-sm">{ip.ip_address}</td>
                        <td className="py-3 px-4 text-sm">{ip.reason}</td>
                        <td className="py-3 px-4 text-sm">{ip.attempts}</td>
                        <td className="py-3 px-4 text-sm">{new Date(ip.blocked_at).toLocaleString()}</td>
                        <td className="py-3 px-4 text-sm">
                          {ip.expires_at ? new Date(ip.expires_at).toLocaleString() : 'Never'}
                        </td>
                        <td className="py-3 px-4">
                          <button
                            onClick={() => handleUnblockIP(ip.ip_address)}
                            className="admin-button-secondary-sm flex items-center space-x-1"
                          >
                            <Unlock className="h-3 w-3" />
                            <span>Unblock</span>
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="text-center py-8">
                <Ban className="h-12 w-12 text-gray-300 mx-auto mb-4" />
                <p className="text-gray-500">No blocked IP addresses</p>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Settings Tab */}
      {activeTab === 'settings' && (
        <div className="admin-card">
          <div className="p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-6">Security Settings</h3>
            
            {settingsLoading ? (
              <div className="text-center py-8">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                <p className="text-gray-500 mt-2">Loading settings...</p>
              </div>
            ) : settings ? (
              <div className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Max Login Attempts
                    </label>
                    <input
                      type="number"
                      defaultValue={settings.max_login_attempts}
                      onChange={(e) => handleUpdateSettings({ max_login_attempts: parseInt(e.target.value) })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-transparent"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Lockout Duration (minutes)
                    </label>
                    <input
                      type="number"
                      defaultValue={settings.lockout_duration}
                      onChange={(e) => handleUpdateSettings({ lockout_duration: parseInt(e.target.value) })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-transparent"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Rate Limit Window (seconds)
                    </label>
                    <input
                      type="number"
                      defaultValue={settings.rate_limit_window}
                      onChange={(e) => handleUpdateSettings({ rate_limit_window: parseInt(e.target.value) })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-transparent"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Rate Limit Max Requests
                    </label>
                    <input
                      type="number"
                      defaultValue={settings.rate_limit_max}
                      onChange={(e) => handleUpdateSettings({ rate_limit_max: parseInt(e.target.value) })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-transparent"
                    />
                  </div>
                </div>

                <div className="space-y-4">
                  <div className="flex items-center">
                    <input
                      type="checkbox"
                      id="auto_block"
                      defaultChecked={settings.auto_block_enabled}
                      onChange={(e) => handleUpdateSettings({ auto_block_enabled: e.target.checked })}
                      className="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                    />
                    <label htmlFor="auto_block" className="ml-2 block text-sm text-gray-700">
                      Auto-block IPs after exceeding rate limits
                    </label>
                  </div>

                  <div className="flex items-center">
                    <input
                      type="checkbox"
                      id="geo_blocking"
                      defaultChecked={settings.geo_blocking_enabled}
                      onChange={(e) => handleUpdateSettings({ geo_blocking_enabled: e.target.checked })}
                      className="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                    />
                    <label htmlFor="geo_blocking" className="ml-2 block text-sm text-gray-700">
                      Enable geographic blocking
                    </label>
                  </div>
                </div>
              </div>
            ) : null}
          </div>
        </div>
      )}
    </div>
  );
};

export default SecurityCenter;