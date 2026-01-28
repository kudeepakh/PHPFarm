import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { 
  Shield, 
  Plus, 
  Edit, 
  Trash2, 
  Search,
  Users,
  Key,
  Settings,
  CheckCircle,
  XCircle
} from 'lucide-react';
import toast from 'react-hot-toast';

import { apiClient } from '@/services/api';

interface Role {
  id: string;
  name: string;
  description: string;
  permissions: Permission[];
  user_count: number;
  permissions_count: number;
  is_system_role: boolean;
  priority: number;
  created_at: string;
  updated_at: string;
}

interface Permission {
  id: string;
  name: string;
  description: string;
  module: string;
  action: string;
}

// API response types
interface ApiRole {
  role_id: string;
  name: string;
  description: string;
  priority: number;
  is_system_role: number;
  created_at: string;
  updated_at: string;
}

interface ApiPermission {
  permission_id: string;
  name: string;
  description: string;
  resource: string;
  action: string;
  created_at: string;
  updated_at: string;
}

const RoleManagement: React.FC = () => {
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingRole, setEditingRole] = useState<Role | null>(null);
  const [searchTerm, setSearchTerm] = useState('');
  const queryClient = useQueryClient();

  // Fetch roles
  const { data: roles, isLoading: rolesLoading } = useQuery<Role[]>({
    queryKey: ['roles', searchTerm],
    queryFn: async () => {
      const res = await apiClient.get('/roles', { 
        params: { search: searchTerm } 
      });
      const apiRoles: ApiRole[] = res.data.data?.roles || res.data.data || [];
      // Map API response to frontend interface
      return apiRoles.map((role: any) => ({
        id: role.role_id,
        name: role.name,
        description: role.description || '',
        permissions: [],
        user_count: role.user_count || 0,
        permissions_count: role.permissions_count || 0,
        is_system_role: Boolean(role.is_system_role),
        priority: role.priority || 0,
        created_at: role.created_at,
        updated_at: role.updated_at,
      }));
    },
  });

  // Fetch all permissions
  const { data: permissions } = useQuery<Permission[]>({
    queryKey: ['permissions'],
    queryFn: async () => {
      const res = await apiClient.get('/permissions');
      const apiPermissions: ApiPermission[] = res.data.data?.permissions || res.data.data || [];
      // Map API response to frontend interface
      return apiPermissions.map((perm: ApiPermission) => ({
        id: perm.permission_id,
        name: perm.name,
        description: perm.description || '',
        module: perm.resource,
        action: perm.action,
      }));
    },
  });

  // Create role mutation
  const createRoleMutation = useMutation({
    mutationFn: (roleData: { name: string; description: string; permissions: string[] }) => 
      apiClient.post('/roles', roleData),
    onSuccess: () => {
      toast.success('Role created successfully');
      setShowCreateModal(false);
      queryClient.invalidateQueries({ queryKey: ['roles'] });
    },
    onError: (error: any) => {
      toast.error(error.message || 'Failed to create role');
    },
  });

  // Update role mutation
  const updateRoleMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: { name: string; description: string; permissions: string[] } }) => 
      apiClient.put(`/roles/${id}`, data),
    onSuccess: () => {
      toast.success('Role updated successfully');
      setEditingRole(null);
      queryClient.invalidateQueries({ queryKey: ['roles'] });
    },
    onError: (error: any) => {
      toast.error(error.message || 'Failed to update role');
    },
  });

  // Delete role mutation
  const deleteRoleMutation = useMutation({
    mutationFn: (id: string) => apiClient.delete(`/roles/${id}`),
    onSuccess: () => {
      toast.success('Role deleted successfully');
      queryClient.invalidateQueries({ queryKey: ['roles'] });
    },
    onError: (error: any) => {
      toast.error(error.message || 'Failed to delete role');
    },
  });

  const handleCreateRole = (roleData: { name: string; description: string; permissions: string[] }) => {
    createRoleMutation.mutate(roleData);
  };

  const handleUpdateRole = (roleData: { name: string; description: string; permissions: string[] }) => {
    if (editingRole) {
      updateRoleMutation.mutate({ id: editingRole.id, data: roleData });
    }
  };

  const handleDeleteRole = (role: Role) => {
    if (role.is_system_role) {
      toast.error(`Cannot delete system role "${role.name}"`);
      return;
    }
    
    if (role.user_count > 0) {
      toast.error(`Cannot delete role "${role.name}" - it has ${role.user_count} active users`);
      return;
    }
    
    if (confirm(`Are you sure you want to delete the role "${role.name}"?`)) {
      deleteRoleMutation.mutate(role.id);
    }
  };

  // Group permissions by module
  const groupedPermissions = permissions?.reduce((acc, permission) => {
    if (!acc[permission.module]) {
      acc[permission.module] = [];
    }
    acc[permission.module].push(permission);
    return acc;
  }, {} as Record<string, Permission[]>) || {};

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Role Management</h1>
          <p className="text-gray-600">Manage user roles and permissions</p>
        </div>
        <button
          onClick={() => setShowCreateModal(true)}
          className="admin-button-primary flex items-center space-x-2"
        >
          <Plus className="h-4 w-4" />
          <span>Add Role</span>
        </button>
      </div>

      {/* Search */}
      <div className="admin-card p-4">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            type="text"
            placeholder="Search roles..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-transparent"
          />
        </div>
      </div>

      {/* Roles Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {rolesLoading ? (
          <div className="col-span-full text-center py-8">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
            <p className="text-gray-500 mt-2">Loading roles...</p>
          </div>
        ) : roles && roles.length > 0 ? (
          roles.map((role) => (
            <div key={role.id} className="admin-card p-6">
              <div className="flex items-start justify-between mb-4">
                <div className="flex items-center space-x-3">
                  <div className="flex-shrink-0">
                    <Shield className="h-8 w-8 text-primary" />
                  </div>
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900">{role.name}</h3>
                    <p className="text-sm text-gray-600">{role.description}</p>
                  </div>
                </div>
                <div className="flex items-center space-x-1">
                  <button
                    onClick={() => setEditingRole(role)}
                    className="text-blue-600 hover:text-blue-800 p-1"
                    title="Edit role"
                  >
                    <Edit className="h-4 w-4" />
                  </button>
                  <button
                    onClick={() => handleDeleteRole(role)}
                    className="text-red-600 hover:text-red-800 p-1"
                    title="Delete role"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              </div>

              <div className="space-y-3">
                {/* User count */}
                <div className="flex items-center justify-between text-sm">
                  <span className="flex items-center text-gray-600">
                    <Users className="h-4 w-4 mr-1" />
                    Users
                  </span>
                  <span className="font-medium">{role.user_count}</span>
                </div>

                {/* Permission count */}
                <div className="flex items-center justify-between text-sm">
                  <span className="flex items-center text-gray-600">
                    <Key className="h-4 w-4 mr-1" />
                    Permissions
                  </span>
                  <span className="font-medium">{role.permissions_count}</span>
                </div>

                {/* Permissions preview */}
                {role.permissions.length > 0 && (
                  <div className="pt-2 border-t border-gray-200">
                    <p className="text-xs text-gray-600 mb-2">Permissions:</p>
                    <div className="flex flex-wrap gap-1">
                      {role.permissions.slice(0, 3).map((permission) => (
                        <span
                          key={permission.id}
                          className="inline-flex px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full"
                        >
                          {permission.name}
                        </span>
                      ))}
                      {role.permissions.length > 3 && (
                        <span className="inline-flex px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">
                          +{role.permissions.length - 3} more
                        </span>
                      )}
                    </div>
                  </div>
                )}

                {/* Created date */}
                <div className="text-xs text-gray-500 pt-2">
                  Created: {new Date(role.created_at).toLocaleDateString()}
                </div>
              </div>
            </div>
          ))
        ) : (
          <div className="col-span-full text-center py-8">
            <Shield className="h-12 w-12 text-gray-300 mx-auto mb-4" />
            <p className="text-gray-500">No roles found</p>
          </div>
        )}
      </div>

      {/* Create/Edit Role Modal */}
      {(showCreateModal || editingRole) && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div className="p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">
                {editingRole ? 'Edit Role' : 'Create Role'}
              </h3>
              
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  const formData = new FormData(e.target as HTMLFormElement);
                  const selectedPermissions = Array.from(formData.getAll('permissions')) as string[];
                  
                  const roleData = {
                    name: formData.get('name') as string,
                    description: formData.get('description') as string,
                    permissions: selectedPermissions,
                  };
                  
                  if (editingRole) {
                    handleUpdateRole(roleData);
                  } else {
                    handleCreateRole(roleData);
                  }
                }}
                className="space-y-4"
              >
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Role Name
                  </label>
                  <input
                    name="name"
                    type="text"
                    required
                    defaultValue={editingRole?.name || ''}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="e.g., Admin, User, Manager"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Description
                  </label>
                  <textarea
                    name="description"
                    rows={3}
                    defaultValue={editingRole?.description || ''}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="Describe this role's purpose and responsibilities"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-3">
                    Permissions
                  </label>
                  <div className="border border-gray-300 rounded-md max-h-64 overflow-y-auto">
                    {Object.entries(groupedPermissions).map(([module, modulePermissions]) => (
                      <div key={module} className="p-3 border-b border-gray-200 last:border-b-0">
                        <h4 className="font-medium text-gray-900 mb-2 capitalize">{module}</h4>
                        <div className="space-y-2">
                          {modulePermissions.map((permission) => {
                            const isChecked = editingRole?.permissions.some(p => p.id === permission.id) || false;
                            return (
                              <label key={permission.id} className="flex items-center">
                                <input
                                  type="checkbox"
                                  name="permissions"
                                  value={permission.id}
                                  defaultChecked={isChecked}
                                  className="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                                />
                                <span className="ml-2 text-sm text-gray-700">
                                  {permission.name}
                                  {permission.description && (
                                    <span className="text-gray-500"> - {permission.description}</span>
                                  )}
                                </span>
                              </label>
                            );
                          })}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="flex justify-end space-x-3 pt-4">
                  <button
                    type="button"
                    onClick={() => {
                      setShowCreateModal(false);
                      setEditingRole(null);
                    }}
                    className="admin-button-secondary"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    disabled={createRoleMutation.isPending || updateRoleMutation.isPending}
                    className="admin-button-primary"
                  >
                    {editingRole ? 'Update' : 'Create'} Role
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default RoleManagement;