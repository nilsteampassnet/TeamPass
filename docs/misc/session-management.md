# Session Management in TeamPass

## What is a session?

When you log into TeamPass, a **session** is created. It's like an entry ticket that remains valid for a limited time (60 minutes by default). After this period, you'll need to log in again for security reasons.

## How does the session counter work?

### The remaining time indicator

At the top of your screen, you'll see a counter displaying the time remaining before your session ends:

🕐 00:45:30

This counter updates automatically every second and displays:
- **Hours : Minutes : Seconds** remaining

### Automatic synchronization

Every 5 minutes, the counter checks with the server to ensure the displayed time is correct. This ensures that:
- If you have multiple tabs open, they all display the same time
- If you extend your session in one tab, the others update accordingly

## How to extend your session?

### Warning before expiration

When there are **50 seconds** remaining before your session ends:
1. A window appears automatically
2. The counter turns red
3. You have two options:
   - **Extend**: Add time to your session
   - **Ignore**: Let the session expire

### Manual extension

If you wish to extend your session:

1. A dialog box appears with a field to enter the duration
2. Enter the number of minutes you want to add (example: 60 for one hour)
3. Click **Confirm**
4. Your session is extended immediately
5. The counter updates with the new time

**Note:** The maximum duration you can add depends on the configuration set by your administrator.

## What happens when the session expires?

### Normal expiration

When the counter reaches **00:00:00**:
- You are automatically logged out
- You are redirected to the login page
- A message indicates that your session has expired

### Protection of your data

For your security:
- After expiration, no action is possible without logging in again
- Even if you click a button, you'll be redirected to the login page
- Your data remains protected

## Common situations

### You're working in multiple tabs

**Scenario:** You have TeamPass open in 2 different tabs.

**Behavior:**
- If you extend the session in tab 1
- Tab 2 will update automatically within the next 5 minutes
- You don't need to extend in each tab

### You're in the middle of work

**Scenario:** You're editing a password when the warning appears.

**What to do:**
1. Save your current work first if possible
2. Click "Extend" in the dialog box
3. Continue your work normally

### You're leaving your workstation

**Best practice:**
- Do NOT extend your session if you're stepping away
- Let the session expire naturally
- Lock your computer (Windows + L on Windows, Ctrl + Cmd + Q on Mac)

## Frequently Asked Questions

### Why does my session expire?

Sessions have a limited duration to protect your sensitive data. If someone accessed your computer during your absence, they wouldn't be able to access TeamPass without your password.

### What is my session duration?

The session duration is configurable by your administrator. By default, it is **60 minutes**. You can sometimes choose a different duration when logging in.

### Can I have a longer session?

This depends on your organization's configuration. Contact your administrator if you need a longer session duration for your work.

### My counter displays an incorrect time

If you notice the counter doesn't seem correct:

1. **Refresh the page** (F5) - the counter will resynchronize
2. **Wait 5 minutes** - automatic synchronization will correct the time
3. **If the problem persists** - contact your administrator

### I'm logged out even though there's time remaining

If you're logged out before the counter ends:

**Possible causes:**
- Network connection issue
- The server was restarted
- Your account was disabled

**Solution:**
- Log in again normally
- If the problem repeats, contact your administrator

### The extension dialog doesn't appear

If you didn't see the warning at 50 seconds:

**Checks:**
1. Make sure you don't have an active pop-up blocker
2. Verify that JavaScript is enabled in your browser
3. Try with another browser

**If the problem persists:** Contact your administrator

## Security recommendations

### ✅ Best practices

- **Extend your session** only if you're in front of your screen
- **Don't leave** an active session if you step away
- **Log out** manually when you're done
- **Lock your computer** if you leave your workstation

### ❌ Avoid

- Extending automatically without thinking
- Leaving a session open during lunch break
- Sharing your session with a colleague
- Using TeamPass on a public computer without logging out

## Need help?

If you encounter a problem with your session:

1. **Consult this documentation** for common issues
2. **Contact your TeamPass administrator** for technical assistance
3. **Provide details**: time of the problem, error message, browser used

---

**Reminder:** Session management is an important security measure. It protects your passwords and those of your organization from unauthorized access.