# EcoLoop

**Trade what you have. Build what you need.**

EcoLoop combines a community marketplace with a project studio. Students describe a project and its material requirements, match them to marketplace stock, and request a complete kit. Material owners approve their contribution and earn credits when the maker collects the kit. Those credits can be used for other community items.

## Stack

- **Python 3.10+**: accounts, marketplace, matching, credits, messages and organiser workflows.
- **HTML + CSS + browser JavaScript**: the responsive interface. JavaScript runs in the browser; no build tool or JavaScript server is needed.
- **PHP 8.1+**: an optional front controller that executes the Python application for each request.
- **JSON files**: all application records, with process locks and atomic writes. Uploaded photos are image files alongside the JSON store.

The Python application has no external runtime packages and starts with fresh data. The PHP route requires Python and `proc_open` on the same host. A PHP-only host that cannot execute Python will not run this version.

## Try it locally

From the folder containing `app.py`:

```bash
python app.py --demo
```

Open **http://127.0.0.1:3000**. On systems that use `python3`, substitute `python3` in the commands. On Windows, `py` also works.

The demo has five sample accounts and uses `storage/demo.json` with separate demo uploads. Use the account switcher to try the maker, suppliers and organiser. It does not read or change the live community file.

### Demo walkthrough

1. Choose **Pratyush** and open the Desktop organiser project.
2. Contribute useful goods worth 60 credits through **My credits**.
3. As **Community Organiser**, confirm physical receipt and accept the contribution.
4. As **Pratyush**, match the project and request the 60-credit kit.
5. As **Asha**, **Kabir** and **Mira**, approve the materials.
6. As the organiser, check each material in. As Pratyush, confirm complete-kit collection.
7. The suppliers receive 20, 15 and 25 credits. They can spend those credits on other items.
8. Mark the project finished and enter the quantities used. Usable leftovers return to the marketplace.

## Start a fresh community

Copy `.env.example` to `.env`. Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Linux/macOS:

```bash
cp .env.example .env
```

Create an organiser and start the application:

```bash
python manage.py setup
python app.py
```

The setup command asks for the organiser's name, email and password locally. Password entry is hidden. Other members register through the website. Run setup again locally if another organiser is needed; ordinary registrations always create members.

## Run through PHP

With Python and PHP installed:

```bash
python manage.py setup
php -S 127.0.0.1:8000 -t public public/index.php
```

Open **http://127.0.0.1:8000**. These built-in servers are for local development.

The gateway finds `python3` on the server's PATH by default. If needed, copy `php-config.example.php` to `php-config.php` and set the actual Python executable path. For example, Windows might use `C:/Python312/python.exe`. Alternatively, set the `PYTHON_BINARY` process environment variable. A value in `.env` cannot select the interpreter because Python reads that file after it starts.

For the PHP demo, run `python manage.py demo`, then set these values in `.env`:

```dotenv
APP_ENV=development
DEMO_MODE=1
DATA_PATH=storage/demo.json
UPLOAD_DIR=storage/demo-uploads
```

## Hosting

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for Python WSGI and PHP/Apache configuration, including alwaysdata.

Use persistent local storage and HTTPS. The server must be able to write to `storage/`. For PHP hosting, the document root must be **`public/`**, with the remaining files outside the public document root. The application uses root-relative URLs; deploy at the root of a hostname, not under a URL subfolder.

Production configuration:

```dotenv
APP_ENV=production
SITE_URL=https://your-account.alwaysdata.net
SECURE_COOKIES=1
DEMO_MODE=0
COMMUNITY_NAME=Your Community
COLLECTION_POINT=Your supervised collection point and hours
DATA_PATH=storage/ecoloop.json
UPLOAD_DIR=storage/uploads
```

## Included workflows

- Marketplace search and filters, editable listings, photos, credit exchanges, barter and gifts.
- Project requirements with dimensions, condition, quantities and approved alternatives.
- Matching against available stock, reusable project templates and missing-material requests.
- Multi-owner kits, supplier approval, organiser check-in and complete-kit collection.
- 48-hour reservations; cancellation releases credits and tracks physical material returns.
- Organiser-reviewed shelf contributions, supplier payouts, shelf redemption and contribution reversals.
- Append-only credit records and stock reconciliation; organisers can pause credit issuance.
- Participant-only messages, notifications, project showcases, leftover and creation relisting.

A listing alone creates no credits. Contributions issue credits only after organiser acceptance of physical goods. Member trades transfer credits; shelf redemptions retire credits. Credits have no cash value.

## JSON storage and backups

`storage/ecoloop.json` contains users, sessions, listings, projects, kits, exchanges, credit records, messages and configuration. Nested data uses JSON arrays and objects. `ecoloop/catalog.json` supplies project templates and supported materials. `data.example.json` shows the empty data structure; the application creates the real file automatically.

Each API operation acquires a separate file lock, reloads the latest JSON, validates the changes, and writes a temporary file before atomically replacing the snapshot. Failed operations roll back together. This protects reservations and credit settlement from concurrent requests on the same machine. Existing ledger entries cannot be modified through application transactions.

Use one host with a persistent **local filesystem**. Do not place the JSON on an object-storage bucket, synced drive or network filesystem, or run separate replicas with independent storage. This whole-file store is intended for a small supervised school/community pilot; file size and serialized writes limit throughput.

```bash
python manage.py check
python manage.py backup
```

Backups include the JSON and registered uploads under `storage/backups/`. Keep an additional copy elsewhere. To restore, stop all application processes and replace the configured JSON file and uploads directory with the matching backup contents. Do not commit live JSON, uploads, `.env` or backups to Git.

## Tests

```bash
python -m unittest discover -s tests -v
```

The suite covers supplier payouts exactly once, reconciliation, barter, cancellation and return handling, expiration, stale matches, access controls, sessions, request forgery checks, uploads, rollback, ledger immutability and simultaneous processes competing for the last item. It also starts real HTTP servers for the Python and PHP entry points. The PHP integration test skips locally when PHP is absent; GitHub Actions requires PHP and runs it.

## Files

| Path | Purpose |
| --- | --- |
| `app.py` | Local Python server and isolated demo launcher |
| `wsgi.py` | Production WSGI entry point |
| `manage.py` | Organiser setup, checks and backups |
| `ecoloop/domain.py` | Marketplace, projects, kits and credit rules |
| `ecoloop/store.py` | JSON locking, validation and atomic persistence |
| `ecoloop/web.py` | HTTP API, sessions, uploads and static-file allowlist |
| `ecoloop/seed.py` | Demo fixtures |
| `ecoloop/catalog.json` | Materials, conditions and project templates |
| `public/` | HTML, CSS, JavaScript, artwork and PHP front controller |
| `bridge.py` | Private PHP-to-Python request bridge |
| `tests/` | Workflow, security and HTTP integration tests |
| `docs/CONCEPT.md` | Product and hackathon concept |

## Pilot limitations

Account recovery, email verification, automated image scanning and a full moderation system are not included. Photo validation checks file signatures and size; it is not malware scanning. Organisers must supervise physical intake and collection. Do not enable public demo account switching on a real community.

## License

No open-source license is included. The repository owner retains all rights unless they choose to add a license.
