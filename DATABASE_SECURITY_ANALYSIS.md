# Database Security & Compatibility Analysis

## 🔍 Current State Assessment

### Database Connection Analysis
**File:** `app/Database/Db.php`

---

## ⚠️ **CRITICAL SECURITY ISSUES**

### 1. **SQL Injection Vulnerabilities** 🚨

**Issue:** The ActiveRecord/QueryBuilder system uses **string concatenation** instead of prepared statements!

**Current Implementation:**
```php
// In QueryBuilder::buildWhere()
if (is_string($value)) {
    $escapedValue = "'" . $this->db->escape($value) . "'";
    $conditions[] = "`{$column}` {$operator} {$escapedValue}";
}

// In Db::escape()
public function escape($value): string {
    $quoted = $this->conn->quote((string)$value);
    return substr($quoted, 1, -1); // Strip quotes
}
```

**Risk Level:** HIGH
- While `PDO::quote()` is safer than `mysqli_real_escape_string()`, it's still inferior to prepared statements
- Second-order SQL injection possible
- No support for parameterized queries
- All ORM methods (where, sum, avg, join, etc.) vulnerable

**Affected Methods:**
- All `where*()` methods
- `sum()`, `avg()`,  `max()`, `min()`
- `join()`, `leftJoin()`, etc.
- `Model::create()`, `Model::update()`

---

### 2. **No Prepared Statements** 🚨

**Issue:** The system **NEVER** uses PDO prepared statements with parameter binding!

**Current Code:**
```php
// Db.php line 84
public function query(string $sql) {
    // Executes raw SQL string - NO parameter binding!
    $stmt = $this->conn->query($sql);
}

// Model.php line 554 - Create method
$sql = "INSERT INTO `{$model->table}` (`" . implode('`, `', $fields) . "`) 
        VALUES (" . implode(', ', $values) . ")";
$db->query($sql); // Raw SQL execution!
```

**What's Missing:**
```php
// ❌ Current: String concatenation
$sql = "SELECT * FROM users WHERE id = '{$id}'";
$db->query($sql);

// ✅ Should be: Prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
```

**Risk Level:** CRITICAL
- Every single database query is vulnerable
- No protection against malicious input
- Escaping alone is not sufficient for modern security standards

---

### 3. **MySQL-Only Support** ⚠️

**Issue:** Hardcoded to MySQL/MariaDB only!

**Evidence:**
```php
// Line 53-58
$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',  // ❌ Hardcoded to MySQL!
    $this->host,
    $this->database,
    $this->charset
);
```

**Limitations:**
- ❌ No PostgreSQL support
- ❌ No SQLite support
- ❌ No Microsoft SQL Server support
- ❌ No Oracle support
- ❌ Backtick quoting (`` `column` ``) is MySQL-specific

**Result:** Framework can **ONLY** work with MySQL/MariaDB databases.

---

### 4. **Connection Security Issues** ⚠️

**Issues Found:**

**a) No SSL/TLS Support:**
```php
// Missing SSL options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
    // ❌ Missing: PDO::MYSQL_ATTR_SSL_CA
    // ❌ Missing: PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT
];
```

**b) Password in Plain Text:**
```php
// Line 35 - Stored in plain text in $_ENV
$this->password = $_ENV['DATABASE_PASSWORD'] ?? '';
```
No encryption, no secrets manager integration.

**c) No Connection Pooling:**
```php
// Line 64 - Creates new connection every time
PDO::ATTR_PERSISTENT => false
```
Performance issue + security concern (connection exhaustion attacks).

---

### 5. **Query Caching Security** ⚠️

**Issue:** Cache keys use SHA-256 but no access control

```php
// Line 264
$hash = hash('sha256', $normalizedSql);
```

**Concerns:**
- APCu cache is shared across all PHP processes
- No user/session isolation
- Potential information disclosure between users
- No cache encryption

**Example Attack:**
```php
// User A queries: SELECT * FROM users WHERE id = 1
// Cached as: php_palm_db:{hash}:users:v1

// User B can potentially access same cache
// if they construct identical query
```

---

## 📊 Security Scorecard

| Security Aspect | Rating | Issue |
|----------------|--------|-------|
| SQL Injection Protection | ❌ **F** | No prepared statements, only escaping |
| Input Validation | ⚠️ **D** | Relies on `PDO::quote()` only |
| Parameterized Queries | ❌ **F** | Not implemented anywhere |
| Connection Security | ⚠️ **D** | No SSL, plain text passwords |
| Access Control | ⚠️ **C** | Basic, no multi-tenancy support |
| Query Caching Security | ⚠️ **D** | No encryption, no isolation |
| Database Compatibility | ❌ **F** | MySQL only |
| Error Handling | ✅ **B** | Good, uses PDO exceptions |
| **Overall Score** | ❌ **D-** | **Not production-ready** |

---

## ✅ What's Good

1. **PDO-Based:** Using PDO instead of mysqli is a good start
2. **Error Handling:** Proper exception handling
3. **Emulate Prepares = false:** Enforces real prepared statements (but not used!)
4. **APCu Caching:** Smart query caching system
5. **Auto-invalidation:** Cache invalidated on writes

---

## 🛠️ **REQUIRED FIXES**

### Priority 1: Security (URGENT)

#### Fix 1: Implement Prepared Statements Throughout

**Create New Method:**
```php
// In Db.php
public function prepare(string $sql, array $params = []): DbResult {
    $this->connect();
    
    try {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->columnCount() > 0) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return new DbResult($rows);
        }
        
        return new DbResult([]);
    } catch (PDOException $e) {
        $this->lastError = $e->getMessage();
        throw $e;
    }
}
```

**Update QueryBuilder:**
```php
// Use ? placeholders instead of values
protected array $bindings = [];

public function where(string $column, $operator = null, $value = null): self {
    $this->where[] = [
        'column' => $column,
        'operator' => $operator ?? '=',
        'placeholder' => '?',  // Use placeholder
        'logic' => 'AND'
    ];
    $this->bindings[] = $value;  // Store value separately
    return $this;
}

protected function buildSql(): array {
    // Return both SQL and bindings
    return [
        'sql' => $sql,
        'bindings' => $this->bindings
    ];
}
```

#### Fix 2: Add Multi-Database Support

**Create Database Adapter Interface:**
```php
interface DatabaseAdapter {
    public function getDriver(): string;
    public function quote(string $identifier): string;
    public function buildDSN(array $config): string;
    public function getDataTypes(): array;
}

class MySQLAdapter implements DatabaseAdapter {
    public function quote(string $identifier): string {
        return "`{$identifier}`";
    }
}

class PostgreSQLAdapter implements DatabaseAdapter {
    public function quote(string $identifier): string {
        return "\"{$identifier}\"";
    }
}
```

#### Fix 3: Add SSL Support

```php
// In connect() method
if (isset($_ENV['DATABASE_SSL_CA'])) {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $_ENV['DATABASE_SSL_CA'];
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
}
```

---

### Priority 2: Database Compatibility

#### Support PostgreSQL
```php
'pgsql:host=%s;port=%s;dbname=%s'
```

#### Support SQLite
```php
'sqlite:%s'  // File path
```

#### Support SQL Server
```php
'sqlsrv:Server=%s;Database=%s'
```

#### Abstract Identifier Quoting
```php
// Instead of backticks everywhere
$this->adapter->quote('column_name')
```

---

### Priority 3: Connection Security

#### Add Connection Pooling
```php
PDO::ATTR_PERSISTENT => (bool)($_ENV['DB_PERSISTENT'] ?? true)
```

#### Implement Secrets Manager Integration
```php
// Support AWS Secrets Manager, Azure Key Vault, etc.
$password = SecretsManager::get('database_password');
```

---

## 📋 Implementation Checklist

### Phase 1: Security Fixes (URGENT - 2-3 days)
- [ ] Implement prepared statements in Db class
- [ ] Update QueryBuilder to use parameter binding
- [ ] Update Model create/update/delete to use prepared statements
- [ ] Add SQL injection tests
- [ ] Security audit all query methods

### Phase 2: Database Abstraction (1 week)
- [ ] Create DatabaseAdapter interface
- [ ] Implement MySQLAdapter
- [ ] Implement PostgreSQLAdapter
- [ ] Implement SQLiteAdapter
- [ ] Implement SQLServerAdapter
- [ ] Abstract identifier quoting
- [ ] Abstract data types

### Phase 3: Connection  Security (3-4 days)
- [ ] Add SSL/TLS support
- [ ] Implement connection pooling
- [ ] Add secrets manager integration
- [ ] Encrypted credentials storage
- [ ] Connection timeout handling

### Phase 4: Query Cache Security (2-3 days)
- [ ] Add cache encryption
- [ ] Implement user/tenant isolation
- [ ] Add cache access control
- [ ] Security audit cache layer

---

## 🎯 Recommended Architecture

```php
// New structure
Database/
├── Db.php (main class)
├── Adapters/
│   ├── DatabaseAdapter.php (interface)
│   ├── MySQLAdapter.php
│   ├── PostgreSQLAdapter.php
│   ├── SQLiteAdapter.php
│   └── SQLServerAdapter.php
├── QueryBuilder.php (move from Core)
├── Connection/
│   ├── ConnectionPool.php
│   ├── ConnectionConfig.php
│   └── SSLConfig.php
└── Security/
    ├── PreparedStatementBuilder.php
    ├── ParameterBinder.php
    └── QuerySanitizer.php
```

---

## 🚀 Quick Wins (Can implement today)

1. **Add `prepare()` method** to Db class
2. **Environment variable for DB driver**:
   ```php
   $driver = $_ENV['DATABASE_DRIVER'] ?? 'mysql';
   ```
3. **SSL configuration**:
   ```php
   if ($_ENV['DATABASE_SSL_ENABLED'] === 'true') {
       // Enable SSL
   }
   ```

---

## 💡 Comparison with Laravel

| Feature | PHP Palm | Laravel Eloquent |
|---------|----------|------------------|
| Prepared Statements | ❌ No | ✅ Yes (always) |
| Multi-DB Support | ❌ MySQL only | ✅ MySQL, PostgreSQL, SQLite, SQL Server |
| Query Builder | ✅ Yes (new!) | ✅ Yes |
| Connection Pooling | ❌ No | ✅ Yes |
| SSL Support | ❌ No | ✅ Yes |
| Read/Write Splitting | ❌ No | ✅ Yes |
| Database Migrations | ❌ No | ✅ Yes |

---

## 📝 Recommendations

### **Immediate Action Required:**

1. **STOP using string concatenation** for SQL queries
2. **START using prepared statements** for ALL database operations
3. **NEVER trust user input** - always use parameter binding

### **Short Term (This Month):**

1. Implement prepared statements throughout
2. Add PostgreSQL support
3. Add SSL/TLS configuration
4. Security audit all database code

### **Long Term (Next 3 Months):**

1. Full multi-database abstraction
2. Connection pooling
3. Read/write splitting
4. Database migrations system
5. Query logging and monitoring

---

## ⚡ Conclusion

**Current Status:** 🔴 **NOT PRODUCTION-READY**

**Critical Issues:**
- No prepared statements = immediate SQL injection risk
- MySQL-only = limited deployment options
- No SSL = insecure connections

**Path Forward:**
1. Implement prepared statements (Priority 1)
2. Add database adapter layer (Priority 2)
3. Enhance connection security (Priority 3)

**Estimated Effort:** 2-3 weeks for full implementation

**Would you like me to start implementing these security fixes immediately?**
