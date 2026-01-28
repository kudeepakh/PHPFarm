import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { 
  Database, 
  Trash2, 
  RefreshCw, 
  Search,
  Key,
  Clock,
  BarChart3,
  Settings
} from 'lucide-react';
import toast from 'react-hot-toast';

import { apiClient } from '@/services/api';

interface CacheStats {
  total_keys: number;
  memory_usage: string;
  hit_rate: number;
  evictions: number;
  connections: number;
  commands_processed: number;
}

interface CacheKey {
  key: string;
  type: string;
  ttl: number;
  size: string;
  last_accessed: string;
}

const CacheManagement: React.FC = () => {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedPrefix, setSelectedPrefix] = useState('');
  const queryClient = useQueryClient();

  // Fetch cache statistics
  const { data: stats, isLoading: statsLoading } = useQuery<CacheStats>({
    queryKey: ['cache-stats'],
    queryFn: () => apiClient.get('/cache/stats').then(res => res.data.data),
    refetchInterval: 10000,
  });

  // Fetch cache keys
  const { data: keys, isLoading: keysLoading } = useQuery<CacheKey[]>({
    queryKey: ['cache-keys', searchTerm, selectedPrefix],
    queryFn: () => apiClient.get('/cache/keys', { 
      params: { search: searchTerm, prefix: selectedPrefix } 
    }).then(res => res.data.data),
  });

  // Clear cache mutation
  const clearCacheMutation = useMutation({
    mutationFn: (params?: { key?: string; pattern?: string }) => 
      apiClient.delete('/cache/clear', { data: params }),
    onSuccess: () => {
      toast.success('Cache cleared successfully');
      queryClient.invalidateQueries({ queryKey: ['cache-stats'] });
      queryClient.invalidateQueries({ queryKey: ['cache-keys'] });
    },
    onError: (error: any) => {
      toast.error(error.message || 'Failed to clear cache');
    },
  });

  // Flush cache mutation
  const flushCacheMutation = useMutation({
    mutationFn: () => apiClient.delete('/cache/flush'),
    onSuccess: () => {
      toast.success('All cache flushed successfully');
      queryClient.invalidateQueries({ queryKey: ['cache-stats'] });
      queryClient.invalidateQueries({ queryKey: ['cache-keys'] });
    },
    onError: (error: any) => {
      toast.error(error.message || 'Failed to flush cache');
    },
  });

  const handleClearKey = (key: string) => {
    if (confirm(`Are you sure you want to delete cache key: ${key}?`)) {
      clearCacheMutation.mutate({ key });
    }
  };

  const handleClearPattern = () => {
    if (searchTerm && confirm(`Clear all keys matching pattern: ${searchTerm}*?`)) {
      clearCacheMutation.mutate({ pattern: `${searchTerm}*` });
    }
  };

  const handleFlushAll = () => {
    if (confirm('Are you sure you want to flush ALL cache? This cannot be undone!')) {
      flushCacheMutation.mutate();
    }
  };

  const formatTTL = (ttl: number) => {
    if (ttl === -1) return 'Never';
    if (ttl === -2) return 'Expired';
    if (ttl < 60) return `${ttl}s`;
    if (ttl < 3600) return `${Math.floor(ttl / 60)}m`;
    if (ttl < 86400) return `${Math.floor(ttl / 3600)}h`;
    return `${Math.floor(ttl / 86400)}d`;
  };

  const getTypeColor = (type: string) => {
    switch (type.toLowerCase()) {
      case 'string': return 'bg-blue-100 text-blue-800';
      case 'hash': return 'bg-green-100 text-green-800';
      case 'list': return 'bg-yellow-100 text-yellow-800';
      case 'set': return 'bg-purple-100 text-purple-800';
      case 'zset': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Cache Management</h1>
          <p className="text-gray-600">Monitor and manage Redis cache</p>
        </div>
        <button
          onClick={handleFlushAll}
          disabled={flushCacheMutation.isPending}
          className="admin-button-danger flex items-center space-x-2"
        >
          <Trash2 className="h-4 w-4" />
          <span>Flush All</span>
        </button>
      </div>

      {/* Cache Statistics */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-medium text-gray-600">Total Keys</h3>
            <Key className="h-5 w-5 text-gray-400" />
          </div>
          <div className="text-2xl font-bold text-gray-900">
            {stats?.total_keys?.toLocaleString() || '0'}
          </div>
        </div>

        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-medium text-gray-600">Memory Usage</h3>
            <Database className="h-5 w-5 text-gray-400" />
          </div>
          <div className="text-2xl font-bold text-gray-900">
            {stats?.memory_usage || 'N/A'}
          </div>
        </div>

        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-medium text-gray-600">Hit Rate</h3>
            <BarChart3 className="h-5 w-5 text-gray-400" />
          </div>
          <div className="text-2xl font-bold text-gray-900">
            {stats?.hit_rate?.toFixed(1) || '0'}%
          </div>
        </div>

        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-medium text-gray-600">Active Connections</h3>
            <Settings className="h-5 w-5 text-gray-400" />
          </div>
          <div className="text-2xl font-bold text-gray-900">
            {stats?.connections || '0'}
          </div>
        </div>

        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-medium text-gray-600">Commands Processed</h3>
            <RefreshCw className="h-5 w-5 text-gray-400" />
          </div>
          <div className="text-2xl font-bold text-gray-900">
            {stats?.commands_processed?.toLocaleString() || '0'}
          </div>
        </div>

        <div className="admin-card p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-medium text-gray-600">Evictions</h3>
            <Trash2 className="h-5 w-5 text-gray-400" />
          </div>
          <div className="text-2xl font-bold text-red-600">
            {stats?.evictions || '0'}
          </div>
        </div>
      </div>

      {/* Cache Keys Management */}
      <div className="admin-card">
        <div className="p-6 border-b border-gray-200">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-lg font-semibold text-gray-900">Cache Keys</h3>
            <div className="flex items-center space-x-4">
              {searchTerm && (
                <button
                  onClick={handleClearPattern}
                  disabled={clearCacheMutation.isPending}
                  className="admin-button-danger-sm"
                >
                  Clear Pattern
                </button>
              )}
            </div>
          </div>
          
          {/* Search and Filters */}
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                type="text"
                placeholder="Search cache keys..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-transparent"
              />
            </div>
            <select
              value={selectedPrefix}
              onChange={(e) => setSelectedPrefix(e.target.value)}
              className="px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-transparent"
            >
              <option value="">All Prefixes</option>
              <option value="user">user:*</option>
              <option value="session">session:*</option>
              <option value="api">api:*</option>
              <option value="cache">cache:*</option>
            </select>
          </div>
        </div>

        <div className="p-6">
          {keysLoading ? (
            <div className="text-center py-8">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
              <p className="text-gray-500 mt-2">Loading cache keys...</p>
            </div>
          ) : keys && keys.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-gray-200">
                    <th className="text-left py-3 px-4 font-medium text-gray-600">Key</th>
                    <th className="text-left py-3 px-4 font-medium text-gray-600">Type</th>
                    <th className="text-left py-3 px-4 font-medium text-gray-600">TTL</th>
                    <th className="text-left py-3 px-4 font-medium text-gray-600">Size</th>
                    <th className="text-left py-3 px-4 font-medium text-gray-600">Last Accessed</th>
                    <th className="text-left py-3 px-4 font-medium text-gray-600">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {keys.map((key, index) => (
                    <tr key={index} className="border-b border-gray-100 hover:bg-gray-50">
                      <td className="py-3 px-4">
                        <code className="text-sm bg-gray-100 px-2 py-1 rounded">
                          {key.key}
                        </code>
                      </td>
                      <td className="py-3 px-4">
                        <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${getTypeColor(key.type)}`}>
                          {key.type}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">
                        {formatTTL(key.ttl)}
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">
                        {key.size}
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">
                        {new Date(key.last_accessed).toLocaleString()}
                      </td>
                      <td className="py-3 px-4">
                        <button
                          onClick={() => handleClearKey(key.key)}
                          disabled={clearCacheMutation.isPending}
                          className="text-red-600 hover:text-red-800 p-1"
                          title="Delete key"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="text-center py-8">
              <Database className="h-12 w-12 text-gray-300 mx-auto mb-4" />
              <p className="text-gray-500">No cache keys found</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default CacheManagement;