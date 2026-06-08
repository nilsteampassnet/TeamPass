<!-- docs/features/knowledge-base.md -->

## Overview

The **Knowledge Base** (KB) is a built-in article repository for documenting internal procedures, policies, notes, and operational knowledge alongside the password vault.

KB articles are stored separately from password items. They can reference items, but article content itself is not encrypted like item passwords and is not controlled by folder permissions.

The Knowledge Base must be enabled before it becomes visible to regular users.

---

## Enabling the Knowledge Base

1. Go to **Administration > Settings > Collaboration**.
2. Enable the **Knowledge base feature** setting.
3. Save the settings.

Once enabled, the **Knowledge Base** entry appears in the navigation menu for regular users.

> **Note:** In the current application flow, administrators do not use the regular Knowledge Base article page. Administrative KB operations are exposed through the maintenance and logs utilities.

---

## Roles and permissions

| Role | Capabilities |
|------|--------------|
| **Author** | Create articles, edit their own articles, delete their own articles, manage attachments on their editable articles |
| **Any user, if allowed** | Edit articles where **Anyone can modify** is enabled by the author |
| **Regular user with access to KB** | Read articles and add comments when comments are enabled |
| **Administrator** | Restore or purge deleted KB articles and review KB logs from the administrative utilities |

Authors cannot edit articles created by other users unless **Anyone can modify** is enabled for the article. Only the author can delete an active article from the regular KB page.

---

## Creating an article

1. Open the **Knowledge Base** page from the navigation menu.
2. Click **Add entry**.
3. Fill in:
   - **Label**: the article title.
   - **Category**: the article category. Existing categories are suggested while typing.
   - **Description**: the article body.
   - **Anyone can modify**: allows other regular users to edit the article.
   - **Allow comments**: lets readers comment on the article.
   - **Associated items**: optional links to password items the current user can access.
4. Attach files if needed.
5. Save the article.

If files are selected while creating a new article, the article is saved first and the attachments are uploaded immediately after the article ID exists.

---

## Reading articles

Click the view icon on an article row to open its detail view. The detail view displays the article category, author, description, associated items, attachments, and comments.

When WebSocket support is enabled, other users can see that the article is currently being viewed. The current user's own consultation is filtered from their own UI.

---

## Editing and deleting articles

Click the edit icon on an article row, or use the edit button from the article detail view, to open the edit form.

When an existing article is opened for editing, TeamPass creates an edition lock:

- Other users cannot edit the same article while the lock is active.
- Delete operations are blocked while another user is editing the article.
- Attachment uploads and attachment deletions require the current user to hold the active edition lock.
- Comments remain available and are not blocked by the edition lock.
- Cancelling or saving the edit releases the lock.
- If the browser tab closes or the heartbeat stops, the lock expires automatically after the edition lock timeout.

The same locking rules apply with or without WebSocket support. Without WebSocket, the server still enforces the lock when another user tries to edit, delete, or mutate attachments. With WebSocket, lock and release indicators are also shown in real time.

---

## File attachments

Articles support file attachments. In the edit form:

1. Select one or more files.
2. Save the article or click the upload button when editing an existing article.
3. The files are stored in the KB attachments storage directory.

Attachments are served through TeamPass and require a valid session to download.

Deleting an attachment is treated as an article mutation and requires the current user to hold the article edition lock.

---

## Comments

Every article can have a comment thread when **Allow comments** is enabled.

- Any regular user with access to the article can add a comment.
- A comment can be deleted by its author or by the article author.
- Comments are intentionally independent from article edition locks.

---

## Searching and filtering

Use the table search field on the Knowledge Base page to filter articles by article data displayed in the list, including label, category, author, and summary text.

Associated item and comment counters are shown in the article list when relevant.

---

## Real-time collaboration

When WebSocket support is enabled, the Knowledge Base participates in TeamPass real-time collaboration:

- article creation, updates, and deletions refresh other connected KB views;
- edition locks are displayed to other users while an article is being edited;
- users who previously tried to edit a locked article are notified when the article becomes available again;
- article consultation presence shows who is currently reading an article.

The WebSocket channel is only a real-time notification layer. Lock ownership and mutation checks are enforced by the server-side KB endpoints and the `kb_edition` table.

---

## Administrative maintenance

Administrators can manage deleted KB articles and KB logs through the administrative utilities.

| Operation | Description |
|-----------|-------------|
| **Restore deleted articles** | Restores soft-deleted KB articles from the deleted entries store |
| **Purge deleted articles** | Permanently removes selected deleted KB entries and their related stored data |
| **KB logs** | Displays KB creation, update, delete, restore, view, attachment, and comment activity |
