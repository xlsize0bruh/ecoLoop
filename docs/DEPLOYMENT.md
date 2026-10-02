# Deploy EcoLoop

## Requirements

- Python 3.10 or newer with OpenSSL/scrypt support.
- A persistent local directory writable by the application.
- HTTPS for public use.
- Optional PHP route: PHP 8.1+, `proc_open`, and access to a Python interpreter.

No package installation is needed for the application itself. PHP and Python share a single implementation: PHP passes HTTP requests to `bridge.py`, and Python runs every application workflow and JSON transaction.

## Vercel

Vercel's serverless filesystem is not persistent, while EcoLoop requires shared, writable local storage for its JSON database, lock file and uploaded photos. The frontend may load even when the API cannot access storage; `/health` checks storage readiness and returns 503 when it is unavailable. Do not point `DATA_PATH` or `UPLOAD_DIR` at `/tmp` for a live community; that can lose data between function instances. Deploy EcoLoop as a Python WSGI app on a host with persistent writable storage, or move the backend to a persistent database and file-storage service before using Vercel for the frontend.

## alwaysdata: Python website

1. Upload the project to a private directory such as `/home/ACCOUNT/ecoloop`.
2. Select a supported Python version, for example Python 3.12.
3. Create `.env` from `.env.example`; set `APP_ENV=production`, `DEMO_MODE=0`, and `SITE_URL=https://ACCOUNT.alwaysdata.net`.
4. In an SSH shell, change to the project directory and run `python manage.py setup` using the configured Python interpreter.
5. Add/configure a **Python WSGI** website pointing to `/home/ACCOUNT/ecoloop/wsgi.py` and set its working directory to `/home/ACCOUNT/ecoloop`. Ensure that project directory is on the Python module search path if the host requires it.
6. Enable HTTPS and redirect HTTP to HTTPS. Restart the website after updating code or `.env`.
7. Confirm `/health` responds, register a member, create a listing and reload it. Check that `/storage/ecoloop.json` and `/.env` return 404.

The Python WSGI route does not need PHP. Provider controls can change; consult the provider's [Python configuration documentation](https://help.alwaysdata.com/en/docs/web-hosting/languages/python/configuration/).

## PHP/Apache website

1. Upload the project folder and set the website document root to its **`public/` subdirectory**.
2. Enable PHP 8.1+ and Apache rewrite rules. `public/.htaccess` routes requests through `index.php`.
3. Verify PHP can launch Python using `proc_open`. Some shared hosts disable this function or do not provide Python; use a Python website on those hosts.
4. If Python is not on PATH, copy `php-config.example.php` to `php-config.php` in the private project root and set `python_binary` to the interpreter's absolute path.
5. Configure `.env` for production as above. Run `python manage.py setup` in the project root through the host's shell.
6. Give the web-server account write permission to the configured JSON and uploads directories. Keep data outside `public/` and do not use world-writable permissions.
7. Set PHP `post_max_size` to at least `4M`; the application enforces a smaller 3,000,000-byte request limit and 2 MB image limit.
8. Enable HTTPS. Open the site root and verify the same checks as above.

For Nginx with PHP-FPM, configure all requests to execute `public/index.php` through FastCGI. Do not expose `.env`, project source, private configuration or JSON storage. Apache `.htaccess` rules are not used by Nginx.

See the [PHP process API](https://www.php.net/manual/en/function.proc-open.php) and [alwaysdata PHP configuration](https://help.alwaysdata.com/en/docs/web-hosting/languages/php/configuration/).

## Other Python WSGI hosts

Use `wsgi:application` as the WSGI application, with the project root as the working directory. Follow the host's instructions for installing and starting its production WSGI server. Both `app.py` and `php -S` are development servers.

Set a fixed HTTPS `SITE_URL`. EcoLoop rejects cross-origin writes and requires a session-specific token for signed-in mutations. If a reverse proxy is used, configure the WSGI server to derive the real client address only from that trusted proxy. EcoLoop does not trust arbitrary forwarded-IP headers.

All workers on one machine must point to the same JSON file, lock file and uploads directory. Do not use ephemeral storage: a restart must retain `storage/`.

## Data lifecycle

This version starts a new JSON community. It includes no legacy database importer and does not load old database files. Demo and real community data are separate.

Back up with `python manage.py backup` and check the credit/stock totals with `python manage.py check`. Stop all processes before restoring a backup. Corrupt JSON causes an error; the application will not silently replace it with an empty community.
