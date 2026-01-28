# Testing Super Admin Access

## ✅ Super Admin Account Details

**Email:** `test@example.com`  
**Password:** (Use existing password for this user)  
**Role:** `superadmin`  
**Permissions:** Full access to all features (`*:*`)

---

## 🧪 Testing Steps

### 1. Verify Superadmin Role Assignment
```powershell
# Check if role is assigned
docker compose exec mysql mysql -u phpfrarm_user -pphpfrarm_password_change_me phpfrarm_db -e "
SELECT 
    u.email,
    r.name as role_name,
    r.priority,
    COUNT(rp.permission_id) as permission_count
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN roles r ON ur.role_id = r.role_id
LEFT JOIN role_permissions rp ON r.role_id = rp.role_id
WHERE u.email = 'test@example.com'
GROUP BY u.email, r.name, r.priority;
"
```

**Expected Output:**
```
email             | role_name   | priority | permission_count
test@example.com  | superadmin  | 1000     | 1 or more
```

### 2. Check All Permissions
```powershell
# View all permissions assigned to superadmin
docker compose exec mysql mysql -u phpfrarm_user -pphpfrarm_password_change_me phpfrarm_db -e "
SELECT p.name, p.description, p.resource, p.action
FROM permissions p
JOIN role_permissions rp ON p.permission_id = rp.permission_id
JOIN roles r ON rp.role_id = r.role_id
WHERE r.name = 'superadmin'
ORDER BY p.resource, p.action;
"
```

### 3. Login and Test Access

1. **Open Frontend:** http://localhost:3900

2. **Login:**
   - Email: `test@example.com`
   - Password: (your password)

3. **Check JWT Token:**
   - Open browser DevTools → Application → Local Storage
   - Verify `access_token` and `refresh_token` exist

4. **Test API Call:**
```javascript
// Open browser console and test:
fetch('http://localhost:8787/api/v1/users', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
    'X-Correlation-Id': 'test-123',
    'X-Transaction-Id': 'test-456',
    'X-Request-Id': 'test-789'
  }
})
.then(r => r.json())
.then(console.log);
```

**Expected:** Should return list of users without 401/403 error

---

## 🔑 Permission Testing

### Test User Management Permissions
```javascript
// In browser console after login:

// List users (requires users:read)
fetch('http://localhost:8787/api/v1/users/admin/list', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
    'X-Correlation-Id': 'test-1',
    'X-Transaction-Id': 'test-2',
    'X-Request-Id': 'test-3'
  }
})
.then(r => r.json())
.then(data => console.log('Admin user list:', data));
```

### Test Role Management (Admin Only)
```javascript
// Get all roles (requires roles:read)
fetch('http://localhost:8787/api/v1/system/roles', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
    'X-Correlation-Id': 'test-1',
    'X-Transaction-Id': 'test-2',
    'X-Request-Id': 'test-3'
  }
})
.then(r => r.json())
.then(data => console.log('Roles:', data));
```

### Test Cache Management (Admin Only)
```javascript
// Get cache statistics (requires cache:read)
fetch('http://localhost:8787/api/v1/system/cache/statistics', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
    'X-Correlation-Id': 'test-1',
    'X-Transaction-Id': 'test-2',
    'X-Request-Id': 'test-3'
  }
})
.then(r => r.json())
.then(data => console.log('Cache stats:', data));
```

---

## 🐛 Troubleshooting

### Issue: "401 Unauthorized"
**Causes:**
1. Token expired
2. Token not in request header
3. User not authenticated

**Fix:**
```javascript
// Check if token exists
console.log('Token:', localStorage.getItem('access_token'));

// If null, login again
// If exists, try refresh:
fetch('http://localhost:8787/api/v1/auth/refresh', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    refresh_token: localStorage.getItem('refresh_token')
  })
})
.then(r => r.json())
.then(data => {
  if (data.success) {
    localStorage.setItem('access_token', data.data.access_token);
    localStorage.setItem('refresh_token', data.data.refresh_token);
    console.log('Token refreshed!');
  }
});
```

### Issue: "403 Forbidden"
**Causes:**
1. User lacks required permission
2. Permission check failing on backend

**Fix:**
```powershell
# Verify user has permission
docker compose exec mysql mysql -u phpfrarm_user -pphpfrarm_password_change_me phpfrarm_db -e "
SELECT p.name
FROM permissions p
JOIN role_permissions rp ON p.permission_id = rp.permission_id
JOIN roles r ON rp.role_id = r.role_id
JOIN user_roles ur ON r.role_id = ur.role_id
JOIN users u ON ur.user_id = u.id
WHERE u.email = 'test@example.com';
"
```

### Issue: "Missing required headers"
**Fix:**
Always include these headers:
```javascript
const headers = {
  'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
  'Content-Type': 'application/json',
  'X-Correlation-Id': crypto.randomUUID(),
  'X-Transaction-Id': crypto.randomUUID(),
  'X-Request-Id': crypto.randomUUID()
};
```

---

## 📋 Quick Verification Checklist

- [ ] Super admin role exists in database
- [ ] test@example.com has superadmin role assigned
- [ ] Superadmin has wildcard permission (`*:*`)
- [ ] Can login successfully at http://localhost:3900
- [ ] Access token is stored in localStorage
- [ ] Can access /api/v1/users endpoint
- [ ] Can access /api/v1/system/* endpoints
- [ ] No 401/403 errors on admin routes
- [ ] Health check shows all services ready
- [ ] Backend logs show successful authentication

---

## 🎯 Next Steps After Verification

Once you've confirmed superadmin access works:

1. **Test existing admin features:**
   - User management page
   - Role management
   - Permission management
   - Cache management
   - Storage management

2. **Start building new features:**
   - Follow the implementation plan
   - Create components one by one
   - Test each feature incrementally

3. **Create additional admin users:**
```sql
-- Create more superadmins if needed
INSERT INTO user_roles (user_role_id, user_id, role_id)
VALUES (
  UUID(),
  (SELECT id FROM users WHERE email = 'another_admin@example.com'),
  '01000000-0000-7000-8000-000000000001'
);
```

---

**Remember:** The superadmin account has full access to everything. Use it wisely! 🔐
