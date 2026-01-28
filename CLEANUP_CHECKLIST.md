# 📋 Repository Cleanup Checklist

This document outlines files and directories that should be cleaned up before pushing to GitHub.

---

## 🗑️ Files/Folders to DELETE or EXCLUDE

### ❌ Temporary Test Scripts (Optional - Keep for Contributors)

These PowerShell test scripts can be kept as they're useful for contributors, but consider moving them to a `/scripts` or `/tools` directory:

**Current Location: `Farm/` root**
- `test_all_apis.ps1`
- `test_api.ps1`
- `test_registration_debug.ps1`
- `test_token_refresh_fix.ps1`
- `view_logs.ps1`
- `logs_stats.ps1`
- `query_logs.ps1`
- `setup_log_retention.ps1`

**Recommendation**: Move to `Farm/scripts/testing/` or keep in root but document in README.

---

### ❌ Temporary PHP Debug Scripts

**Location: `Farm/backend/`**
- `check_routes.php` - Debug script, not needed in production
- `debug_routes.php` - Debug script, not needed in production

**Action**: DELETE these files before pushing

---

### ❌ Temporary Test Scripts in Backend

**Location: `Farm/backend/`**
- `test_complete.sh`
- `test_complete_flow.ps1`
- `test_security_api.sh`

**Action**: Move to `Farm/scripts/testing/` or `Farm/backend/tests/scripts/`

---

### ❌ External/Unrelated Projects

**Location: Root `/argon-dashboard-tailwind-1.0.1/`**

This appears to be an unrelated dashboard template.

**Action**: 
- ✅ ALREADY EXCLUDED in `.gitignore`
- Consider deleting from local workspace if not needed

---

### ❌ Environment Files with Sensitive Data

**Locations:**
- `Farm/.env` (if contains production secrets)
- `Farm/backend/.env`
- `Farm/backend/.env.production`
- `Farm/frontend/.env`

**Action**:
- ✅ ALREADY EXCLUDED in `.gitignore`
- Create `.env.example` files with placeholder values
- Document required environment variables

---

### ❌ Docker Override Files

**Location: `Farm/docker-compose.override.yml`** (if exists)

**Action**: 
- ✅ ALREADY EXCLUDED in `.gitignore`
- These are for local development customization

---

### ❌ IDE and OS Files

**Already handled by `.gitignore`:**
- `.idea/` (PhpStorm/IntelliJ)
- `.vscode/` (VS Code settings - keep if shared team settings)
- `.DS_Store` (macOS)
- `Thumbs.db` (Windows)

---

### ❌ Vendor and Dependencies

**Already handled by `.gitignore`:**
- `Farm/backend/vendor/` (PHP dependencies)
- `Farm/frontend/node_modules/` (Node dependencies)

---

### ❌ Log Files

**Already handled by `.gitignore`:**
- `Farm/backend/logs/*.log`
- All `*.log` files

**Keep**: Empty `.gitkeep` files to preserve directory structure

---

## ✅ Files to CREATE Before Publishing

### 1. License File

**Create: `LICENSE`**

```
MIT License

Copyright (c) [YEAR] [YOUR NAME]

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction...
```

---

### 2. Environment Example Files

**Create: `Farm/.env.example`**

```bash
# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_HOST=mysql
DB_PORT=3306
DB_NAME=phpfrarm
DB_USER=phpfrarm_user
DB_PASSWORD=your_secure_password

# MongoDB
MONGO_HOST=mongodb
MONGO_PORT=27017
MONGO_DATABASE=phpfrarm_logs
MONGO_USER=phpfrarm_user
MONGO_PASSWORD=your_secure_password

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=

# JWT
JWT_SECRET=your_jwt_secret_key_change_this
JWT_ACCESS_EXPIRY=3600
JWT_REFRESH_EXPIRY=2592000

# Email (optional)
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM=noreply@example.com

# SMS (optional)
SMS_PROVIDER=twilio
SMS_API_KEY=
SMS_API_SECRET=
```

**Create: `Farm/backend/.env.example`** (same as above)

**Create: `Farm/frontend/.env.example`**

```bash
VITE_API_URL=http://localhost:8000
VITE_APP_NAME=PHPFrarm
VITE_APP_ENV=development
```

---

### 3. CHANGELOG File

**Create: `CHANGELOG.md`**

```markdown
# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-01-28

### Added
- Initial release of PHPFrarm framework
- Multi-flow authentication system
- RBAC authorization
- MongoDB logging and observability
- Rate limiting and throttling
- React frontend with Tailwind CSS
- Docker-based development environment
- Comprehensive documentation
- Test suite with PHPUnit

### Security
- JWT token management
- Input validation and sanitization
- SQL injection prevention via stored procedures
- XSS protection
- CORS configuration
```

---

### 4. Security Policy

**Create: `SECURITY.md`**

```markdown
# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | ✅ Yes             |

## Reporting a Vulnerability

If you discover a security vulnerability, please email [your-email@example.com] with:

1. Description of the vulnerability
2. Steps to reproduce
3. Potential impact
4. Suggested fix (if available)

**Please do NOT open a public issue for security vulnerabilities.**

We will respond within 48 hours and provide a timeline for a fix.

## Security Best Practices

When using PHPFrarm:
- Keep all dependencies updated
- Use strong JWT secrets
- Enable HTTPS in production
- Rotate secrets regularly
- Follow the principle of least privilege
- Monitor logs for suspicious activity
```

---

## 📂 Recommended Directory Restructure

Consider reorganizing test scripts:

### Current Structure:
```
Farm/
 ├── test_all_apis.ps1
 ├── test_api.ps1
 ├── view_logs.ps1
 └── ...
```

### Recommended Structure:
```
Farm/
 ├── scripts/
 │    ├── testing/
 │    │    ├── test_all_apis.ps1
 │    │    ├── test_api.ps1
 │    │    └── README.md
 │    ├── logging/
 │    │    ├── view_logs.ps1
 │    │    ├── logs_stats.ps1
 │    │    └── query_logs.ps1
 │    └── setup/
 │         ├── setup_log_retention.ps1
 │         └── README.md
```

---

## 🔍 Files to REVIEW Before Publishing

### Documentation Files

Review and ensure these are up-to-date:

- `Farm/README.md` ✅
- `Farm/docs/README.md`
- `Farm/docs/QUICK_START.md`
- `Farm/docs/DEVELOPER_ONBOARDING_GUIDE.md`
- `Farm/docs/API_COMPLETE_REFERENCE.md`

**Check for:**
- No TODOs left in documentation
- No placeholder text
- All links work
- Screenshots are included (if referenced)
- Code examples are tested

---

### Configuration Files

**Review:**
- `Farm/docker-compose.yml` - Ensure no hardcoded secrets
- `Farm/backend/apache-config.conf` - Production-ready settings
- `Farm/frontend/nginx.conf` - Security headers configured

---

## 🎯 Pre-Push Checklist

Before pushing to GitHub, verify:

### Required Files
- [x] `README.md` (root)
- [x] `CONTRIBUTING.md` (root)
- [x] `LICENSE` (root) - TO CREATE
- [x] `SECURITY.md` - TO CREATE
- [x] `CHANGELOG.md` - TO CREATE
- [x] `.gitignore` (root and Farm/)
- [x] `.env.example` files - TO CREATE
- [x] `CODE_OF_CONDUCT.md` (in .github/)
- [x] GitHub issue templates (in .github/ISSUE_TEMPLATE/)
- [x] Pull request template (in .github/)

### Clean Repository
- [ ] No `.env` files tracked
- [ ] No `vendor/` or `node_modules/` tracked
- [ ] No log files tracked
- [ ] No sensitive data in git history
- [ ] Debug scripts moved/removed
- [ ] External projects excluded

### Documentation
- [ ] All markdown files reviewed
- [ ] Code examples tested
- [ ] Links validated
- [ ] Screenshots updated (if any)
- [ ] API documentation current

### Testing
- [ ] All tests pass
- [ ] Integration tests work
- [ ] Docker setup works fresh
- [ ] Test scripts documented

---

## 🚀 Git Commands for Cleanup

### Remove tracked files that should be ignored:

```bash
# Navigate to Farm directory
cd Farm

# Remove .env files from git (if accidentally committed)
git rm --cached .env
git rm --cached backend/.env
git rm --cached frontend/.env

# Remove vendor/node_modules if accidentally committed
git rm -r --cached backend/vendor/
git rm -r --cached frontend/node_modules/

# Remove log files
git rm --cached backend/logs/*.log

# Commit the cleanup
git commit -m "chore: remove sensitive and generated files from git"
```

### Clean up debug scripts:

```bash
# Delete debug scripts
git rm backend/check_routes.php
git rm backend/debug_routes.php

git commit -m "chore: remove debug scripts"
```

---

## ✅ Final Validation

Run these commands before pushing:

```bash
# Check what will be committed
git status

# Check for sensitive data
git diff

# Verify .gitignore is working
git check-ignore -v Farm/backend/vendor/
git check-ignore -v Farm/.env

# Test fresh clone and setup
cd /tmp
git clone /path/to/your/repo test-clone
cd test-clone/Farm
docker-compose up -d
# Verify everything works
```

---

## 📝 Notes

- Keep test scripts if they're useful for contributors (but document them)
- Ensure all secrets use environment variables
- Document any manual setup steps in README
- Test the setup on a fresh machine/environment
