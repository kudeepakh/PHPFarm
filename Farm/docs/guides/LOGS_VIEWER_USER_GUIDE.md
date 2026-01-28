# 🔍 **System Logs Viewer – Quick Start Guide**

## 📖 **For Superadmin Users**

---

## 🚀 **Accessing the Logs Viewer**

1. **Login** to PHPFrarm admin dashboard
2. Click **"System Logs"** in the left sidebar menu (second item)
3. You'll see the logs viewer interface

---

## 🎯 **Understanding the Interface**

### **Top Section: Statistics Cards**
Shows real-time counts for all 4 log types:
- **Application** - Main application logs
- **Access** - HTTP access logs
- **Security** - Security events
- **Audit** - User action audits

### **Filter Section**
5 powerful filters to narrow down logs:

| Filter | Options | Purpose |
|--------|---------|---------|
| **Collection** | Application, Access, Security, Audit | Choose which logs to view |
| **Log Level** | All, ERROR, WARNING, INFO, DEBUG | Filter by severity |
| **Start Date** | Date/time picker | Logs from this time onward |
| **End Date** | Date/time picker | Logs up to this time |
| **Items Per Page** | 20, 50, 100 | How many logs to show |

**Search Bar:** Enter keywords to search within log messages

### **Logs Table**
Shows logs in descending order (newest first):

| Column | Description |
|--------|-------------|
| **Timestamp** | When the log was created |
| **Level** | Severity (color-coded badge) |
| **Message** | Brief log message (truncated) |
| **Correlation ID** | Unique request identifier |
| **Actions** | "View" button for details |

### **Pagination Controls**
- Shows: "Showing X to Y of Z results"
- **Previous/Next** buttons
- **Page indicator**: "Page X of Y"

---

## 🔍 **Common Use Cases**

### **1. Find All Errors from Today**

1. Select: **Collection** = "Application"
2. Select: **Log Level** = "ERROR"
3. Set: **Start Date** = Today 00:00:00
4. Click: **Search**

### **2. Investigate a Specific Issue**

1. Enter error message keywords in **Search Bar**
2. Click: **Search**
3. Click: **View** on matching log
4. Inspect **Context** section for details

### **3. Trace a Request Flow**

1. Copy the **Correlation ID** from any log
2. Go to browser console and run:
   ```javascript
   // In your API testing tool or browser
   fetch('/api/v1/logs/trace/{CORRELATION_ID}', {
     headers: { 'Authorization': 'Bearer YOUR_TOKEN' }
   })
   ```
3. This returns ALL logs (application, access, security, audit) for that request

### **4. Monitor Security Events**

1. Select: **Collection** = "Security"
2. Set: **Start Date** = Last 24 hours
3. Click: **Search**
4. Review for suspicious activities

### **5. Check Recent Warnings**

1. Select: **Log Level** = "WARNING"
2. Set: **Start Date** = Last 1 hour
3. Click: **Search**
4. Investigate repeated warnings

---

## 🎨 **Understanding Log Levels**

| Level | Badge Color | Icon | When Used | Action Required |
|-------|-------------|------|-----------|-----------------|
| **ERROR** | 🔴 Red | AlertCircle | System failures, crashes | **Immediate fix needed** |
| **WARNING** | 🟡 Yellow | AlertTriangle | Non-critical issues | **Review & monitor** |
| **INFO** | 🔵 Blue | Info | Normal operations | **No action needed** |
| **DEBUG** | ⚫ Gray | Bug | Development details | **For developers only** |

---

## 👁️ **Viewing Log Details**

Click **"View"** button on any log to see:

### **Basic Information**
- Full timestamp
- Log level badge
- Complete message

### **Trace IDs**
- **Correlation ID** - Tracks request across services
- **Transaction ID** - Groups related operations
- **Request ID** - Unique per HTTP request

### **Server Information**
- **HTTP Method & URI** (e.g., POST /api/v1/auth/login)
- **IP Address**
- **User Agent** (browser/device info)

### **Context Object (JSON)**
Full details about the log:
- Error messages
- Stack traces
- User IDs
- Request data
- Additional metadata

**Note:** Sensitive data (passwords, tokens) are automatically masked.

---

## 🔄 **Refresh & Clear**

### **Refresh Button** (Green)
- Reloads logs with current filters
- Updates statistics cards
- Use after fixing issues to verify

### **Clear Button** (Gray)
- Resets all filters to default
- Clears search query
- Useful for starting a new search

---

## 📊 **Performance Tips**

### **Speed Up Searches**
1. **Use specific date ranges** - Don't search all time
2. **Filter by log level** - Reduces results
3. **Use pagination** - Start with 20 items per page
4. **Be specific in search** - Use unique keywords

### **Example: Fast Search**
✅ **Good:**
```
Collection: Application
Level: ERROR
Start Date: 2026-01-28 10:00
End Date: 2026-01-28 12:00
Search: "token refresh"
```

❌ **Slow:**
```
Collection: Application
Level: (All)
Start Date: (empty)
End Date: (empty)
Search: "error"
```

---

## 🐛 **Common Issues & Solutions**

### **"No logs found"**
**Possible Causes:**
1. Filters too restrictive
2. Date range doesn't have logs
3. Search query has no matches

**Solution:** Click "Clear" and start with broader filters.

### **"Failed to retrieve logs"**
**Possible Causes:**
1. MongoDB connection issue
2. Session expired

**Solution:**
1. Click "Refresh"
2. If still failing, logout and login again
3. Contact system admin if persists

### **"Logs loading very slowly"**
**Possible Causes:**
1. Too many results (600K+ logs)
2. No filters applied

**Solution:**
1. Add date range filter
2. Select specific log level
3. Use smaller page size (20 instead of 100)

---

## 🔍 **Search Tips**

### **Exact Phrase**
Search for exact error messages:
```
"Authentication failed"
```

### **Keywords**
Search for any occurrence:
```
database connection
```

### **Partial Match**
Works on message field only:
```
user_id
```

### **Case Insensitive**
Search is not case-sensitive:
```
ERROR = error = Error
```

---

## 📱 **Mobile Access**

The logs viewer is mobile-responsive:
- Filters collapse on small screens
- Table scrolls horizontally
- Touch-friendly buttons
- Optimized for tablets

---

## 🚨 **When to Check Logs**

### **Daily Routine**
- Review ERROR logs from last 24 hours
- Check WARNING counts for trends
- Monitor SECURITY logs for threats

### **After Deployments**
- Verify no new ERROR logs
- Check for configuration issues
- Confirm services are logging

### **When Users Report Issues**
1. Note the time of issue
2. Check logs around that time
3. Look for ERROR or WARNING
4. Use Correlation ID to trace request

### **Security Monitoring**
- Daily check of SECURITY logs
- Weekly audit of suspicious IPs
- Monthly review of authentication failures

---

## 💡 **Pro Tips**

1. **Bookmark Common Searches**
   - Copy URL after applying filters
   - Bookmark for quick access

2. **Use Browser DevTools**
   - Open Network tab
   - See actual API requests
   - Debug filter issues

3. **Export Data (via API)**
   ```bash
   curl "http://localhost:3900/api/v1/logs/application?level=ERROR&start_date=2026-01-28" \
     -H "Authorization: Bearer YOUR_TOKEN"
   ```

4. **Correlate Multiple Logs**
   - Copy Correlation ID from one log
   - Use API endpoint `/api/v1/logs/trace/{CORRELATION_ID}`
   - See entire request journey

---

## 📞 **Need Help?**

### **For Technical Issues:**
- Contact: System Administrator
- Check: `docs/LOGS_VIEWER_COMPLETE_IMPLEMENTATION.md`

### **For Log Interpretation:**
- Review: Error context object
- Check: Stack traces
- Search: Documentation for error codes

---

## 🎓 **Quick Reference Card**

| Action | Steps |
|--------|-------|
| **View all errors** | Collection: Application → Level: ERROR → Search |
| **Last hour logs** | Start Date: 1 hour ago → Search |
| **Search message** | Enter text in Search Bar → Click Search |
| **See full detail** | Click "View" button on any log |
| **Trace request** | Copy Correlation ID → Use trace API |
| **Reset filters** | Click "Clear" button |
| **Refresh data** | Click green "Refresh" button |

---

**Last Updated:** January 28, 2026
**Version:** 1.0
