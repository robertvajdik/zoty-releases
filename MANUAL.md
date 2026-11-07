# Zoty Release Notes & Customer Support – User Manual

This manual covers the whole app for its three groups of users:

| Who | Where | What they do |
|---|---|---|
| **Visitors** | `/` (public site) | Read release notes, filter them, download them, subscribe to e-mail news |
| **Customers** | `/portal/` | Raise support requests and follow them |
| **Administrators** | `/admin/` | Write release notes, answer customer requests, manage customers |

The public site and the customer portal are in **Czech and English** (switch CS / EN in the top bar).
The administration is in Czech; this manual quotes Czech labels in **bold**, with a translation where useful.

For installation and server configuration see `install.md` and `readme.txt`.

---

## Contents

1. [Public release notes](#1-public-release-notes)
2. [Customer portal](#2-customer-portal)
3. [Administration – overview](#3-administration--overview)
4. [Workspace: Release notes](#4-workspace-release-notes)
5. [Workspace: Podpora (Support)](#5-workspace-podpora-support)
6. [Linking tickets and release notes](#6-linking-tickets-and-release-notes)
7. [Reference: text formatting](#7-reference-text-formatting)
8. [Reference: statuses, severities, request types](#8-reference-statuses-severities-request-types)
9. [E-mails the app sends](#9-e-mails-the-app-sends)
10. [Configuration for operators](#10-configuration-for-operators)
11. [Troubleshooting](#11-troubleshooting)

---

## 1. Public release notes

The home page lists all **published** versions, newest first. Each version shows its release date,
optional title and summary, and its entries (changes).

### Finding changes

- **Search** – full-text search in titles, descriptions, modules and version numbers.
- **Version** and **Module** – narrow the list to one version or one module (e.g. DataLink, Audit, Reporty).
- **Type chips** – show only one type of change:

  | Type | Czech | English |
  |---|---|---|
  | new | Vylepšení | Feature |
  | improved | Úprava | Improved |
  | fixed | Oprava | Fixed |
  | security | Zranitelnosti | Vulnerabilities |

- Each version has its own link (e.g. `/?version=v1.42.07`, or the `#v1.42.07` anchor) that you can share.

### Downloading

- **Download MD** (top bar) downloads the current selection, including filters, as a Markdown file.
- **Download MD** next to a version downloads just that version.

### E-mail news and RSS

- At the bottom of the page, enter your e-mail and click **Subscribe**. You'll get a confirmation e-mail;
  news is sent only after you confirm.
- Every news e-mail has an **Unsubscribe** link in the footer (one click, then confirm).
- RSS/Atom readers can use `/feed.xml`.

### Ticket keys in release notes

When a change was made because of a customer request, the entry may contain a blue key such as
**NBU_ZOTY-12**. Customers of that company can click it to open the request in the portal (sign-in required).
See [section 6](#6-linking-tickets-and-release-notes).

---

## 2. Customer portal

Address: **`/portal/`** (also linked as **Podpora / Support** in the top bar of the public site).

### 2.1 Signing in

1. Open `/portal/` and enter the **e-mail and password** you received from Zoty.
2. To change the language, use the link under the sign-in button or CS / EN in the top bar.
3. **Sign out** is in the top bar next to your company name.

You don't have an account? Ask Zoty support – accounts are created by the administrator (there is no self-registration).
Forgotten password: contact support, they will set a new one.

### 2.2 Your company's space

Each customer (company) has its own space. After sign-in you see **My requests**:

- All requests of **your company** – including those raised by your colleagues. You never see other companies' requests.
- Tabs: **Open** (open, in progress, waiting for you), **Resolved** (resolved and closed), **All**.
- **Search** in text, or type a request key (e.g. `ACME-12`).
- **All types** filter and **Only mine** (only requests you raised).
- Each row shows the key, summary, status, severity, who raised it and when it was last updated.

### 2.3 Raising a new request

1. Click **+ New request**.
2. Choose the request type:
   - **Support Incident** – help with installing, configuring or troubleshooting.
   - **Question** – using the app, licensing, how something works.
   - **Feature Request** – a new feature or a change to an existing one.
3. Fill in the form. Fields marked with **\*** are required.

| Field | What to enter |
|---|---|
| **Product \*** | The product or module concerned. Start typing to search the list. Choose *Other* if it's not listed. |
| **Product Version \*** | Version you use; the newest one is preselected. |
| **Operating System \*** | The OS where the problem occurs. |
| **Summary \*** | One line describing the problem – it becomes the title of the request. |
| **Description \*** | As much detail as possible: what happened, steps to reproduce, what you expected, error messages. Use the toolbar for formatting (see [section 7](#7-reference-text-formatting)). |
| **Attach any relevant files** | Screenshots, logs, documents. See below. |
| **Severity \*** | How serious the problem is – see the table below. |

**Choosing severity**

| Severity | Use when |
|---|---|
| **Crisis** | Production outage – the service is unavailable and all core functions are impacted. Use **only** for real outages. |
| **High** | A key feature doesn't work and there is no workaround. |
| **Medium** | A feature is limited, but there is a workaround. |
| **Low** | A question, a minor or cosmetic issue. |

Choosing the right severity helps support prioritise and respond as quickly as possible.

4. Click **Send**. The request gets a key such as **ACME-12** (your company key + number) and support is notified.

### 2.4 Attachments

- **Drag and drop** files onto the attachment box, click **Browse**, or **paste a screenshot** with **Ctrl+V**
  anywhere in the form (also inside the description).
- The image button in the editor toolbar opens the file picker too.
- Before sending you see a preview of each file; remove one with **×**.
- Limits: up to **10 files** per upload, **10 MB** each.
  Allowed: images (PNG, JPG, GIF, WebP), PDF, TXT, LOG, CSV, JSON, XML, ZIP, 7Z, Office documents (DOC/DOCX, XLS/XLSX, PPT/PPTX, ODT, ODS), EML, MSG.
- Attachments are private: only your company's users and Zoty support can open them.

### 2.5 Following a request

Click a request in the list to open its detail:

- **Left:** description and attachments, **Addressed in release notes** (if the fix was released),
  and **Activity** – comments from you, your colleagues and **Zoty Support** (marked *Support*), plus status changes.
- **Right:** current status, request type, severity, product, version, OS, reporter, created and updated dates.

**Adding a comment** – write in the box under the activity, optionally attach files, click **Send**.
If the request was *Waiting for customer*, *Resolved* or *Closed*, your comment puts it back to **Open**
so support sees it again.

**Mark as resolved** – click it when your problem is solved. **Reopen** brings a resolved request back.

You'll receive an e-mail when support replies or changes the status (see [section 9](#9-e-mails-the-app-sends)).

---

## 3. Administration – overview

Address: **`/admin/`**.

- **First visit:** the app asks you to create the administrator account (e-mail + password, min. 8 characters).
- Later visits: sign in with that e-mail and password. **Odhlásit** = sign out.

### Two workspaces

Under the top bar there is a switch between two separate workspaces:

| Workspace | Sections (tabs) | Main button |
|---|---|---|
| **Release notes** | Záznamy (entries) · Verze (versions) · Moduly (modules) · Odběratelé (subscribers) | **+ Nový záznam** (new entry) |
| **Podpora** (support) | Požadavky (requests) · Zákazníci a přístupy (customers & access) | **+ Nový zákazník** (new customer) |

- The red number on **Podpora** = requests waiting for support (new or with a customer's reply). It's visible from both workspaces.
- The top bar links to the **public site** (in Release notes) or the **customer portal** (in Podpora), opening in a new tab.
- The logo takes you to the home page of the current workspace.
- On a phone the section tabs scroll sideways.

### Help and manuals (Nápověda)

**Nápověda** in the top bar opens the manuals. The tabs switch between:

| Manual | Who reads it | Where |
|---|---|---|
| **Příručka administrace** (EN, CS) | administrators | `/admin/help` |
| **Nápověda zákaznického portálu** (CS, EN) | customers | `/portal/help` – link **Nápověda / Help** in the portal and on its sign-in page |

The portal shows the help in the customer's language (falling back to the other one).

**Editing a manual:** click **✎ Upravit**.

- Left: Markdown with the formatting toolbar (headings, bold, italic, lists, link, image, code, **table**, **divider**, quote).
  Right: live preview, rendered exactly like the published page.
- Images: toolbar button, drag & drop or Ctrl+V – they are uploaded and inserted automatically.
- Links to a chapter: `[text](#id-of-heading)`; the id is the heading in lower case with spaces as `-`
  (hover a heading to see its `#` link).
- **Uložit** (Ctrl+S) saves and stays in the editor, **Uložit a zobrazit** saves and opens the manual.
  Leaving with unsaved changes asks for confirmation.
- If someone else saved the manual meanwhile, you get a warning and your text is kept; saving again overwrites their version.
- A missing language version (e.g. the Czech admin manual) is created from the other language, ready to translate.
- **Obnovit výchozí text** replaces the manual with the original file (`MANUAL.md`, `docs/portal.cs.md`, `docs/portal.en.md`).

---

## 4. Workspace: Release notes

### 4.1 Versions (Verze)

A version groups the entries released together.

- **Create:** fill in **Číslo verze** (version number, format `v1.42.07`), **Datum vydání** (release date),
  optional **Název verze** (title) and **Shrnutí verze** (summary), then **Vytvořit verzi** – or **Vytvořit a přidat záznam**
  to continue straight to a new entry.
- **Upravit** – edit; **+ Záznam** – add an entry to this version.
- **Skrýt / Publikovat** – a hidden version hides **all its entries** on the public site.
- **Smazat** – only for versions without entries.
- **✉ Rozeslat** – sends this version to all confirmed subscribers by e-mail. After sending the button shows the date
  (**✉ Rozesláno dd.mm.**). It is disabled when there are no confirmed subscribers.

### 4.2 Entries (Záznamy)

The list shows all entries, including drafts, with filters (text, version, type, module, date from–to)
and counts at the top.

- **Stáhnout MD (publikované)** / **Stáhnout MD (vč. konceptů)** export the current filter as Markdown.
- Click a title to edit; row buttons publish/unpublish or delete.

**Creating an entry** (**+ Nový záznam**):

| Field | Notes |
|---|---|
| **Název změny \*** | Title of the change. |
| **Verze \*** | Pick a version, or **+ Nová verze…** to create one inline (number, date, title). |
| **Datum vydání verze** | Changing it changes the date of the **whole version**. |
| **Typ změny \*** | Vylepšení / Úprava / Oprava / Zranitelnosti. |
| **Moduly / oblasti** | Any number of modules, or none. |
| **Popis** | Description in simple formatting ([section 7](#7-reference-text-formatting)). Images: **🖼 Vložit obrázek**, drag & drop or Ctrl+V (JPG/PNG/GIF/WebP, max 5 MB). |
| **Publikovat na veřejném changelogu** | Unchecked = draft (not public). |

**Uložit a přidat další** saves and opens an empty form for the same version.

### 4.3 Modules (Moduly)

Add, rename or delete modules (e.g. DataLink, Audit, Reporty). Renaming updates all entries that use the module.
A module can be deleted only when no entry uses it. Modules also form the **Product** list in the customer portal.

### 4.4 Subscribers (Odběratelé)

- List of e-mail subscribers with status (**Potvrzeno** = confirmed, **Čeká** = waiting for confirmation); **Odebrat** removes one.
- **Test odesílání e-mailů** – send a test e-mail to check the mail settings; recent sending errors are shown below.

---

## 5. Workspace: Podpora (Support)

### 5.1 Customers and access (Zákazníci a přístupy)

Each customer is a company with its own space in the portal.

**Create a customer** (**+ Nový zákazník**):

- **Název** – company name.
- **Klíč požadavků** (request key) – prefix for request numbers, e.g. `ACME` → `ACME-1`, `ACME-2`…
  2–10 characters: letters A–Z, digits, underscore; must start with a letter, e.g. `NBU_ZOTY`.
  Input is tidied automatically (`nbu zoty`, `NBU-ZOTY` → `NBU_ZOTY`).
  The key **can't be changed** once the customer has requests.

**Users of a customer** (open the customer):

1. Fill in **E-mail** (sign-in name), **Jméno** (name) and **Heslo** (password, min. 8 characters) –
   **Vygenerovat** creates a strong one.
2. Keep **Poslat přihlašovací údaje e-mailem** checked to send the user the portal link, e-mail and password.
3. **Přidat uživatele**.

Further actions: **Upravit** (change name/e-mail, set a new password – leave empty to keep it),
**Zablokovat / Odblokovat** (block sign-in), **Smazat** (the user's requests stay).
**Smazat zákazníka** works only for customers without requests.

### 5.2 Request queue (Požadavky)

**Overview tiles** (click to filter):

- **čeká na podporu** – status *Open*: new requests or a customer has replied → act on these first.
- **nevyřešené v závažnosti Krize** – unresolved Crisis requests.
- **řeší se** – in progress. **čeká na zákazníka** – waiting for the customer.
- **vyřešeno za 7 dní** – resolved/closed in the last 7 days (and how many were created).

**Tabs** by status: Nevyřešené (all unresolved), Otevřeno, Řeší se, Čeká na zákazníka, Vyřešeno, Uzavřeno, Vše.
**Filters:** text or key (`ACME-12`), customer, type, severity. Unresolved requests are listed first, most recently updated on top.

### 5.3 Working on a request

Open a request by its key or summary. The detail is the same as the customer sees, plus admin tools:

- **Status** (right panel) – changing it saves immediately and **e-mails the customer**.
- **Severity** – can be corrected; the change is logged in the activity (no e-mail).
- **Odpovědět zákazníkovi** (reply) – write the answer, attach files, choose **Stav po odeslání** (status after sending;
  *Čeká na zákazníka* is preselected for open requests) and click **Odeslat odpověď**.
  The customer gets an e-mail with your reply.
- **Zákazník** links to the customer's settings; the reporter's e-mail is shown for direct contact.
- **+ Zapsat do release notes** – create a release-note entry from the request ([section 6](#6-linking-tickets-and-release-notes)).
- **Smazat požadavek** (bottom) – deletes the request with all comments and attachments. Cannot be undone.

In the activity, customers see your replies signed as **Zoty Support**; in the admin you see which admin wrote them.

**Suggested workflow**

```
Open ──► In progress ──► Waiting for customer ──► Resolved ──► Closed
  ▲                          │  customer replies        │ customer reopens
  └──────────────────────────┴──────────────────────────┘
```

---

## 6. Linking tickets and release notes

When a change in a release solves a customer request, write the request key (e.g. **NBU_ZOTY-12**) in the entry's
title or description.

- On the public site, emails and RSS the key becomes a **blue link** to the request in the portal.
  Only keys of existing customers are linked; other text such as `UTF-8` is left alone.
- The request detail shows **Addressed in release notes** (*Vyřešeno v přehledu změn*) with the entry, version and date.
  Customers see only published entries (click → the entry on the public site); admins see drafts too (click → editor).

**Fastest way:** in the request, click **+ Zapsat do release notes**. A new entry opens prefilled:

| From the request | In the entry |
|---|---|
| Summary | Title |
| Product (if it is a module) | Module |
| Key | Description "Požadavek NBU_ZOTY-12." |
| Type: Incident / Feature / Question | Type: Oprava / Vylepšení / Úprava |

Pick the version, adjust the text and save.

---

## 7. Reference: text formatting

### Release-note entries and version summaries

| Write | Result |
|---|---|
| empty line | new paragraph |
| `- item` | bullet list |
| `**bold**` | **bold** |
| `` `code` `` | `code` |
| `[text](https://…)` | link |
| `![description](uploads/…)` | image (inserted by the upload button) |
| `#20261101` | bug number shown in bold |
| `NBU_ZOTY-12` | link to the customer request |

### Requests and comments (portal and admin replies)

The editor toolbar inserts these for you; you can also type them.

| Toolbar | Write | Result |
|---|---|---|
| Tt ▾ | `# `, `## `, `### ` at line start | headings |
| **B** (Ctrl+B) | `**text**` | bold |
| *I* (Ctrl+I) | `*text*` or `_text_` | italic |
| S | `~~text~~` | strikethrough |
| bullet list | `- item` | bullet list |
| numbered list | `1. item` | numbered list |
| link (Ctrl+K) | `[text](https://…)` | link (plain `https://…` addresses are linked automatically) |
| image | – | opens the attachment picker |
| `</>` | `` `code` `` | inline code |
| code block | ```` ``` ```` lines around the block | code block |
| quote | `> text` | quotation |

Single line breaks are kept. HTML is never interpreted – it's shown as text.

---

## 8. Reference: statuses, severities, request types

**Statuses**

| Czech | English | Meaning | Set by |
|---|---|---|---|
| Otevřeno | Open | New, or the customer replied – support should act | system, customer (reopen/comment), admin |
| Řeší se | In progress | Support is working on it | admin |
| Čeká na zákazníka | Waiting for customer | Support needs information from the customer | admin |
| Vyřešeno | Resolved | Solved | admin, customer (*Mark as resolved*) |
| Uzavřeno | Closed | Finished, no more work | admin |

**Severities:** Krize / Crisis · Vysoká / High · Střední / Medium · Nízká / Low – see [2.3](#23-raising-a-new-request).

**Request types:** Hlášení incidentu / Support Incident · Dotaz / Question · Návrh na vylepšení / Feature Request.

---

## 9. E-mails the app sends

| Event | Recipient |
|---|---|
| Subscription to news | the subscriber – confirmation link |
| **✉ Rozeslat** on a version | all confirmed subscribers – version summary and entries |
| New request | support addresses from `config.php` (`support.notify`) |
| Customer comment, customer resolves/reopens | support addresses |
| Admin reply | the reporter – the reply text and a link |
| Admin changes status | the reporter – the new status and a link |
| New portal user (option checked) | the user – portal link, e-mail and password |
| **Test odesílání e-mailů** | the address you enter |

Customer e-mails are sent in the language the user last signed in with (Czech or English).
Failed sends are logged and shown under **Odběratelé → Test odesílání e-mailů**.

---

## 10. Configuration for operators

All settings are in `config.php` (template: `config.sample.php`). Details in `install.md` / `readme.txt`.

```php
'support' => [
    // who gets e-mails about new requests and customer comments
    'notify' => ['podpora@example.cz'],
    // choices for "Operating System" ("Jiné / Other" is always added)
    'os' => ['Windows 11', 'Windows 10', 'Windows Server 2022', 'Linux', 'macOS'],
],
```

- **Products** in the portal = the modules from **Moduly**; **Product Version** = published versions.
- Database tables are created automatically while `'auto_migrate' => true`.
- Attachments are stored in `data/tickets/` (not accessible from the web). Allow at least 10 MB in PHP
  `upload_max_filesize` and more in `post_max_size` (several files are sent together).
- Back up the database **and** the `data/` and `uploads/` folders.

---

## 11. Troubleshooting

| Problem | Solution |
|---|---|
| Customer can't sign in | Check the user exists and isn't **Zablokován** (blocked); set a new password under Zákazníci. |
| Customer sees "Request not found" | The key belongs to another company, or the request was deleted. |
| "The upload is too large" | Files exceed the server limit (`post_max_size`); send fewer or smaller files. |
| Support doesn't get e-mails about requests | Fill in `support.notify` in `config.php`; test sending under **Odběratelé**. |
| Customer key rejected | Use 2–10 characters A–Z, 0–9 or `_`, starting with a letter (e.g. `NBU_ZOTY`). |
| A ticket key in release notes isn't a link | The customer with that key doesn't exist, or the key is part of a URL or link text. |
| Old look after an update | Reload with **Ctrl+F5**. |
| **✉ Rozeslat** is disabled | There are no confirmed subscribers yet. |
