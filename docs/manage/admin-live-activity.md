# Administrator live activity

The **Live activity** tile on the administrator dashboard refreshes every ten seconds and shows the ten latest matching events from the last five minutes. It uses the existing journals and works without the WebSocket service.

Use the gear button to select item changes, item consultations, failed sign-ins and lockouts, successful sign-ins, or knowledge-base activity (when that module is enabled). Successful sign-ins are disabled by default. The other available categories are enabled by default. Preferences are saved per administrator in the current browser; clearing browser storage resets them. No journal data is stored in browser storage.

The failed-sign-in counter covers the selected time window independently of the number of visible rows. Clicking it temporarily selects failed sign-ins only and opens the expanded view. This shortcut does not overwrite saved category preferences. Failure rows show the submitted login, the recorded reason, and the Web/API channel. An unknown or misspelled login remains visible. Successful-sign-in rows exclude other authentication-related events such as sending an MFA code.

Use the expand button to open the scrollable modal. Select the last 5, 15, or 30 minutes. The first page contains up to 50 matching events; **Load previous events** appends the next page. Categories are shared with the tile; the selected period applies to the modal only.

At the top of the first page, the expanded view refreshes automatically. While scrolled down or after loading previous events, it keeps the displayed rows and scroll position intact and announces new events with a button. Clicking that button, or **Refresh**, returns to the latest first page. Changing the period or categories also starts a new view. **View all logs** opens the full journals for further investigation.

Only recorded authentication failures appear. This feature does not change authentication, lockout rules, journal retention, or the coverage of authentication logging. It adds no database schema or installation settings. Access remains restricted to administrators, and the activity endpoint validates the session key.
