<div align="center">
  <h1>💊 PharmaCore</h1>
  <p><strong>Pharmacy operations, made clear.</strong></p>
  <p>A lightweight web application for managing a pharmacy catalogue, inventory, sales and accounts.</p>
  <p>
    <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.1 or later">
    <img src="https://img.shields.io/badge/MySQL%20%2F%20MariaDB-PDO-4479A1?logo=mysql&logoColor=white" alt="MySQL or MariaDB with PDO">
    <img src="https://img.shields.io/badge/Interface-French-238636" alt="French interface">
    <img src="https://img.shields.io/badge/Frameworks-None-555555" alt="No application framework">
  </p>
</div>

<p align="center"><em>For the formatted view in VS Code, open the Markdown preview with <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>V</kbd>.</em></p>

<hr>

<div align="center">
  <a href="#at-a-glance">Overview</a> ·
  <a href="#get-started">Get started</a> ·
  <a href="#how-it-works">How it works</a> ·
  <a href="#configuration">Configuration</a> ·
  <a href="#production-checklist">Production checklist</a>
</div>

## At a glance

| 🌐 Public website | 🧾 Pharmacy operations | 🔐 Account security |
|:---|:---|:---|
| Browse pharmacy information and the medicine catalogue without creating an account. | Manage medicines, stock, sales, customers, notifications and reports from the admin area. | Optional client accounts, role-protected administration and single-use password reset links. |

### Built with

<p>
  <code>PHP</code> &nbsp; <code>MySQL / MariaDB</code> &nbsp; <code>PDO</code> &nbsp;
  <code>HTML</code> &nbsp; <code>CSS</code> &nbsp; <code>Vanilla JavaScript</code> &nbsp; <code>XAMPP</code>
</p>

PharmaCore is a small, framework-free application. The interface is in French; this README is an English project and setup overview.

> **Project status**  
> Intended for local development and evaluation. Confirm the database schema, pharmacy details, operational procedures and mail delivery before using the application in production.

## Get started

### Run locally with XAMPP

<table>
  <thead>
    <tr><th>Step</th><th>Action</th></tr>
  </thead>
  <tbody>
    <tr><td><strong>1 · Place</strong></td><td>Put the project in <code>C:\xampp\htdocs\PharmaCore</code>.</td></tr>
    <tr><td><strong>2 · Start</strong></td><td>Start Apache and MySQL from the XAMPP Control Panel.</td></tr>
    <tr><td><strong>3 · Check</strong></td><td>Use the existing <code>pharmacore_db</code> database and confirm that its schema matches the application.</td></tr>
    <tr><td><strong>4 · Configure</strong></td><td>Set database connection values for your local MySQL instance (see <a href="#database-connection">Database connection</a>).</td></tr>
    <tr><td><strong>5 · Create admin</strong></td><td>If no administrator exists, open <a href="http://localhost/PharmaCore/setup/create_admin.php"><code>http://localhost/PharmaCore/setup/create_admin.php</code></a> locally and create one.</td></tr>
    <tr><td><strong>6 · Open</strong></td><td>Visit <a href="http://localhost/PharmaCore/"><code>http://localhost/PharmaCore/</code></a> for the public site or <a href="http://localhost/PharmaCore/admin/login.php"><code>the admin login</code></a>.</td></tr>
  </tbody>
</table>

The installer only permits local access, refuses to create a second initial administrator and closes after the first administrator has been created. Administrator passwords must be at least 12 characters.

### Database connection

Configure these environment variables before Apache starts:

| Variable | Description |
|---|---|
| `PHARMACORE_DB_HOST` | MySQL server host |
| `PHARMACORE_DB_PORT` | MySQL server port |
| `PHARMACORE_DB_NAME` | Database name |
| `PHARMACORE_DB_USER` | Database username |
| `PHARMACORE_DB_PASSWORD` | Database password |

Defaults for local development are defined in `config/connexion.php`. The application expects the existing tables `utilisateurs`, `medicaments`, `ventes` and `vente_details`; client, notification and password-history features also use their corresponding existing tables. PharmaCore does not drop or replace existing tables or data.

## How it works

<details open>
  <summary><strong>Inventory and sales</strong></summary>
  <p>Administrators maintain medicine details, prices, quantities and expiry dates. When a sale is submitted, the server recalculates the total, checks stock and writes the sale, its line items and stock reductions within one database transaction. If validation fails, the transaction is rolled back. The applied product name and unit price are retained in sale details.</p>
</details>

<details>
  <summary><strong>Accounts and password recovery</strong></summary>
  <p>Client registration is optional. Administrators can upload a JPEG, PNG or WebP profile photo from account settings; it is stored locally and served only to authenticated administrators. Until a photo is uploaded, the account initials are shown. Passwords are stored as hashes. Reset tokens are random, single-use and stored as hashes; they expire after one hour. A new request invalidates previous active links. Administrator resets also check the existing password history. Client passwords require at least 8 characters; administrator passwords require at least 12.</p>
  <p>The reset-token table, <code>jetons_reinitialisation_mot_de_passe</code>, is created on demand with <code>CREATE TABLE IF NOT EXISTS</code>. This addition does not replace or delete existing data.</p>
</details>

<details>
  <summary><strong>Application structure</strong></summary>
  <table>
    <tbody>
      <tr><td><code>admin/</code></td><td>Administrator sign-in, dashboard and management pages</td></tr>
      <tr><td><code>assets/</code></td><td>Stylesheets and browser-side JavaScript</td></tr>
      <tr><td><code>client/</code></td><td>Optional client registration, sign-in and profile</td></tr>
      <tr><td><code>config/</code></td><td>PDO connection and pharmacy details</td></tr>
      <tr><td><code>includes/</code></td><td>Shared security, layout, reports and password-reset helpers</td></tr>
      <tr><td><code>setup/</code></td><td>First-administrator installer</td></tr>
      <tr><td><code>index.php</code></td><td>Public pharmacy website</td></tr>
    </tbody>
  </table>
</details>

## Configuration

### Pharmacy details

Update `config/pharmacie.php` with verified pharmacy name, address, contact information, opening hours and services before publishing. The example telephone number must be replaced.

The application displays amounts in Burundian francs (BIF). Confirm the currency and accuracy of existing database values; PharmaCore does not convert currencies. Catalogue entries marked `DEMO DATA:` are illustrative only and must not be treated as real products or stock.

### Password-reset email

Password-reset messages are sent by PHPMailer over SMTP. The PHPMailer dependency is installed with Composer using the committed `composer.json` and `composer.lock`.

| Setting | Purpose |
|---|---|
| `PHARMACORE_SMTP_HOST` | SMTP server hostname |
| `PHARMACORE_SMTP_PORT` | SMTP port, usually `587` for TLS or `465` for SSL |
| `PHARMACORE_SMTP_USERNAME` | SMTP account username |
| `PHARMACORE_SMTP_PASSWORD` | SMTP account password or provider app password |
| `PHARMACORE_SMTP_ENCRYPTION` | `tls` (default) or `ssl` |
| `PHARMACORE_MAIL_FROM` | Verified sender e-mail address |
| `PHARMACORE_MAIL_FROM_NAME` | Sender display name |
| `PHARMACORE_BASE_URL` | Absolute base URL recipients can access, e.g. `https://example.com/pharmacore` |

Copy `.env.example` to `.env` and enter the SMTP settings there. The `.env` file is excluded from Git and denied by Apache. Never put SMTP credentials in PHP source or commit `.env`. Use the mail provider's app password where required, not the account's normal password. `PHARMACORE_SMTP_ENCRYPTION=none` is accepted only for an unauthenticated SMTP server on `localhost`, `127.0.0.1` or `::1`; use TLS or SSL with authentication for external SMTP providers.

Without `PHARMACORE_BASE_URL`, links use the local development URL and cannot be opened from another device. A successful SMTP submission confirms acceptance by the configured mail server, not delivery to the recipient's inbox. Send a test request with the selected provider before production use.

### Requirements

- PHP 8.1 or later; developed and checked with PHP 8.2.
- PHP extensions `pdo_mysql` and `mbstring`.
- MySQL or MariaDB.
- Internet access for the optional Chart.js dashboard charts and Google Fonts. Core pages and data tables remain available if external assets cannot load.

## Production checklist

<table>
  <tbody>
    <tr><td>☐</td><td>Replace example pharmacy contact details and demo catalogue data with verified information.</td></tr>
    <tr><td>☐</td><td>Use HTTPS and a least-privilege database account.</td></tr>
    <tr><td>☐</td><td>Keep database and SMTP credentials outside the repository; protect backups.</td></tr>
    <tr><td>☐</td><td>Verify reset-email delivery and use an externally reachable application URL.</td></tr>
    <tr><td>☐</td><td>Review applicable pharmacy, privacy and data-retention requirements before deployment.</td></tr>
  </tbody>
</table>

<div align="center">
  <sub>PharmaCore · Pharmacy management · PHP + MySQL</sub>
</div>

## Résumé en français

PharmaCore est une application web de gestion de pharmacie en PHP et MySQL. Elle associe un site public consultable sans compte à un espace administrateur pour gérer le catalogue, le stock, les ventes et les rapports. Les clients peuvent créer un compte facultatif. Les ventes sont vérifiées côté serveur et enregistrées dans une transaction. Les mots de passe sont hachés ; la réinitialisation par e-mail nécessite un relais SMTP configuré. Le projet est prévu pour un démarrage local avec XAMPP : vérifiez les données et la configuration avant tout déploiement.
