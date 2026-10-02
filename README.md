# EcoTrade + EcoLoop

**Trade what you have. Build what you need.**

The original EcoTrade marketplace, sign-in, item management, direct barter, private trade chat, profile and champions screens, with a new **Project Studio** in the same PHP application.

The design keeps EcoTrade's charcoal backgrounds, Inter typography, compact buttons and restrained borders. EcoLoop adds warm material illustrations, selective lime accents, a responsive three-column project builder, supplier payout cards, a credit balance and a community collection desk. No frontend framework, Node server or Python application is required.

## Run

Requirements: **PHP 8.2+** and a writable data directory. No database server or PHP database extension is needed. The code is tested on PHP 8.4. Browser styling on the original EcoTrade pages uses the existing Tailwind CDN, Font Awesome CDN and Google Fonts; internet access is needed for those assets. The new Studio's CSS and illustrations are local.

From the repository root:

```sh
php -S 127.0.0.1:8000 router.php
```

Open `http://127.0.0.1:8000`. This starts a fresh community. Register accounts through the original sign-in page, using an eight-character-or-longer password and a six-digit pincode. Project materials match only within the same pincode.

Runtime state is stored as readable JSON files in `data/`, following the original EcoTrade approach. The web process must be able to write `data/` and `uploads/`. You can point `ECOLOOP_DATA_DIR` at another writable directory; this is useful for Docker volumes or keeping runtime data outside the web root. Back up the JSON files and uploaded images if the demo data matters.

To appoint an organiser, after registering the account:

```sh
php manage.php organiser "Account username"
```

This is a server-side CLI operation; members cannot promote themselves through the API. Run it with the same data directory/environment as the website.

## Isolated demonstration

Linux/macOS:

```sh
export ECOLOOP_DEMO=1
php manage.php demo
php -S 127.0.0.1:8000 router.php
```

Windows PowerShell:

```powershell
$env:ECOLOOP_DEMO='1'
php manage.php demo
php -S 127.0.0.1:8000 router.php
```

Open `login.php` and select **Enter demo community**. A labelled role selector lets you try Pratyush, Asha, Kabir, Mira and the community organiser. Demo data is isolated in `data/demo/*.json`; normal mode uses `data/*.json`. The role-switch endpoint returns 404 outside demo mode. Never enable demo mode on a real community deployment. Seeding refuses to overwrite existing accounts.

### Full demo journey

1. As Pratyush, choose Desktop organiser and **Find my materials**. It matches a three-sheet cardboard pack (20), three tubes (15), and a fabric-and-string pack (25), supplied by three owners. The total is 60; Pratyush initially has zero credits.
2. Switch to Community organiser. Open **Organiser** in the footer. Accept the seeded geometry box (20), sketch pad (15) and novel (25), explicitly confirming physical intake. These are staged demo confirmations, not claims that a real exchange happened.
3. Switch to Pratyush, match again and request the kit. Sixty credits and the exact quantities are held for up to 48 hours, or until the chosen collection date if sooner.
4. Switch to Asha, Kabir and Mira in turn. Each opens **My projects** and approves their own materials at the agreed value.
5. As organiser, check every material in after verifying condition, quantity and dimensions.
6. As Pratyush, confirm complete-kit collection. The single transaction pays Asha 20, Kabir 15 and Mira 25. Retrying collection cannot pay twice.
7. Each supplier opens **My credits**, sees the earned balance and requests a useful item from the community shelf. The organiser confirms handover; the matching credits are retired.
8. Pratyush records what was made and can reuse the plan or return to the original marketplace to list usable leftovers.

The exhibition template intentionally includes paper that is absent from the demo inventory. Its gap remains visible; an incomplete kit cannot be requested.

## The owner's benefit

Owners receive **choice**, not just recognition: agreed trade credits that can be used for other participating material listings (including single-item project requests) or useful goods on the shared shelf. Direct barter remains available in the unchanged Marketplace journey, and material owners can opt into a voluntary gift instead.

- Creating a listing or proposing a contribution creates **no credits**.
- An organiser can accept useful goods at the contributor's proposed value, or decline them. This creates shared stock and an equal credit issue. Renegotiate a different valuation with the member and submit a new contribution.
- Kit settlement transfers existing credits from maker to suppliers. It does not mint additional credits.
- Shelf handover removes the goods and retires their credit value.
- Project completion and badges do not earn spendable credits. Credits have no cash conversion or investment promise.

The practical dependency is demand: suppliers must be able to find goods they want. The organiser should only accept useful stock, cap overstocked categories, publish desired contributions and review shelf turnover. If no organiser or physical storage exists, don't issue credits for unsold goods. See [product decisions](docs/DECISIONS.md).

## Matching and collection

Owners first create an ordinary marketplace listing, then choose **Offer materials** in the Studio. They specify a material type, unit, quantity, minimum dimensions, condition, gift/credit preference and value per unit. Project participation is opt-in. Dimensions of packs describe each usable piece; titles/descriptions should state pack contents.

The matcher checks material type, unit, dimensions, condition, local community, owner, available quantity and reservations. Matching is deterministic and exact; it does not invent stock or silently substitute materials. It can split a requirement across suppliers. The user reviews a quote before requesting; changed suppliers, quantities or prices require a fresh quote. A saved draft can be reopened and matched against current inventory.

All supplier approvals are required. Quantities are provisionally held at request time so overlapping requests cannot promise the same goods. Only organisers check materials in; only the maker confirms complete-kit collection. Cancellation/expiry restores credits. Already-received goods remain unavailable until the organiser confirms return. A completed transfer keeps the original source listing locked for direct barter; any unconsumed project quantity stays available to project matching.

## Storage and consistency

EcoTrade's `readJson` / `writeJson` interfaces read and write ordinary JSON files. A request-wide file lock serializes simultaneous requests, and updates are written to a temporary file before replacing the destination. Project stock, holds and credit entries live together in `loop.json`, so the important kit settlement is one atomic file replacement. All transfer entries have transaction references; balances are derived from ledger history and active holds.

The committed JSON files are empty starting data. No accounts or transactions from either previous repository are migrated automatically. This replacement is a fresh installation; previous source remains in Git history.

## Deployment

Use a PHP host with persistent writable files, or the included Apache Docker image:

```sh
docker build -t ecoloop .
docker run --rm -p 8080:80 -v ecoloop-data:/var/www/ecoloop-storage -v ecoloop-uploads:/var/www/html/uploads ecoloop
```

Use HTTPS, persistent storage, JSON/upload backups and a single application instance using a shared local disk. The development server is for local demonstration. Apache must honour the supplied `.htaccess`; for another server configure equivalent denials for dotfiles, `data/`, `tests/`, `docs/`, `manage.php`, `router.php` and internal API helpers. Never execute uploaded files. Set secure, HttpOnly, SameSite session cookies in production. Disable PHP error display and add rate limiting at the web server for login/registration.

This repository replaces the former Python/WSGI application. An existing host configured to start `app.py`, `wsgi.py` or serve `public/` must be updated to use the repository root with PHP. Uploading this code does not change an external hosting service's runtime settings.

## Checks

```sh
php tests/workflows.php
python3 tests/integration.py
node --check assets/app.js
node --check assets/loop.js
```

Python is used only for HTTP integration tests. The test starts its own PHP server and temporary JSON data directory, checks original barter/chat and the full credit/project lifecycle, and shuts it down. Set `PHP_BIN` if PHP is not on PATH.

The current suites contain **46 domain checks and 47 HTTP checks**. GitHub Actions runs both plus syntax validation. Manual browser checks cover the 60-credit match, separate supplier approvals, organiser intake/check-in, collection, supplier redemption and responsive Studio views.

## Source continuity and limits

Base: `xlsize0bruh/ecotrade` at `8d76d88c229a9d6fde3409da66431a49ab56e509`. Its last JavaScript update references borrowing/donation endpoints and modal elements absent from that repository. This version restores `assets/app.js` from EcoTrade's matching complete UI revision `d8b9191` so the existing dashboard works; no functioning borrowed-item or donation server existed to carry over. The marketplace layout is preserved, with a Project Studio navigation link and focused permission/upload/transaction fixes.

This is a working community pilot, not an automated logistics service. Collection scheduling is coordinated with the organiser; the app does not guarantee availability by a date. It currently records finished-project text, not project photo uploads or automatically quantified leftovers. Relist leftovers through My Items. There is no AI dependency, automatic substitution, email notification service, cash system, loss/write-down accounting or multi-campus administration. Pause stock intake and resolve any physical stock discrepancy before continuing a real pilot.

