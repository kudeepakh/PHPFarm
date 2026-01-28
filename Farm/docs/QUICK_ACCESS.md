# 🚀 PHPFrarm Quick Access Guide

**Print this or keep it open!** All essential links in one place.

---

## ⚡ **START HERE (First Time)**

| What | Where | Time |
|------|-------|------|
| 📚 **Complete Index** | [INDEX.md](INDEX.md) | 5 min |
| 🚀 **Onboarding Tutorial** | [DEVELOPER_ONBOARDING_GUIDE.md](DEVELOPER_ONBOARDING_GUIDE.md) | 2 hours |
| ⚡ **Quick Setup** | [QUICK_START.md](QUICK_START.md) | 10 min |
| 📝 **Daily Reference** | [QUICK_REFERENCE.md](QUICK_REFERENCE.md) | Bookmark! |

---

## 🔥 **Most Used (Daily)**

| Task | Document |
|------|----------|
| **Create new API** | [guides/DEVELOPER_GUIDE.md](guides/DEVELOPER_GUIDE.md) |
| **Check standards** | [CODE_STANDARDS_FINAL_SUMMARY.md](CODE_STANDARDS_FINAL_SUMMARY.md) |
| **View checklist** | [api/API-Features.md](api/API-Features.md) |
| **Test APIs** | [api/PHPFrarm.postman_collection.json](api/PHPFrarm.postman_collection.json) |
| **Troubleshoot** | [QUICK_REFERENCE.md](QUICK_REFERENCE.md) |

---

## 🏗️ **By Feature**

| Feature | Guide |
|---------|-------|
| **Authentication** | [guides/OTP_SECURITY_GUIDE.md](guides/OTP_SECURITY_GUIDE.md) |
| **Permissions** | [guides/ROLES_PERMISSIONS_GUIDE.md](guides/ROLES_PERMISSIONS_GUIDE.md) |
| **Caching** | [guides/CACHING_GUIDE.md](guides/CACHING_GUIDE.md) |
| **Rate Limiting** | [guides/TRAFFIC_MANAGEMENT_GUIDE.md](guides/TRAFFIC_MANAGEMENT_GUIDE.md) |
| **Testing** | [guides/TESTING_GUIDE.md](guides/TESTING_GUIDE.md) |
| **Tokens** | [guides/TOKEN_REFRESH_GUIDE.md](guides/TOKEN_REFRESH_GUIDE.md) |
| **Resilience** | [guides/CIRCUIT_BREAKER_GUIDE.md](guides/CIRCUIT_BREAKER_GUIDE.md) |

---

## 🎯 **Common Questions**

| Question | Answer |
|----------|--------|
| "How do I start?" | [DEVELOPER_ONBOARDING_GUIDE.md](DEVELOPER_ONBOARDING_GUIDE.md) |
| "Where's the architecture?" | [architecture/ARCHITECTURE.md](architecture/ARCHITECTURE.md) |
| "What are the standards?" | [CODE_STANDARDS_FINAL_SUMMARY.md](CODE_STANDARDS_FINAL_SUMMARY.md) |
| "How to contribute?" | [CONTRIBUTING.md](CONTRIBUTING.md) |
| "API endpoints?" | [API_COMPLETE_REFERENCE.md](API_COMPLETE_REFERENCE.md) |
| "16 modules?" | [api/Prompt.md](api/Prompt.md) |

---

## 🚨 **Emergency Fixes**

| Problem | Solution |
|---------|----------|
| **Services won't start** | Check Docker, ports, .env file |
| **Route 404** | Verify attribute routing syntax |
| **DB connection failed** | Check MySQL container, credentials |
| **Stored proc not found** | Re-run migration scripts |
| **Tests failing** | Clear cache, check dependencies |

See [QUICK_REFERENCE.md](QUICK_REFERENCE.md) for detailed troubleshooting.

---

## 📋 **Must Follow Rules**

```
✅ DO:
- Controller → Service → DAO → Stored Procedure
- Use Response::success() and Response::error()
- Validate ALL inputs
- Log with correlation IDs
- Follow API-Features.md checklist

❌ DON'T:
- Put business logic in controllers
- Write raw SQL queries
- Use getenv() directly
- Skip error handling
- Hardcode values
```

---

## 🎓 **Learning Order**

```
Day 1:
└─ QUICK_START.md → DEVELOPER_ONBOARDING_GUIDE.md

Day 2-3:
└─ ARCHITECTURE.md → BACKEND_ARCHITECTURE.md

Week 1:
└─ DEVELOPER_GUIDE.md → API-Features.md

Week 2+:
└─ Feature Guides → Advanced Topics
```

---

## 🔗 **Essential Links**

- **📚 Full Index:** [INDEX.md](INDEX.md)
- **📖 Main README:** [README.md](README.md)
- **🤝 Contributing:** [CONTRIBUTING.md](CONTRIBUTING.md)
- **✅ Standards:** [CODE_STANDARDS_FINAL_SUMMARY.md](CODE_STANDARDS_FINAL_SUMMARY.md)

---

## 📞 **Need Help?**

1. Check [INDEX.md](INDEX.md) for navigation
2. Search [QUICK_REFERENCE.md](QUICK_REFERENCE.md) for commands
3. Review similar code in `backend/modules/`
4. Read [DEVELOPER_GUIDE.md](guides/DEVELOPER_GUIDE.md)

---

**Keep this file open while developing!** 🚀

**Last Updated:** 2026-01-28
