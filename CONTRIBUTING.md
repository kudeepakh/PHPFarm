# 🤝 Contributing to PHPFrarm

Thank you for your interest in contributing to PHPFrarm! This guide will help you contribute effectively while maintaining our enterprise standards.

---

## 📋 Before You Start

### Required Reading
1. [Developer Onboarding Guide](./Farm/docs/DEVELOPER_ONBOARDING_GUIDE.md) - Understand the framework
2. [Code Standards](./Farm/docs/CODE_STANDARDS_FINAL_SUMMARY.md) - Code quality requirements
3. [API Features Checklist](./Farm/docs/api/API-Features.md) - 250+ item checklist
4. [Architecture Overview](./Farm/docs/architecture/ARCHITECTURE.md) - Architecture principles

### Prerequisites
- ✅ Framework environment set up and running
- ✅ Completed onboarding guide
- ✅ Built at least one test API
- ✅ Understand service layer pattern
- ✅ Know stored procedure requirement

---

## 🎯 Types of Contributions

### 1️⃣ Bug Fixes
Small, focused changes to fix existing issues
- Fix identified bugs
- Improve error handling
- Correct documentation typos
- Update deprecated dependencies

### 2️⃣ Feature Additions
New functionality following existing patterns
- New API endpoints
- New services
- New utilities
- Module enhancements

### 3️⃣ Performance Improvements
Optimizations without breaking changes
- Query optimization
- Caching improvements
- Code efficiency
- Resource usage reduction

### 4️⃣ Documentation
Improving guides and references
- Tutorial additions
- Code examples
- Architecture diagrams
- Troubleshooting guides

---

## 🚦 Development Process

### Step 1: Create an Issue

Before starting work, create an issue describing:
- The problem or feature
- Your proposed solution
- Impact on existing code
- Related checklist items from [API-Features.md](./Farm/docs/api/API-Features.md)

### Step 2: Fork and Branch

```bash
# Fork the repository on GitHub
# Clone your fork
git clone https://github.com/YOUR_USERNAME/phpfrarm.git
cd phpfrarm

# Create feature branch
git checkout -b feature/your-feature-name

# Or for bug fixes
git checkout -b fix/bug-description
```

### Step 3: Follow the Architecture Pattern

**MANDATORY: All changes must follow this pattern:**

```
Controller → Service → DAO → Stored Procedure → Database
```

**Example: Adding a new feature**

```bash
# 1. Database first (if needed)
Farm/backend/database/mysql/tables/your_module.sql
Farm/backend/database/mysql/stored_procedures/your_module/

# 2. DAO Layer
Farm/backend/app/DAO/YourModuleDAO.php

# 3. Service Layer
Farm/backend/modules/YourModule/Services/YourService.php

# 4. Controller
Farm/backend/modules/YourModule/Controllers/YourController.php

# 5. Routes
Farm/backend/modules/YourModule/routes.php

# 6. Tests
Farm/backend/tests/Unit/YourModuleTest.php
Farm/backend/tests/Integration/YourModuleIntegrationTest.php
```

### Step 4: Write Tests

All code must include tests:

```bash
# Unit tests
composer test:unit

# Integration tests
composer test:integration

# All tests
composer test
```

### Step 5: Follow Code Standards

- **PSR-12** coding standards
- **Return type declarations** on all methods
- **Type hints** for all parameters
- **DocBlocks** for all public methods
- **No raw SQL** - stored procedures only
- **Error handling** with proper HTTP status codes
- **Logging** with correlation IDs

### Step 6: Update Documentation

- Update relevant markdown files
- Add code examples
- Update OpenAPI specifications
- Add inline code comments

### Step 7: Commit Your Changes

```bash
# Stage your changes
git add .

# Commit with descriptive message
git commit -m "feat: add user profile endpoint

- Add GET /api/v1/users/profile endpoint
- Implement profile service layer
- Add stored procedures for profile data
- Include unit and integration tests
- Update API documentation

Closes #123"
```

### Commit Message Format

```
<type>: <subject>

<body>

<footer>
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation only
- `style`: Code style changes (formatting)
- `refactor`: Code refactoring
- `test`: Adding tests
- `chore`: Maintenance tasks

### Step 8: Push and Create Pull Request

```bash
# Push to your fork
git push origin feature/your-feature-name

# Create Pull Request on GitHub
# Use the PR template
# Link to the related issue
```

---

## ✅ Pull Request Checklist

Before submitting your PR, ensure:

### Code Quality
- [ ] Code follows PSR-12 standards
- [ ] All methods have return type declarations
- [ ] All parameters have type hints
- [ ] DocBlocks added for public methods
- [ ] No hardcoded values (use config)

### Database
- [ ] Only stored procedures used (no raw SQL)
- [ ] Database migrations included (if schema changed)
- [ ] Stored procedures tested

### Security
- [ ] Authentication implemented
- [ ] Authorization/RBAC configured
- [ ] Input validation added
- [ ] XSS/SQL injection prevented
- [ ] Sensitive data not logged

### Framework Standards
- [ ] Correlation/Transaction IDs used
- [ ] MongoDB logging configured
- [ ] Rate limiting applied (if public API)
- [ ] Error handling implemented
- [ ] Standard response envelope used

### Testing
- [ ] Unit tests written
- [ ] Integration tests written
- [ ] All tests pass locally
- [ ] Code coverage maintained/improved

### Documentation
- [ ] README updated (if needed)
- [ ] API documentation updated
- [ ] Code comments added
- [ ] Example usage provided

---

## 🚫 Common Mistakes to Avoid

### ❌ DON'T:
- Write raw SQL queries (use stored procedures)
- Skip tests
- Hardcode credentials or secrets
- Expose internal errors to API responses
- Bypass authentication/authorization
- Ignore the 250+ item checklist
- Create duplicate functionality
- Use `var_dump()` or `echo` for debugging

### ✅ DO:
- Follow existing patterns
- Write comprehensive tests
- Use environment variables for config
- Return structured error responses
- Implement proper security
- Check all applicable checklist items
- Reuse existing services/utilities
- Use the Logger class for debugging

---

## 🧪 Testing Guidelines

### Unit Tests
Test individual components in isolation:

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Modules\YourModule\Services\YourService;

class YourServiceTest extends TestCase
{
    public function testServiceMethod(): void
    {
        $service = new YourService();
        $result = $service->method();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
    }
}
```

### Integration Tests
Test the full flow from controller to database:

```php
<?php
namespace Tests\Integration;

use Tests\TestCase;

class YourModuleTest extends TestCase
{
    public function testEndpointReturnsExpectedData(): void
    {
        $response = $this->post('/api/v1/your-endpoint', [
            'field' => 'value'
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'meta'
        ]);
    }
}
```

---

## 📚 Additional Resources

### Framework Documentation
- [Developer Onboarding](./Farm/docs/DEVELOPER_ONBOARDING_GUIDE.md)
- [Quick Start Guide](./Farm/docs/QUICK_START.md)
- [API Complete Reference](./Farm/docs/API_COMPLETE_REFERENCE.md)
- [Architecture Guide](./Farm/docs/architecture/ARCHITECTURE.md)

### Code Examples
- [Module Development](./Farm/docs/modules/MODULE_DEVELOPMENT.md)
- [Testing Guide](./Farm/docs/guides/TESTING_GUIDE.md)
- [Security Guide](./Farm/docs/security/AUTHENTICATION.md)

### Community
- [Code of Conduct](./.github/CODE_OF_CONDUCT.md)
- [Issue Templates](./.github/ISSUE_TEMPLATE/)
- [Pull Request Template](./.github/PULL_REQUEST_TEMPLATE.md)

---

## 🎉 Recognition

Contributors will be:
- Listed in our contributors file
- Acknowledged in release notes
- Credited in documentation

---

## 💬 Questions?

- **Documentation**: Check [./Farm/docs/](./Farm/docs/)
- **Issues**: [GitHub Issues](https://github.com/yourusername/phpfrarm/issues)
- **Discussions**: [GitHub Discussions](https://github.com/yourusername/phpfrarm/discussions)

---

## 📄 License

By contributing, you agree that your contributions will be licensed under the same license as the project (MIT License).

---

<div align="center">

**Thank you for contributing to PHPFrarm!** 🎉

Every contribution, no matter how small, helps make this framework better.

</div>
