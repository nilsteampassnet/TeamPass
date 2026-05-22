<!-- docs/features/knowledge-base.md -->

## Overview

The **Knowledge Base** (KB) is a built-in article repository for documenting internal procedures, policies, or any information that should be accessible alongside the password vault. It is separate from items: KB articles are not encrypted and are not tied to a folder's access rights.

The Knowledge Base must be enabled by an administrator before it becomes visible to users.

---

## Enabling the Knowledge Base

1. Go to **Admin → Settings → Collaboration**.
2. Toggle **Enable knowledge base** to on.
3. Click **Save**.

Once enabled, the **Knowledge Base** entry appears in the left navigation menu for all users.

---

## Roles and permissions

| Role | Capabilities |
|------|-------------|
| **Author** | Create articles, edit their own articles, delete their own articles |
| **Any user (if allowed)** | Edit any article where *Anyone can modify* is checked by the author |
| **Administrator** | All of the above + restore soft-deleted articles, permanently purge deleted articles |

Authors cannot edit or delete articles created by other users unless the *Anyone can modify* flag was set by the original author.

---

## Creating an article

1. Open the **Knowledge Base** page from the navigation menu.
2. Click **New article**.
3. Fill in:
   - **Title** — short descriptive title.
   - **Description** — optional summary shown in the article list.
   - **Content** — the article body (rich text editor).
   - **Tags** — comma-separated keywords for filtering.
   - **Folders** — restrict visibility to users who have access to the selected folders. Leave empty to make the article visible to all users.
   - **Anyone can modify** — allow any user to edit this article.
4. Attach files if needed (see [File attachments](#file-attachments)).
5. Click **Save**.

---

## Editing and deleting articles

- Click the **edit icon** (pencil) on an article row to open the edit form.
- Click the **delete icon** (trash) to soft-delete the article. Soft-deleted articles remain in the database and can be restored by an administrator.

> 🔔 Only the author and administrators can delete articles. Soft deletion moves the article to a hidden state — it does not permanently remove it.

---

## File attachments

Articles support file attachments. In the edit form:

1. Click **Choose file** or drag and drop onto the upload zone.
2. The file is stored in `storage/files/kb_attachments/` on the server.
3. Save the article.

Attachments are served through Teampass and require a valid session to download.

---

## Comments

Every article has a comment thread accessible at the bottom of the article detail view.

- Any user with access to the article can post a comment.
- Authors and administrators can delete comments.

---

## Searching and filtering

Use the **search bar** at the top of the Knowledge Base page to filter articles by title or description. The **Tags** filter narrows results to articles that share a selected tag.

---

## Administrator maintenance

Administrators have access to two additional maintenance operations under the Knowledge Base page (visible only to admins):

| Operation | Description |
|-----------|-------------|
| **Restore deleted articles** | Lists soft-deleted articles and allows selective restoration |
| **Purge deleted articles** | Permanently removes all soft-deleted articles (irreversible) |
