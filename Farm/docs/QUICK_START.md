# 🚀 Quick Reference - PHPFrarm Phase 1

## 🔥 Quick Start

```bash
cd Farm
docker compose up -d
```

**Frontend:** http://localhost:3000
**Backend:** http://localhost:8787

**Login:** test@example.com / Test@1234

---

## ⌨️ Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+K` or `Cmd+K` | Focus search bar |
| `Ctrl+B` or `Cmd+B` | Toggle sidebar |
| `Ctrl+F` or `Cmd+F` | Focus page search (Users page) |
| `Ctrl+R` or `Cmd+R` | Refresh page (Users page) |
| `ESC` | Close mobile menu |
| `Shift+?` | Show shortcuts help |

---

## 📂 Key Locations

### Backend
```
Farm/backend/
├── modules/
│   ├── Auth/          # Authentication APIs
│   ├── User/          # User management
│   └── Storage/       # File storage
├── app/
│   ├── Controllers/   # Base controllers
│   ├── Core/          # Framework core
│   ├── DAO/           # Data access layer
│   └── Services/      # Business logic
└── database/
    ├── mysql/         # Tables & stored procedures
    └── mongo/         # Log schemas
```

### Frontend
```
Farm/frontend/
├── src/
│   ├── components/
│   │   ├── common/    # Reusable components
│   │   └── layouts/   # Layout wrappers
│   ├── pages/         # Page components
│   ├── services/      # API service layer
│   ├── hooks/         # Custom React hooks
│   └── styles/        # Global styles
└── modules/           # Feature modules
```

---

## 🔧 Common Commands

### Docker
```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# Restart backend
docker compose restart backend

# View logs
docker compose logs -f backend
docker compose logs -f frontend

# Execute commands in containers
docker compose exec backend bash
docker compose exec frontend sh

# Install npm packages
docker compose exec frontend npm install <package>
```

### Database
```bash
# MySQL
docker compose exec mysql mysql -uroot -proot phpfrarm

# MongoDB
docker compose exec mongo mongosh mongodb://mongo:27017/phpfrarm
```

---

## 🐛 Troubleshooting

### Login Issues
1. Check user exists: `docker compose exec mysql mysql -uroot -proot phpfrarm -e "SELECT * FROM users WHERE email='test@example.com'"`
2. Verify password hash matches
3. Check account status is 'active'

### Frontend Errors
1. Check container logs: `docker compose logs frontend`
2. Verify npm packages: `docker compose exec frontend npm list`
3. Rebuild if needed: `docker compose up -d --build frontend`

### Backend 500 Errors
1. Check PHP logs: `docker compose logs backend`
2. Verify controller return types are `void`
3. Check RouteGroup attributes exist

### Missing Packages
```bash
# Frontend
docker compose exec frontend npm install

# Backend (if using composer)
docker compose exec backend composer install
```

---

## 📋 Testing Checklist

### Authentication
- [ ] Login with email/password
- [ ] Token refresh works
- [ ] Logout clears session

### User Management
- [ ] List users with pagination
- [ ] Search users by name/email
- [ ] Filter by status/role
- [ ] Lock/unlock accounts
- [ ] Suspend/activate accounts

### UI/UX
- [ ] Loading skeletons appear
- [ ] Toast notifications display
- [ ] Mobile menu works
- [ ] Keyboard shortcuts respond
- [ ] Error boundary catches errors

---

## 🎨 Component Usage

### LoadingSkeleton
```jsx
import LoadingSkeleton from '../components/common/LoadingSkeleton';

// Table loading
<LoadingSkeleton type="table" count={10} />

// Card loading
<LoadingSkeleton type="card" count={3} />

// Text loading
<LoadingSkeleton type="text" count={5} height={20} />
```

### ErrorBoundary
```jsx
import ErrorBoundary from '../components/ErrorBoundary';

<ErrorBoundary>
  <YourComponent />
</ErrorBoundary>
```

### Keyboard Shortcuts
```jsx
import useKeyboardShortcuts from '../hooks/useKeyboardShortcuts';

// In component
useKeyboardShortcuts({
  'ctrl+k': () => console.log('Ctrl+K pressed'),
  'shift+?': () => setShowHelp(true)
});
```

---

## 🔒 API Endpoints

### Authentication
- `POST /api/v1/auth/login` - Login
- `POST /api/v1/auth/logout` - Logout
- `POST /api/v1/auth/refresh` - Refresh token

### User Management
- `GET /api/v1/users` - List users
- `GET /api/v1/users/:id` - Get user
- `POST /api/v1/system/users` - Create user
- `PUT /api/v1/system/users/:id` - Update user
- `DELETE /api/v1/system/users/:id` - Delete user

### Account Status
- `POST /api/v1/system/users/:id/lock` - Lock account
- `POST /api/v1/system/users/:id/unlock` - Unlock account
- `POST /api/v1/system/users/:id/suspend` - Suspend account
- `POST /api/v1/system/users/:id/activate` - Activate account

---

## 📊 Environment Variables

### Backend (.env)
```env
DB_HOST=mysql
DB_PORT=3306
DB_NAME=phpfrarm
DB_USER=root
DB_PASS=root

MONGO_HOST=mongo
MONGO_PORT=27017
MONGO_DB=phpfrarm

JWT_SECRET=your-secret-key
JWT_EXPIRY=3600
```

### Frontend (.env)
```env
REACT_APP_API_URL=http://localhost:8787
REACT_APP_ENV=development
```

---

## 🎯 Quick Fixes

### Reset Admin Password
```sql
UPDATE users 
SET password_hash = '$2y$10$YourHashHere',
    status = 'active',
    account_status = 'active'
WHERE email = 'test@example.com';
```

### Clear All Caches
```bash
docker compose restart backend frontend
```

### Reset Database
```bash
docker compose down -v
docker compose up -d
# Run migrations
```

---

## 📞 Need Help?

1. Check [PHASE1_COMPLETE_SUMMARY.md](./PHASE1_COMPLETE_SUMMARY.md)
2. Check [RETURN_TYPE_BUG_FIX.md](./RETURN_TYPE_BUG_FIX.md)
3. Check Docker logs
4. Check browser console
5. Check network tab

---

## ✅ Status

- ✅ Phase 1 Complete
- ✅ All APIs Integrated
- ✅ All Polish Features Done
- ✅ All Critical Bugs Fixed
- ✅ Production Ready

---

**Last Updated:** 2024-01-24
**Version:** 1.0
**Status:** 🟢 All Systems Operational
