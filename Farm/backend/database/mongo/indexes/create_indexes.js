// MongoDB Indexes for PHPFrarm Logging

// Application Logs Collection
db.application_logs.createIndex({ "correlation_id": 1 });
db.application_logs.createIndex({ "transaction_id": 1 });
db.application_logs.createIndex({ "request_id": 1 });
db.application_logs.createIndex({ "timestamp": -1 });
db.application_logs.createIndex({ "level": 1 });
db.application_logs.createIndex({ "timestamp": -1, "level": 1 });

// Access Logs Collection
db.access_logs.createIndex({ "correlation_id": 1 });
db.access_logs.createIndex({ "transaction_id": 1 });
db.access_logs.createIndex({ "request_id": 1 });
db.access_logs.createIndex({ "timestamp": -1 });
db.access_logs.createIndex({ "server.ip": 1 });
db.access_logs.createIndex({ "server.method": 1 });

// Audit Logs Collection
db.audit_logs.createIndex({ "correlation_id": 1 });
db.audit_logs.createIndex({ "transaction_id": 1 });
db.audit_logs.createIndex({ "request_id": 1 });
db.audit_logs.createIndex({ "timestamp": -1 });
db.audit_logs.createIndex({ "context.user_id": 1 });
db.audit_logs.createIndex({ "context.action": 1 });

// Security Logs Collection
db.security_logs.createIndex({ "correlation_id": 1 });
db.security_logs.createIndex({ "transaction_id": 1 });
db.security_logs.createIndex({ "request_id": 1 });
db.security_logs.createIndex({ "timestamp": -1 });
db.security_logs.createIndex({ "server.ip": 1 });
db.security_logs.createIndex({ "level": 1 });

print("MongoDB indexes created successfully");
