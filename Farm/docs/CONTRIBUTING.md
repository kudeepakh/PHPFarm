# 🤝 Contributing to PHPFrarm Framework

**Thank you for your interest in contributing to PHPFrarm!**

This guide outlines how to add features, fix bugs, and improve the framework while maintaining our enterprise standards.

---

## 📋 **Before You Start**

### Required Reading
1. **[DEVELOPER_ONBOARDING_GUIDE.md](DEVELOPER_ONBOARDING_GUIDE.md)** - Understand the framework
2. **[CODE_STANDARDS_FINAL_SUMMARY.md](CODE_STANDARDS_FINAL_SUMMARY.md)** - Code quality requirements
3. **[api/API-Features.md](api/API-Features.md)** - 250+ item checklist (you must follow this)
4. **[architecture/ARCHITECTURE.md](architecture/ARCHITECTURE.md)** - Architecture principles

### Prerequisites
- ✅ Framework environment set up and running
- ✅ Completed onboarding guide
- ✅ Built at least one test API
- ✅ Understand service layer pattern
- ✅ Know stored procedure requirement

---

## 🎯 **Contribution Types**

### 1️⃣ Bug Fixes
**Small, focused changes to fix existing issues**
- Fix identified bugs
- Improve error handling
- Correct documentation typos
- Update deprecated dependencies

### 2️⃣ Feature Additions
**New functionality following existing patterns**
- New API endpoints
- New services
- New utilities
- Module enhancements

### 3️⃣ Performance Improvements
**Optimizations without breaking changes**
- Query optimization
- Caching improvements
- Code efficiency
- Resource usage reduction

### 4️⃣ Documentation
**Improving guides and references**
- Tutorial additions
- Code examples
- Architecture diagrams
- Troubleshooting guides

---

## 🚦 **Development Process**

### Step 1: Plan Your Contribution

**Before writing code, answer these questions:**
1. What problem does this solve?
2. Does it follow existing patterns?
3. Does it require database changes?
4. Does it impact existing APIs?
5. Is it covered by the 250+ item checklist?

**Create a brief design document:**
```markdown
## Feature: [Name]
**Problem:** [What you're solving]
**Solution:** [High-level approach]
**Impact:** [What changes]
**Checklist Items:** [Which API-Features.md items apply]
```

### Step 2: Set Up Your Branch

```bash
# Create feature branch
git checkout -b feature/your-feature-name

# Or for bug fixes
git checkout -b fix/bug-description
```

### Step 3: Follow the Pattern

**MANDATORY: Use the established architecture**

```
Your Change Must Follow This Pattern:
Controller → Service → DAO → Stored Procedure → Database
```

**Example: Adding a new feature**

```bash
# 1. Database first
backend/database/mysql/tables/your_feature.sql
backend/database/mysql/stored_procedures/your_feature/

# 2. DAO layer
backend/modules/YourFeature/DAO/YourDAO.php

# 3. Service layer (business logic)
backend/modules/YourFeature/Services/YourService.php

# 4. Controller layer (validation & delegation)
backend/modules/YourFeature/Controllers/YourController.php

# 5. Tests
backend/tests/YourFeature/YourServiceTest.php
```

### Step 4: Code Standards Compliance

**Run these checks before committing:**

```bash
# PHP syntax check
find backend -name "*.php" -exec php -l {} \;

# Code style (if configured)
./vendor/bin/phpcs backend/modules/YourFeature

# Run tests
./vendor/bin/phpunit tests/YourFeature
```

**Your code MUST:**
- ✅ Use service layer (NO business logic in controllers)
- ✅ Use stored procedures (NO raw SQL)
- ✅ Use `Response::success()` and `Response::error()`
- ✅ Include correlation ID in all logs
- ✅ Follow naming conventions
- ✅ Include PHPDoc comments
- ✅ Handle errors gracefully
- ✅ Validate all inputs

**Your code MUST NOT:**
- ❌ Have business logic in controllers
- ❌ Use raw SQL queries
- ❌ Use `getenv()` directly (use `env()` helper)
- ❌ Have hardcoded values
- ❌ Expose sensitive data in responses
- ❌ Skip input validation
- ❌ Ignore error handling

### Step 5: Testing Requirements

**Every contribution needs:**
1. **Unit Tests** - Test services independently
2. **Integration Tests** - Test API endpoints
3. **Manual Testing** - Use Postman/curl
4. **Checklist Review** - Verify API-Features.md items

**Example Test:**
```php
<?php

namespace PHPFrarm\Tests\YourFeature;

use PHPFrarm\Tests\TestCase;
use PHPFrarm\Modules\YourFeature\Services\YourService;

class YourServiceTest extends TestCase
{
    public function testCreateItem()
    {
        $service = new YourService();
        $result = $service->createItem('Test', 'Description', 'corr-123');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('item_id', $result);
        $this->assertEquals('Test', $result['name']);
    }
}
```

### Step 6: Documentation Updates

**Update these files as needed:**
- **README.md** (if adding major feature)
- **INDEX.md** (if adding new documentation)
- **QUICK_REFERENCE.md** (if adding new commands)
- **guides/** (if creating new guide)
- **API_COMPLETE_REFERENCE.md** (if adding API endpoints)

### Step 7: Commit Guidelines

**Use conventional commit format:**

```bash
# Feature
git commit -m "feat(module): add item creation endpoint"

# Bug fix
git commit -m "fix(auth): resolve OTP expiry issue"

# Documentation
git commit -m "docs(guides): add caching examples"

# Performance
git commit -m "perf(api): optimize user query"

# Refactor
git commit -m "refactor(service): extract validation logic"
```

**Commit message structure:**
```
<type>(<scope>): <subject>

<body>

<footer>
```

**Types:**
- `feat` - New feature
- `fix` - Bug fix
- `docs` - Documentation only
- `style` - Code style (formatting, no logic change)
- `refactor` - Code refactoring
- `perf` - Performance improvement
- `test` - Adding tests
- `chore` - Build/config changes

---

## ✅ **Pre-Submission Checklist**

Before submitting your contribution:

### Code Quality
- [ ] No business logic in controllers
- [ ] All database access via stored procedures
- [ ] Service layer used for business logic
- [ ] All inputs validated
- [ ] All outputs use Response:: methods
- [ ] Correlation IDs in logs
- [ ] No hardcoded values
- [ ] No raw SQL queries
- [ ] No `getenv()` calls (use `env()`)
- [ ] Error handling implemented

### Testing
- [ ] Unit tests written and passing
- [ ] Integration tests passing
- [ ] Manual testing completed
- [ ] All tests run successfully: `./vendor/bin/phpunit`
- [ ] No broken existing tests

### Documentation
- [ ] Code comments added (PHPDoc)
- [ ] README updated (if needed)
- [ ] API documentation updated (if adding endpoints)
- [ ] CHANGELOG updated (if applicable)

### API Checklist Compliance
- [ ] Reviewed [API-Features.md](api/API-Features.md)
- [ ] All applicable checklist items satisfied
- [ ] Authentication implemented (if required)
- [ ] Rate limiting considered
- [ ] Logging implemented
- [ ] Error responses standardized

### Database
- [ ] Migration scripts created (if schema changes)
- [ ] Stored procedures documented
- [ ] Indexes added (if needed)
- [ ] Backward compatibility maintained

---

## 🎨 **Code Style Guide**

### PHP Code Standards

**Naming Conventions:**
```php
// Classes: PascalCase
class UserService {}

// Methods: camelCase
public function createUser() {}

// Variables: camelCase
$userId = '123';

// Constants: UPPER_SNAKE_CASE
const MAX_ATTEMPTS = 3;

// Stored Procedures: sp_snake_case
sp_create_user

// Database Tables: snake_case
user_identifiers
```

**File Organization:**
```php
<?php

namespace PHPFrarm\Modules\Feature\Services;

// Imports grouped and sorted
use PHPFrarm\Core\Logger;
use PHPFrarm\Core\TraceContext;
use PHPFrarm\Modules\Feature\DAO\FeatureDAO;

/**
 * Feature Service
 * 
 * Handles business logic for feature management
 */
class FeatureService
{
    private FeatureDAO $dao;

    public function __construct()
    {
        $this->dao = new FeatureDAO();
    }

    /**
     * Create new feature
     * 
     * @param string $name Feature name
     * @param string $correlationId Trace correlation ID
     * @return array Created feature data
     * @throws \Exception If creation fails
     */
    public function createFeature(string $name, string $correlationId): array
    {
        // Implementation
    }
}
```

### Database Standards

**Table Naming:**
```sql
-- Use snake_case
CREATE TABLE user_sessions (...);
CREATE TABLE otp_verifications (...);

-- Always include:
-- - Primary key with descriptive name
-- - created_at, updated_at timestamps
-- - deleted_at for soft deletes
-- - Indexes for foreign keys and lookup fields
```

**Stored Procedure Naming:**
```sql
-- Format: sp_<action>_<entity>
DELIMITER $$

CREATE PROCEDURE sp_create_user(...)
CREATE PROCEDURE sp_get_user_by_id(...)
CREATE PROCEDURE sp_update_user_status(...)
CREATE PROCEDURE sp_delete_user(...) -- Soft delete only

DELIMITER ;
```

---

## 🔍 **Code Review Process**

Your contribution will be reviewed for:

### 1. Architecture Compliance
- ✅ Follows Controller → Service → DAO → SP pattern
- ✅ No business logic in controllers
- ✅ Service layer properly used
- ✅ No raw SQL queries

### 2. Code Quality
- ✅ Clean, readable code
- ✅ Proper naming conventions
- ✅ Adequate comments
- ✅ No code duplication
- ✅ Error handling implemented

### 3. Security
- ✅ Input validation present
- ✅ No SQL injection vulnerabilities
- ✅ No sensitive data exposure
- ✅ Authentication/authorization implemented
- ✅ Proper secrets management

### 4. Testing
- ✅ Tests included
- ✅ Tests pass
- ✅ Good test coverage
- ✅ Edge cases considered

### 5. Documentation
- ✅ Code documented
- ✅ API docs updated
- ✅ README updated (if needed)

---

## 🚫 **Common Mistakes to Avoid**

### ❌ Don't Do This:
```php
// 1. Business logic in controller
public function create() {
    $id = bin2hex(random_bytes(16)); // ❌ Should be in service
    $hash = hash('sha256', $data);    // ❌ Should be in service
}

// 2. Raw SQL query
$query = "SELECT * FROM users WHERE id = ?"; // ❌ Use stored procedure

// 3. Direct getenv()
$secret = getenv('JWT_SECRET'); // ❌ Use env() helper

// 4. No error handling
$result = $this->service->create($data); // ❌ No try-catch

// 5. No validation
$name = $_POST['name']; // ❌ No input validation
```

### ✅ Do This Instead:
```php
// 1. Delegate to service
public function create() {
    $result = $this->service->createItem($validatedData, $correlationId);
}

// 2. Use stored procedure
Database::callProcedure('sp_get_user', [$userId]);

// 3. Use env() helper + config
$secret = env('JWT_SECRET', 'default');

// 4. Handle errors
try {
    $result = $this->service->create($data);
} catch (\Exception $e) {
    Logger::error(...);
    throw new BadRequestHttpException(...);
}

// 5. Validate input
$validator = new InputValidator([...]);
$validatedData = $validator->validate($data);
```

---

## 🏆 **Recognition**

Contributors who follow these guidelines and maintain quality standards will be:
- ✅ Listed in CONTRIBUTORS.md
- ✅ Recognized in release notes
- ✅ Considered for maintainer role

---

## 📚 **Additional Resources**

### Framework Documentation
- [INDEX.md](INDEX.md) - Complete documentation index
- [DEVELOPER_ONBOARDING_GUIDE.md](DEVELOPER_ONBOARDING_GUIDE.md) - Onboarding guide
- [CODE_STANDARDS_FINAL_SUMMARY.md](CODE_STANDARDS_FINAL_SUMMARY.md) - Quality standards
- [api/API-Features.md](api/API-Features.md) - Complete checklist

### Guides
- [guides/DEVELOPER_GUIDE.md](guides/DEVELOPER_GUIDE.md) - Development handbook
- [guides/TESTING_GUIDE.md](guides/TESTING_GUIDE.md) - Testing strategies
- [architecture/ARCHITECTURE.md](architecture/ARCHITECTURE.md) - System architecture

### Examples
- `backend/modules/Auth/` - Authentication module (complete example)
- `backend/modules/System/` - System APIs (multiple examples)
- `backend/modules/User/` - User management (CRUD example)

---

## 🆘 **Need Help?**

### Questions About:
- **Architecture** → Review [architecture/ARCHITECTURE.md](architecture/ARCHITECTURE.md)
- **Coding Standards** → Review [CODE_STANDARDS_FINAL_SUMMARY.md](CODE_STANDARDS_FINAL_SUMMARY.md)
- **API Checklist** → Review [api/API-Features.md](api/API-Features.md)
- **Patterns** → Study existing modules in `backend/modules/`

### Still Stuck?
1. Check [INDEX.md](INDEX.md) for relevant documentation
2. Review similar existing code in the framework
3. Read the complete [DEVELOPER_GUIDE.md](guides/DEVELOPER_GUIDE.md)

---

## 🎯 **Summary**

**To contribute successfully:**
1. ✅ Follow established patterns (Controller → Service → DAO → SP)
2. ✅ Use stored procedures ONLY (no raw SQL)
3. ✅ Extract business logic to services
4. ✅ Validate all inputs
5. ✅ Handle errors properly
6. ✅ Write tests
7. ✅ Update documentation
8. ✅ Follow API-Features.md checklist

**Thank you for contributing to PHPFrarm! 🚀**

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-28  
**Framework:** PHPFrarm Enterprise API Framework
