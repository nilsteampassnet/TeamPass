# Session Management Improvements

## What Changed?

TeamPass has improved the way user sessions are managed to provide a more reliable and secure experience.

## The Problem We Fixed

Previously, you might have experienced unexpected disconnections where:
- The countdown timer showed you still had time remaining
- You clicked on a folder or tried to view a password
- TeamPass suddenly logged you out anyway

This was frustrating because the timer was giving you incorrect information about your actual session status.

---

## What You'll Notice Now

### More Reliable Session Management

Your session countdown is now **synchronized with the server** every few minutes. This means:

✅ **Accurate countdown timer** - The time displayed always reflects your actual session status
✅ **No more surprise logouts** - If the timer shows 5 minutes remaining, you actually have 5 minutes
✅ **Consistent across tabs** - If you have TeamPass open in multiple browser tabs, they all show the same countdown

### How It Works for You

1. **When you log in**, your session starts (typically 60 minutes, depending on your administrator's settings)

2. **As time passes**, the countdown timer shows your remaining session time accurately

3. **At 2 minutes before expiration**, you'll see a dialog asking if you want to extend your session
   - Click the extend button to add more time (you choose how much)
   - This is **intentional** - you stay in control of your session

4. **If you extend your session**:
   - All your open tabs will update their countdown within a few minutes
   - You can continue working uninterrupted

5. **If you don't extend**:
   - When the countdown reaches zero, you'll be logged out properly
   - This protects your passwords when you step away from your computer

---

## Why This Is Better

### For Your Security
- **Predictable behavior** means you know exactly when you'll be logged out
- **Manual extension only** ensures someone can't access your passwords if you leave your computer unattended
- **Consistent enforcement** across all features - no more some features working while others don't

### For Your Productivity
- **No unexpected interruptions** - you won't lose work mid-task because of premature disconnections
- **Multi-tab support** - work across multiple tabs without confusion about your session status
- **Clear warnings** - you always get a 50-second warning before being logged out

### For Your Peace of Mind
- **Trustworthy countdown** - the timer accurately represents your actual session time
- **Control** - you decide when to extend, not the system automatically extending without your knowledge
- **Transparency** - you always know where you stand with your session

## What You Need to Do

**Nothing different!** The improvements work automatically. Just continue using TeamPass as you always have:

- When the extension dialog appears at 50 seconds, click "Extend" if you want more time
- Choose how much additional time you need
- Continue working

The only difference is that everything now works more reliably and predictably.

---

## Best Practices

### ✅ What to Do

- **Extend Consciously:** When you see the warning, decide if you really need more time
- **Log Out:** When you're done, use the logout button
- **Lock Your Screen:** If you step away, lock your computer (Windows + L)
- **Monitor the Countdown:** Keep an eye on remaining time if you're working on something important

### ❌ What to Avoid

- **Don't Extend Mechanically:** Think before extending
- **Don't Abuse Extensions:** If you're stepping away, let it expire
- **Don't Stay Connected Unnecessarily:** Log out when you're not working

---

## Technical Note

For administrators and technically-curious users: The session countdown now synchronizes with the server every 5 minutes, and session expiration is strictly enforced on all operations. The PHP session cookie lasts 24 hours to maintain session data, but the application session (what you see in the countdown) is controlled separately and enforced consistently.

---

## Questions?

If you experience any issues with session management, contact your TeamPass administrator. The improvements ensure that:
- Your countdown is always accurate
- You're never logged out unexpectedly
- All features respect your session timeout consistently
