# Session Management in TeamPass

## Table of Contents
1. [Overview](#overview)
2. [Session Architecture](#session-architecture)
3. [Components](#components)
4. [Session Lifecycle](#session-lifecycle)
5. [Configuration](#configuration)
6. [Security Mechanisms](#security-mechanisms)
7. [Troubleshooting](#troubleshooting)
8. [Developer Guide](#developer-guide)

---

## Overview

TeamPass implements a robust two-tier session management system that separates PHP session handling from application-level session control. This architecture provides:

- **Precise session expiration control** at the application level
- **Protection against premature PHP garbage collection** of session files
- **Server-side validation** for all user requests
- **Client-side countdown synchronization** for accurate time display
- **Manual session extension** for security and control

### Key Concepts

| Concept | Duration | Purpose |
|---------|----------|---------|
| **PHP Session Cookie** | 24 hours | Maintains PHP session data between requests |
| **Application Session** | Configurable (default: 60 min) | Controls actual user access duration |
| **Countdown Timer** | Real-time | Displays remaining session time to user |
| **Server Sync** | Every 5 minutes | Ensures countdown accuracy |

---

## Session Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         USER BROWSER                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Countdown Timer (functions.js)                          │  │
│  │  ┌────────────────────────────────────────────────────┐  │  │
│  │  │ • Displays time remaining: HH:MM:SS                │  │  │
│  │  │ • Syncs with server every 5 minutes                │  │  │
│  │  │ • Shows warning at 50 seconds remaining            │  │  │
│  │  └────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                              ▲                                   │
│                              │ AJAX: user_get_session_time      │
│                              │ Response: {timestamp}             │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Manual Extension Dialog (showExtendSession)             │  │
│  │  ┌────────────────────────────────────────────────────┐  │  │
│  │  │ • Appears at 50 seconds before expiration          │  │  │
│  │  │ • User inputs extension duration (minutes)         │  │  │
│  │  │ • Sends request to server                          │  │  │
│  │  └────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                              │                                   │
│                              │ AJAX: increase_session_time       │
│                              │ Params: {duration}                │
│                              ▼                                   │
└─────────────────────────────────────────────────────────────────┘
                               │
                               │ HTTPS
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                        TEAMPASS SERVER                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Request Handler (*.queries.php, *.php)                  │  │
│  │  ┌────────────────────────────────────────────────────┐  │  │
│  │  │  1. Receives request                               │  │  │
│  │  │  2. Calls PerformChecks::checkSession()            │  │  │
│  │  │  3. If valid → Process request                     │  │  │
│  │  │  4. If invalid → Redirect to error.php             │  │  │
│  │  └────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Session Validator (PerformChecks::checkSession)         │  │
│  │  ┌────────────────────────────────────────────────────┐  │  │
│  │  │  Validation Logic:                                 │  │  │
│  │  │  • Get user-session_duration from session          │  │  │
│  │  │  • Compare with current time()                     │  │  │
│  │  │  • If user-session_duration < time()               │  │  │
│  │  │    → Return FALSE (session expired)                │  │  │
│  │  │  • Verify user_id and user_key exist               │  │  │
│  │  │  • Check key_tempo in database matches             │  │  │
│  │  └────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Session Manager (SessionManager.php)                    │  │
│  │  ┌────────────────────────────────────────────────────┐  │  │
│  │  │  PHP Session Configuration:                        │  │  │
│  │  │  • Cookie lifetime: 86400 sec (24 hours)           │  │  │
│  │  │  • Encrypted session handler (DefusePHP)           │  │  │
│  │  │  • Secure, HttpOnly, SameSite=Lax                  │  │  │
│  │  │                                                     │  │  │
│  │  │  Application Session Storage:                      │  │  │
│  │  │  • user-session_duration (timestamp)               │  │  │
│  │  │  • user-id, user-key, user-login                   │  │  │
│  │  │  • Various user permissions and settings           │  │  │
│  │  └────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Database (teampass_users table)                         │  │
│  │  ┌────────────────────────────────────────────────────┐  │  │
│  │  │  Fields:                                           │  │  │
│  │  │  • key_tempo: Current session key                  │  │  │
│  │  │  • session_end: Session expiration timestamp       │  │  │
│  │  │  • timestamp: Last activity timestamp              │  │  │
│  │  └────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Components

### 1. PHP Session Layer

**File:** `vendor/teampassclasses/sessionmanager/src/SessionManager.php`

**Responsibilities:**
- Manages PHP session lifecycle (start, destroy)
- Configures session cookie parameters
- Encrypts session data using DefusePHP
- Provides session storage for application data

**Key Configuration:**
```php
session_set_cookie_params([
    'lifetime' => 86400,      // 24 hours
    'path' => '/',
    'secure' => $isSecure,    // HTTPS only when available
    'httponly' => true,       // Prevent JavaScript access
    'samesite' => 'Lax'       // CSRF protection
]);
```

**Why 24 hours?**
The PHP session cookie is set to 24 hours to prevent PHP's garbage collection from deleting session files prematurely. The actual session expiration is controlled at the application level through `user-session_duration`.

---

### 2. Application Session Control

**File:** `sources/identify.php` (login), `sources/main.queries.php` (extension)

**Responsibilities:**
- Sets initial session duration on login
- Calculates session expiration timestamp
- Handles manual session extension requests
- Updates database with session information

**Login Process:**
```php
// During authentication (identify.php:449-451)
$max_time = isset($SETTINGS['maximum_session_expiration_time'])
    ? (int) $SETTINGS['maximum_session_expiration_time']
    : 60; // Default 60 minutes

$session_time = min($dataReceived['duree_session'], $max_time);
$lifetime = time() + ($session_time * 60); // Convert to timestamp

// Store in session
$session->set('user-session_duration', (int) $lifetime);

// Store in database
DB::update(
    prefixTable('users'),
    ['session_end' => $lifetime],
    'id = %i',
    $user_id
);
```

**Session Extension:**
```php
// Manual extension (main.queries.php:3555-3578)
function increaseSessionDuration(int $duration): string
{
    $session = SessionManager::getSession();

    // Verify session is still valid
    if ($session->get('user-session_duration') > time()) {
        // Add duration (in seconds) to current expiration
        $newExpiration = (int) $session->get('user-session_duration') + $duration;
        $session->set('user-session_duration', $newExpiration);

        // Update database
        DB::update(
            prefixTable('users'),
            ['session_end' => $newExpiration],
            'id = %i',
            $session->get('user-id')
        );

        return '[{"new_value":"' . $newExpiration . '"}]';
    }

    return '[{"new_value":"expired"}]';
}
```

---

### 3. Session Validation

**File:** `vendor/teampassclasses/performchecks/src/PerformChecks.php`

**Responsibilities:**
- Validates session expiration on every AJAX request
- Checks user credentials and session key
- Enforces access control policies

**Validation Logic:**
```php
public function checkSession(): bool
{
    if (count($this->sessionVar) > 0) {
        $session = SessionManager::getSession();

        // CRITICAL: Check if session has expired
        if ($session->has('user-session_duration') &&
            $session->get('user-session_duration') !== null &&
            !empty($session->get('user-session_duration')) &&
            $session->get('user-session_duration') < time()) {
            return false; // Session expired
        }

        // Verify user ID exists
        if (isset($this->sessionVar['user_id']) === true &&
            (is_null($this->sessionVar['user_id']) === true ||
             empty($this->sessionVar['user_id']) === true)) {
            return false;
        }

        // Verify session key exists
        if (isset($this->sessionVar['user_key']) === true &&
            (is_null($this->sessionVar['user_key']) === true ||
             empty($this->sessionVar['user_key']) === true)) {
            return false;
        }
    }

    return true;
}
```

**Usage in Request Handlers:**
```php
// Example from sources/items.queries.php
$checkUserAccess = new PerformChecks(
    ['type' => $post_type],
    [
        'user_id' => $session->get('user-id'),
        'user_key' => $session->get('key'),
    ]
);

if ($checkUserAccess->checkSession() === false) {
    // Session expired or invalid
    $session->set('system-error_code', ERR_SESS_EXPIRED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
```

---

### 4. Client-Side Countdown

**File:** `includes/js/functions.js`

**Responsibilities:**
- Displays remaining session time to user
- Synchronizes with server every 5 minutes
- Triggers extension dialog at 50 seconds
- Redirects to login when session expires

**Countdown Function:**
```javascript
function countdown() {
    // Get session expiration timestamp from hidden field
    let theDay = $('#temps_restant').val();
    let today = new Date();
    let second = Math.floor(theDay - today.getTime() / 1000);

    // Calculate hours, minutes, seconds
    let minute = Math.floor(second / 60);
    let hour = Math.floor(minute / 60);
    let CHour = hour % 24;
    let CMinute = minute % 60;
    let CSecond = second % 60;

    // Format display: HH:MM:SS
    let DayTill =
        (CHour < 10 ? '0' + CHour : CHour) + ':' +
        (CMinute < 10 ? '0' + CMinute : CMinute) + ':' +
        (CSecond < 10 ? '0' + CSecond : CSecond);

    // Show extension dialog at 50 seconds
    if (DayTill === '00:00:50') {
        showExtendSession();
        $('#countdown').css('color', 'red');
    }

    // Redirect when session expires
    if (DayTill <= '00:00:00') {
        $(location).attr('href', 'index.php?session=expired');
    }

    // Update display
    $('#countdown').html('<i class="far fa-clock mr-1"></i>' + DayTill);

    // Repeat every second
    $(this).delay(1000).queue(function() {
        countdown();
        $(this).dequeue();
    });
}
```

**Server Synchronization:**
```javascript
// Sync every 5 minutes (300000 ms)
let currentTime = new Date().getTime();
if (currentTime - lastSessionSync > sessionSyncInterval) {
    syncSessionTimeWithServer();
    lastSessionSync = currentTime;
}

function syncSessionTimeWithServer() {
    $.ajax({
        type: 'POST',
        url: 'sources/main.queries.php',
        data: {
            type: 'user_get_session_time',
            type_category: 'action_user',
            data: prepareExchangedData(
                JSON.stringify({user_id: userId}),
                'encode',
                sessionKey
            ),
            key: sessionKey
        },
        success: function(serverData) {
            var decodedData = prepareExchangedData(serverData, 'decode', sessionKey);
            if (decodedData && decodedData.timestamp > 0) {
                // Update countdown with server value
                $('#temps_restant').val(decodedData.timestamp);
            }
        }
    });
}
```

---

## Session Lifecycle

### 1. User Login

```
┌─────────────────────────────────────────────────────────────┐
│ 1. User submits credentials                                │
│    └─ includes/core/login.php → sources/identify.php       │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. Authenticate user                                        │
│    • Verify password (LDAP/database)                        │
│    • Check 2FA if enabled                                   │
│    • Verify account is not locked/disabled                  │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Create new session                                       │
│    • Generate unique session key (30 chars)                 │
│    • Calculate expiration: time() + (duration_min * 60)     │
│    • Respect maximum_session_expiration_time setting        │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Store session data                                       │
│    PHP Session:                                             │
│    • user-id, user-login, user-key                          │
│    • user-session_duration (timestamp)                      │
│    • user-admin, user-manager, etc.                         │
│                                                              │
│    Database (teampass_users):                               │
│    • key_tempo = session key                                │
│    • session_end = expiration timestamp                     │
│    • timestamp = current time                               │
│    • last_connexion = current datetime                      │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. Client initialization                                    │
│    • Set #temps_restant = expiration timestamp              │
│    • Start countdown() timer                                │
│    • Initialize store.js with user data                     │
└─────────────────────────────────────────────────────────────┘
```

### 2. Active Session

```
┌─────────────────────────────────────────────────────────────┐
│ User Activity (clicks, page loads, AJAX requests)           │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ Every Request:                                              │
│  1. Load sources/core.php (for pages)                       │
│     OR                                                       │
│     PerformChecks::checkSession() (for AJAX)                │
│                                                              │
│  2. Validate session:                                       │
│     • user-session_duration < time() ?                      │
│     • user_id exists and valid?                             │
│     • user_key matches database key_tempo?                  │
│                                                              │
│  3. If valid → Process request                              │
│     If invalid → Redirect to error.php                      │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ Every 1 Second:                                             │
│  • countdown() updates display                              │
│  • Shows remaining time: HH:MM:SS                           │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ Every 5 Minutes:                                            │
│  • syncSessionTimeWithServer()                              │
│  • GET user_get_session_time from server                   │
│  • Update #temps_restant with server value                 │
│  • Ensures accuracy across browser tabs                    │
└─────────────────────────────────────────────────────────────┘
```

### 3. Session Extension

```
┌─────────────────────────────────────────────────────────────┐
│ At 50 seconds before expiration                             │
│  • countdown() detects DayTill === '00:00:50'               │
│  • Calls showExtendSession()                                │
│  • Displays modal dialog                                    │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ User Input                                                  │
│  • User enters extension duration (minutes)                 │
│  • Clicks "Extend" button                                   │
│                                                              │
│  OR                                                          │
│                                                              │
│  • User ignores dialog → session expires                    │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ If Extended:                                                │
│  1. AJAX POST to increase_session_time                      │
│     Params: duration (in seconds)                           │
│                                                              │
│  2. Server validates current session                        │
│     IF user-session_duration > time():                      │
│       • Add duration to user-session_duration               │
│       • Update session variable                             │
│       • Update database session_end                         │
│       • Return new expiration timestamp                     │
│     ELSE:                                                    │
│       • Return "expired"                                    │
│                                                              │
│  3. Client receives response                                │
│     • Update #temps_restant with new value                  │
│     • Countdown continues with new time                     │
│     • All tabs sync within 5 minutes                        │
└─────────────────────────────────────────────────────────────┘
```

### 4. Session Expiration

```
┌─────────────────────────────────────────────────────────────┐
│ Countdown reaches 00:00:00                                  │
│  • countdown() detects DayTill <= '00:00:00'                │
│  • Redirects: window.location = 'index.php?session=expired' │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ Any AJAX Request After Expiration                           │
│  • PerformChecks::checkSession() called                     │
│  • Checks: user-session_duration < time()                   │
│  • Returns false                                            │
│  • Request handler includes error.php                       │
│  • User sees "Session Expired" message                      │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ Clean Up                                                    │
│  • error.php invalidates session                            │
│  • Database: key_tempo = '', session_end = ''               │
│  • Log disconnection event (if enabled)                     │
│  • PHP session destroyed                                    │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ User redirected to login page                               │
└─────────────────────────────────────────────────────────────┘
```

---

## Configuration

### Application Settings

Session duration is configurable via the administration interface or database.

**Setting:** `maximum_session_expiration_time`
**Location:** Administration > Settings > Maximum session expiration time
**Database:** `teampass_misc` table, type='admin', intitule='maximum_session_expiration_time'
**Default:** 60 minutes
**Range:** 1 - 1440 minutes (24 hours)

**Example Configuration:**
```sql
-- Set maximum session to 120 minutes (2 hours)
UPDATE teampass_misc
SET valeur = '120'
WHERE type = 'admin'
  AND intitule = 'maximum_session_expiration_time';
```

### User-Specific Duration

During login, users can select their preferred session duration (if allowed by configuration).

**Login Form Field:** `session_duration`
**Constraint:** `min(user_choice, maximum_session_expiration_time)`

**Example:**
- User selects: 240 minutes
- Admin maximum: 120 minutes
- **Result:** Session duration = 120 minutes (respects admin limit)

### PHP Configuration

**Recommended php.ini settings:**
```ini
session.gc_maxlifetime = 86400      ; 24 hours (matches cookie)
session.cookie_lifetime = 0         ; Browser session (overridden by app)
session.use_strict_mode = 1         ; Reject uninitialized session IDs
session.cookie_httponly = 1         ; Prevent JavaScript access
session.cookie_secure = 1           ; HTTPS only (if using HTTPS)
session.cookie_samesite = "Lax"     ; CSRF protection
```

**Note:** TeamPass overrides many of these via `session_set_cookie_params()` in SessionManager.

---

## Security Mechanisms

### 1. Session Key Validation

Every request validates that the session key in PHP session matches the `key_tempo` in the database.

**Purpose:** Prevents session fixation and hijacking
**Implementation:** PerformChecks::userAccessPage() → line 189

```php
if (empty($data['key_tempo']) === true ||
    $data['key_tempo'] !== $this->sessionVar['user_key']) {
    return false; // Invalid session
}
```

### 2. Session Migration

On successful authentication, the session ID is regenerated to prevent fixation attacks.

**Implementation:** identify.php → line 457
```php
$session->migrate(); // Regenerate session ID
$session->set('key', generateQuickPassword(30, false)); // New session key
```

### 3. Encrypted Session Storage

Session data is encrypted at rest using DefusePHP encryption library.

**Implementation:** SessionManager.php → line 44-50
```php
$key = Key::loadFromAsciiSafeString(file_get_contents(SECUREPATH . "/" . SECUREFILE));
$handler = new EncryptedSessionProxy(new \SessionHandler(), $key);
self::$session = new Session(new NativeSessionStorage([], $handler));
```

### 4. Strict Expiration Enforcement

Server-side validation on **every** request ensures expired sessions cannot be used.

**Files:**
- `sources/core.php` (page loads) → line 253-254
- `PerformChecks::checkSession()` (AJAX) → line 65-70

**Logic:**
```php
if ($session->get('user-session_duration') < time()) {
    // Expired - disconnect user
    DB::update(prefixTable('users'), ['key_tempo' => ''], 'id=%i', $user_id);
    $session->invalidate();
    redirect('index.php?session=expired');
}
```

### 5. CSRF Protection

Session cookies use `SameSite=Lax` to prevent cross-site request forgery.

**Configuration:** SessionManager.php → line 66
```php
'samesite' => 'Lax' // Blocks cross-site POST requests
```

### 6. HttpOnly Cookies

Session cookies are marked HttpOnly to prevent JavaScript access.

**Configuration:** SessionManager.php → line 65
```php
'httponly' => true // Prevents XSS from stealing session
```

---

## Troubleshooting

### Problem: Users Disconnected Before Session Expires

**Symptoms:**
- Countdown shows time remaining (e.g., 10:00)
- User clicks feature and sees "Session Expired" error
- Happens randomly, not at countdown zero

**Root Cause:**
Prior to version 3.1.5.1, AJAX requests did not validate `user-session_duration < time()`. The PHP session cookie (24h) remained valid while the application session had expired.

**Solution:**
Upgrade to TeamPass 3.1.5.1 or later. This version includes:
- Server-side expiration check in `PerformChecks::checkSession()`
- All AJAX requests now validate session expiration
- Countdown synchronizes with server every 5 minutes

**Verification:**
```bash
# Check version
grep "define('TP_VERSION'" includes/config/include.php

# Should be 3.1.5.1 or higher
```

---

### Problem: Countdown Not Synchronized Across Tabs

**Symptoms:**
- Open application in two browser tabs
- Extend session in tab 1
- Tab 2 countdown doesn't update
- Tab 2 shows "Session Expired" even though session was extended

**Cause:**
Client-side countdown relies on local `#temps_restant` value. Without synchronization, each tab maintains its own countdown.

**Solution:**
TeamPass 3.1.5.1+ includes automatic server synchronization every 5 minutes.

**Manual Trigger:**
If you need immediate synchronization, reload the page or wait up to 5 minutes.

**Verification:**
Open browser console and check for:
```
Session sync called at: [timestamp]
```

---

### Problem: Session Expires Faster Than Configured

**Symptoms:**
- Admin sets maximum session to 120 minutes
- Users report disconnection after 60 minutes
- Database `session_end` shows correct timestamp

**Possible Causes:**

1. **PHP garbage collection**
   ```bash
   # Check PHP configuration
   php -i | grep session.gc_maxlifetime

   # If less than your session duration, increase it
   # php.ini:
   session.gc_maxlifetime = 7200  ; 2 hours
   ```

2. **Load balancer timeout**
   - Check your reverse proxy/load balancer session timeout
   - Nginx: `proxy_read_timeout`
   - Apache: `Timeout` directive

3. **User selected shorter duration**
   - Users can select their own duration at login (if enabled)
   - Check login logs for actual duration selected

**Debugging:**
```sql
-- Check user's session_end
SELECT
    id,
    login,
    session_end,
    FROM_UNIXTIME(session_end) as expiration_time,
    (session_end - UNIX_TIMESTAMP()) as seconds_remaining
FROM teampass_users
WHERE id = <user_id>;
```

---

### Problem: "ERROR SESSION EXPIRED" After Each Request

**Symptoms:**
- User logs in successfully
- Immediately disconnected on next action
- Error code: ERR_SESS_EXPIRED (1002)

**Possible Causes:**

1. **System clock mismatch**
   ```bash
   # Check server time
   date

   # Check PHP time
   php -r "echo date('Y-m-d H:i:s');"

   # Should match within a few seconds
   ```

2. **Database timezone issues**
   ```sql
   -- Check MySQL timezone
   SELECT NOW(), UNIX_TIMESTAMP();

   -- Compare with PHP time()
   SELECT UNIX_TIMESTAMP() - <php_time_value>;
   -- Should be 0 or very small
   ```

3. **Session storage failure**
   ```bash
   # Check session directory permissions
   ls -ld $(php -r "echo ini_get('session.save_path');")

   # Should be writable by web server user
   sudo chmod 1733 /var/lib/php/sessions  # Example for Debian/Ubuntu
   ```

**Solution:**
Ensure server time, PHP time, and database time are synchronized. Use NTP for time synchronization.

---

### Problem: Extension Dialog Doesn't Appear

**Symptoms:**
- Countdown reaches 00:00:50
- No extension dialog shown
- User disconnected at 00:00:00

**Causes:**

1. **JavaScript error**
   - Open browser console (F12)
   - Check for errors in functions.js or load.js.php

2. **Modal blocked**
   - Check if `#warningModal` exists in DOM
   - Verify showExtendSession() function is defined

3. **Countdown not running**
   - Check if `#temps_restant` has valid value
   - Verify countdown() is called on page load

**Debugging:**
```javascript
// Browser console
console.log('temps_restant:', $('#temps_restant').val());
console.log('countdown function:', typeof countdown);
console.log('showExtendSession:', typeof showExtendSession);

// Manual trigger
showExtendSession();
```

---

## Developer Guide

### Adding Session Validation to New Endpoints

When creating new AJAX endpoints or query handlers:

1. **Include session check**
   ```php
   <?php
   require_once __DIR__.'/../sources/main.functions.php';

   use TeampassClasses\SessionManager\SessionManager;
   use TeampassClasses\PerformChecks\PerformChecks;

   $session = SessionManager::getSession();

   $checkUserAccess = new PerformChecks(
       ['type' => filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS)],
       [
           'user_id' => $session->get('user-id'),
           'user_key' => $session->get('key'),
       ]
   );

   if ($checkUserAccess->checkSession() === false) {
       $session->set('system-error_code', ERR_SESS_EXPIRED);
       include $SETTINGS['cpassman_dir'] . '/error.php';
       exit;
   }
   ```

2. **Validate user access**
   ```php
   if ($checkUserAccess->userAccessPage('page_name') === false) {
       $session->set('system-error_code', ERR_NOT_ALLOWED);
       include $SETTINGS['cpassman_dir'] . '/error.php';
       exit;
   }
   ```

### Retrieving Session Information

**PHP:**
```php
use TeampassClasses\SessionManager\SessionManager;

$session = SessionManager::getSession();

// Get user ID
$userId = $session->get('user-id');

// Get session expiration
$expiration = $session->get('user-session_duration');
$remainingSeconds = $expiration - time();

// Check if admin
$isAdmin = $session->get('user-admin') === 1;
```

**JavaScript:**
```javascript
// Using store.js
var userId = store.get('teampassUser').user_id;
var userKey = store.get('teampassUser').key;
var isAdmin = store.get('teampassUser').user_admin === 1;

// Get remaining time
var expirationTimestamp = $('#temps_restant').val();
var remainingSeconds = expirationTimestamp - Math.floor(Date.now() / 1000);
```

### Extending Session Programmatically

**Server-side:**
```php
use TeampassClasses\SessionManager\SessionManager;

$session = SessionManager::getSession();

// Add 30 minutes (1800 seconds)
$newExpiration = (int) $session->get('user-session_duration') + 1800;
$session->set('user-session_duration', $newExpiration);

// Update database
DB::update(
    prefixTable('users'),
    ['session_end' => $newExpiration],
    'id = %i',
    $session->get('user-id')
);
```

**Client-side:**
```javascript
// Send extension request
$.post(
    'sources/main.queries.php',
    {
        type: 'increase_session_time',
        type_category: 'action_user',
        duration: 1800, // 30 minutes in seconds
        key: store.get('teampassUser').key
    },
    function(data) {
        var result = JSON.parse(data);
        if (result[0].new_value !== 'expired') {
            // Update countdown
            $('#temps_restant').val(result[0].new_value);
        }
    }
);
```

### Monitoring Sessions

**Active Sessions Query:**
```sql
SELECT
    u.id,
    u.login,
    u.last_connexion,
    FROM_UNIXTIME(u.session_end) as session_expires,
    CASE
        WHEN u.session_end > UNIX_TIMESTAMP() THEN 'Active'
        ELSE 'Expired'
    END as status,
    TIMESTAMPDIFF(MINUTE, NOW(), FROM_UNIXTIME(u.session_end)) as minutes_remaining
FROM teampass_users u
WHERE u.key_tempo != ''
  AND u.session_end > 0
ORDER BY u.session_end DESC;
```

**Session Activity Log:**
```sql
-- Requires log_connections setting enabled
SELECT
    l.date,
    l.label,
    l.qui,
    u.login
FROM teampass_log_system l
JOIN teampass_users u ON l.qui = u.id
WHERE l.type = 'user_connection'
  AND l.action IN ('connection', 'disconnect')
ORDER BY l.date DESC
LIMIT 50;
```

---

## Best Practices

### For Administrators

1. **Set appropriate session durations**
   - Consider your organization's security policy
   - Balance security (shorter) vs. usability (longer)
   - Typical range: 30-120 minutes

2. **Enable connection logging**
   - Administration > Settings > Log connections
   - Helps track session abuse or unauthorized access

3. **Monitor session activity**
   - Regularly review active sessions
   - Investigate sessions with unusual durations

4. **Educate users**
   - Explain the extension dialog
   - Encourage manual extension when working on long tasks
   - Advise against leaving sessions open when away

### For Developers

1. **Always validate sessions**
   - Use PerformChecks::checkSession() in all AJAX handlers
   - Never trust client-side session state

2. **Handle expiration gracefully**
   - Detect ERR_SESS_EXPIRED responses
   - Redirect to login with appropriate message
   - Preserve user work when possible (localStorage)

3. **Test edge cases**
   - Session expiration during long-running operations
   - Multiple browser tabs/windows
   - Clock skew between client and server

4. **Use consistent time sources**
   - Always use time() for server-side timestamps
   - Use Date.now() for client-side timestamps
   - Never trust client-provided timestamps

---

## Conclusion

TeamPass's session management system provides a robust, secure, and user-friendly approach to controlling access duration. By separating PHP session mechanics from application-level session control, it achieves:

- **Precision:** Sessions expire exactly when intended
- **Security:** Multiple layers of validation prevent unauthorized access
- **Usability:** Clear countdown and manual extension give users control
- **Reliability:** Server synchronization prevents premature disconnections

For questions or issues not covered in this document, please refer to:
- [TeamPass Documentation](https://teampass.readthedocs.io/)
- [GitHub Issues](https://github.com/nilsteampassnet/TeamPass/issues)
- [Reddit Community](https://www.reddit.com/r/TeamPass/)
