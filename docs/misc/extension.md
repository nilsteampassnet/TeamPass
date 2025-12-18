# Teampass Browser Extension

Official extension for Chrome, Firefox, and Edge browsers enabling seamless integration with your Teampass server.

⚠️ **Note**: Currently in testing phase.

---

## 📋 Features

### ✅ Available

The Teampass extension currently offers the following features:

#### Password Management
- 🔍 **Automatic search**: Current URL detection and matching credentials search
- 📋 **One-click copy**: Login, password, email, or TOTP code
- ✨ **Auto-fill**: Login form completion
- 👁️ **Details view**: Complete information display with password show/hide
- 🎲 **Integrated password generator**: Generate passwords based upon criteria

#### Complete CRUD Operations
- ➕ **Create**: Add new credentials directly from the extension
- ✏️ **Edit**: Modify all fields of an existing item
- 🗑️ **Delete**: Deletion with confirmation
- 🔄 **Real-time synchronization**: Always up-to-date data from your Teampass server

#### Advanced Security
- 🔐 **TOTP/MFA**: Generation and copying of temporary codes (OTP)
- 🔑 **JWT Authentication**: Secure tokens with automatic renewal
- 🔄 **Automatic re-authentication**: After PC wake or token expiration
- 🔒 **End-to-end encryption**: Maintains native Teampass security
- ✅ **HTTPS required**: Secure communications only

#### Folder Management
- 📁 **Hierarchical structure**: Complete folder tree
- 🔎 **Real-time search**: Folder filtering during creation/modification
- 🔐 **Permission management**: Respects user access rights

#### User Interface
- 🌙 **Dark mode**: Dark theme for visual comfort
- 🎨 **Modern interface**: Clean and minimalist design with Font Awesome
- 🔔 **Toast notifications**: Non-intrusive visual feedback
- 📊 **Counter badge**: Number of available credentials for current site

#### License Verification
- 🔍 **Automatic validation**: Verification with Teampass license server
- ⏳ **Grace period**: In case of license server unavailability
- 🎯 **Status indicator**: Colored badge (🟢 valid, 🔴 invalid, 🟠 not verified)

### 🚧 Coming Soon

The following features are planned for upcoming releases:

- 🌍 **Multilingual support** (French, German, Spanish)
- ⌨️ **Customizable keyboard shortcuts**
- ⭐ **Favorites** for quick access
- 🔍 **Global search** (not just by URL)
- 🔧 **Custom fields** (support for Teampass custom fields)

---

## 📦 Installation from ZIP File

### Prerequisites

- **Teampass 3.1.5.15 minimum installed and configured
- **API enabled** in Teampass settings
- **HTTPS connection** (required for security)
- **Valid API key** generated in Teampass
- **Compatible browser**: Chrome 88+, Edge 88+, or Firefox 89+

### Installation Steps

#### For Google Chrome

1. **Download** the extension ZIP file from the Teampass releases page
2. **Extract** the ZIP contents to a folder of your choice
3. **Open Chrome** and navigate to: `chrome://extensions/`
4. **Enable** "Developer mode" (upper right corner)
5. **Click** on "Load unpacked"
6. **Select** the extracted folder
7. ✅ Extension is installed!

#### For Microsoft Edge

1. **Download** and **extract** the ZIP file
2. **Install Font Awesome** (see Chrome step 3 above)
3. **Open Edge** and navigate to: `edge://extensions/`
4. **Enable** "Developer mode" (lower left corner)
5. **Click** on "Load unpacked"
6. **Select** the extracted folder
7. ✅ Extension is installed!

#### For Mozilla Firefox

1. **Download** and **extract** the ZIP file
2. **Install Font Awesome** (see Chrome step 3 above)
3. **Open Firefox** and navigate to: `about:debugging#/runtime/this-firefox`
4. **Click** on "Load Temporary Add-on..."
5. **Navigate** to the extracted folder and select the `manifest.json` file
6. ✅ Extension is installed!

⚠️ **Firefox Note**: The extension will be uninstalled on browser restart in developer mode. For permanent installation, the extension must be signed by Mozilla or installed from their official store. It will be the case after a test period.

### Initial Configuration

After installation:

1. **Right-click** on the extension icon → **Options**
2. **Fill in** the connection information:
   - **Teampass Server URL**: `https://your-teampass-server.com`
   - **License Email**: Email associated with your Teampass license
   - **Instance FQDN**: Fully qualified domain name of your instance
   - **Username**: Your Teampass username
   - **Password**: Your Teampass password
   - **API Key**: Key generated in Teampass (My Profile → API)
3. **Click** on "Save Configuration"
4. **Click** on "Force Re-authentication" to test the connection
5. ✅ All indicators should be green

---

## 🔐 License Logic

### General Principle

Starting from version 1.1.0, the Teampass extension verifies the validity of your license with the official license server.

### How Verification Works

#### When Verification Occurs

- **During JWT token refresh**: License is automatically verified when renewing the authentication token
- **Not on every operation**: To minimize network requests and preserve performance
- **Manual test**: "Test License" button available in the Options page

#### Required Data

The extension requires two pieces of information to verify your license:

1. **License Email**: The email address associated with your Teampass license
2. **Instance FQDN**: The fully qualified domain name of your Teampass server (e.g., `teampass.example.com`)

This information must be configured in the extension's Options page.

### License Server Responses

The license server can return different response codes:

| HTTP Code | Status | Meaning | Action |
|-----------|--------|---------|---------|
| **200 OK** | ✅ VALID | Valid and active license | ✅ Access authorized |
| **400 Bad Request** | ❌ INVALID | Malformed request | ❌ Access blocked immediately |
| **401 Unauthorized** | ❌ EXPIRED | Expired or invalid license | ❌ Access blocked immediately |
| **429 Too Many Requests** | ❌ LIMIT_EXCEEDED | User limit reached | ❌ Access blocked immediately |
| **5xx Server Error** | ⚠️ NOT_VERIFIED | Server temporarily unavailable | ⚠️ Grace period activated |
| **Network Error** | ⚠️ NOT_VERIFIED | Unable to reach server | ⚠️ Grace period activated |

### Grace Period

#### Principle

To avoid blocking users in case of temporary license server outage, the extension includes a **grace period**.

#### Activation Conditions

The grace period is activated only in these cases:

- **5xx server error**: The license server encounters a technical problem
- **Network error**: Unable to contact the server (timeout, DNS, etc.)

#### Does NOT activate for:

- ❌ Expired license (401)
- ❌ Invalid license (400)
- ❌ User limit exceeded (429)

#### Duration and Behavior

- **Counter**: Starts from the last successful verification
- **Expiration**: After a period without successful verification, access is blocked
- **Reset**: As soon as a verification succeeds, the counter is reset to zero

#### Storage

License information is stored locally in the browser:

```javascript
{
  status: 'VALID' | 'INVALID' | 'EXPIRED' | 'LIMIT_EXCEEDED' | 'NOT_VERIFIED',
  lastSuccessTimestamp: 1702542000000, // Last successful verification timestamp
  graceExpiryTimestamp: 1702974000000  // Grace period expiry timestamp
}
```

### Visual Indicators

#### In the Popup

A colored badge indicates the license status:

- 🟢 **Green**: Valid license (`VALID`)
- 🔴 **Red**: Invalid/expired/limit exceeded license (`INVALID`, `EXPIRED`, `LIMIT_EXCEEDED`)
- 🟠 **Orange**: Unverified license, grace period active (`NOT_VERIFIED`)

#### In the Options Page

The "Current Status" section displays:

- **License status**: Descriptive text with colored badge
- **"Test License" button**: Allows manual license verification
- **Error messages**: In case of configuration or license problems

### Security and Privacy

- ✅ **Secure connection**: All communications with the license server use HTTPS
- ✅ **Minimal data**: Only email and FQDN are transmitted
- ✅ **No tracking**: No usage data collection
- ✅ **Local storage**: License information remains in your browser

### Troubleshooting

#### "Unverified License" Message

**Possible causes**:
- License server temporarily unavailable
- Network connectivity issue
- First use of the extension

**Solution**:
- ⏳ Wait a few minutes and try again
- 🔄 Click on "Test License" in the Options page
- ✅ If the badge is orange (🟠), you're in the grace period

#### "Invalid License" or "Expired License" Message

**Causes**:
- Incorrect license email
- Incorrect FQDN
- License actually expired
- User limit reached

**Solution**:
1. Verify email and FQDN in the Options page
2. Check your license on the Teampass website
3. Contact Teampass support if necessary

#### Access Blocked

**Solution**:
1. Check your internet connection
2. Verify that the license server is accessible
3. Contact Teampass support if the problem persists

---

## ⚖️ License

This Teampass Extension, including all proprietary code, images, and documentation, is licensed under a **Commercial, Non-Public License**.

📋 **Ownership and Distribution:**
* This extension is proprietary software and the property of **LogCarré**.
* It is provided to you solely for use with your licensed Teampass instance, identified by your registered FQDN.

❌ **Restrictions:**
* You are strictly prohibited from copying, redistributing, modifying, or using the code for any purpose outside the scope of your commercial subscription.
* **Reverse Engineering and Unauthorized Access:** Decompilation, disassembly, or reverse engineering of the obfuscated or distributed code is strictly forbidden.
* **API Restriction:** Unauthorized access or abuse of the external licensing API endpoint is prohibited and will result in the immediate termination of your service.

**For full terms and conditions, please refer to the End-User License Agreement (EULA) provided at the time of purchase.**