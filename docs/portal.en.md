# Zoty Customer Support – Manual

In the customer portal you raise support requests for Zoty and follow how they are resolved.
Each company has its own space – you see your colleagues' requests, but never those of other customers.

## Signing in

1. Open **Customer Support** (the **Support** link in the top bar of the release notes).
2. Enter the **email and password** you received from Zoty.
3. Switch the language with **CS / EN** in the top bar.

No account yet, or forgot your password? Contact us via the [contact page](https://zoty.cz/kontakt/) –
accounts are created and passwords changed by Zoty support.

## My requests

After signing in you see the requests of your company:

- **Open** – open, in progress and waiting for you. **Resolved** – resolved and closed. **All**.
- **Search** by text or by request key (e.g. `ACME-12`).
- **All types** – filter by request type. **Only mine** – only requests you raised.
- Each row shows the key, summary, status, severity, who raised it and when it last changed.

![Your company's requests with the Open / Resolved / All tabs, search and filters](uploads/manual/portal-list-en.png)

## New request

1. Click **+ New request**.
2. Choose the type:
   - **Support Incident** – help with installing, configuring or troubleshooting.
   - **Question** – using the app, licensing, how a feature works.
   - **Feature Request** – a new feature or a change to an existing one.

![Choosing the request type – Support Incident, Question or Feature Request](uploads/manual/portal-types-en.png)

3. Fill in the form. Required fields are marked with an asterisk **\***.

| Field | What to enter |
|---|---|
| **Product \*** | The product or module. Start typing and pick from the list; choose *Other* if it isn't there. |
| **Product Version \*** | The version you use. The newest one is preselected. |
| **Operating System \*** | The system where the problem occurs. |
| **Summary \*** | One sentence about the problem – it becomes the request title. |
| **Description \*** | As much detail as possible: what happened, steps to reproduce, what you expected, error messages. |
| **Attachments** | Screenshots, logs, documents – see below. |
| **Severity \*** | How serious the problem is – see the table. |

![The new request form: product, version, system, summary, description with toolbar, attachments and severity](uploads/manual/portal-form-en.png)

4. Click **Send**. The request gets a key (your company code and a number, e.g. **ACME-12**) and support is notified.

### Choosing the severity

| Severity | Use when |
|---|---|
| **Crisis** | Production outage – the service is unavailable and all core functionality is impacted. Use it **only** for real outages. |
| **High** | A key feature doesn't work and there is no workaround. |
| **Medium** | A feature is limited, but there is a workaround. |
| **Low** | A question, a minor or cosmetic issue. |

Choosing the appropriate severity helps us prioritize and respond as quickly as possible.

## Attachments

- **Drag and drop** files onto the attachment box, click **Browse**, or **paste a screenshot** with **Ctrl+V**
  anywhere in the form (also right into the description).
- You see a preview of the files before sending; remove one with **×**.
- Up to **10 files** at once, **10 MB** each. Allowed: images, PDF, Office documents, TXT, LOG, CSV, JSON, XML, ZIP, 7Z, EML and MSG.
- Attachments are private – only your company's users and Zoty support can open them.

## Following a request

Click a request to open its detail:

- **Description** and the attachments you added.
- **Addressed in release notes** – once the fix is part of a new version, you'll find a link to the release note here.
- **Activity** – comments from you, your colleagues and **Zoty Support** (marked *Support*), plus status changes.
- On the right: status, type, severity, product, version, system, who raised the request and when.

![Request detail: description with an attachment, status and details on the right, activity and comment box below](uploads/manual/portal-ticket-en.png)

Once the fix is released, you'll see it under **Addressed in release notes**:

![A resolved request linking to its release note, with the Reopen button](uploads/manual/portal-resolved-en.png)

**Comment** in the box under the activity, optionally attach files, and click **Send**.
If the request was waiting for you or resolved, your comment hands it back to support (status *Open*).

**Mark as resolved** – when the problem is solved. **Reopen** – when the problem comes back.

## Request statuses

| Status | Meaning |
|---|---|
| **Open** | New request, or you replied – it's support's turn. |
| **In progress** | Support is working on it. |
| **Waiting for customer** | Support needs information from you – reply with a comment. |
| **Resolved** | The problem is solved. If not, reopen the request. |
| **Closed** | The request is finished. |

## Emails

You get an email when support **replies** or **changes the status** of your request.
The email links straight to the request and is sent in the language you last signed in with.

**New version emails:** the switch at the top right of **My requests** turns the release notes of every new Zoty
version on or off. They go to your account's email address; no confirmation email is needed.

## Text formatting

The toolbar above the text inserts the formatting for you. You can also type it:

| Type | Result |
|---|---|
| `**text**` | **bold** |
| `*text*` | *italic* |
| `- item` | bullet |
| `1. item` | numbered list |
| `` `code` `` | `code` |
| ```` ``` ```` on a line before and after a block | code block (e.g. an error log) |
| `> text` | quote |
| `[text](https://…)` | link |

Shortcuts: **Ctrl+B** bold, **Ctrl+I** italic, **Ctrl+K** link.

## FAQ

| Problem | Solution |
|---|---|
| I can't sign in | Check your email and password. If that doesn't help, contact support – they will set a new password. |
| "Request not found" | The request belongs to another company or was deleted. |
| "The upload is too large" | Send fewer or smaller files at once – you can add more in a comment. |
| I don't see a colleague's request | Make sure **Only mine** is off and you are on the **All** tab. |
