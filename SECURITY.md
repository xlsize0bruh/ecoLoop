# Security and operating model

EcoLoop is intended for a supervised school/community pilot.

- Passwords use salted scrypt; raw session tokens are hashed before storage.
- Sessions use HttpOnly, SameSite cookies, Secure cookies in production, and CSRF tokens for authenticated writes.
- JSON mutation routes require JSON bodies and reject cross-site origins.
- JSON records, credentials and uploads stay outside the public document root.
- The Python static server has an explicit file allowlist; private files are never served.
- PHP launches a fixed Python script with an argument array, without interpolating request data into a shell command.
- Every API operation uses a process lock and atomic JSON commit. Domain errors do not partially commit reservations or credits.
- Existing credit entries are append-only through the application; filesystem administrators can still edit JSON, so protect and back up the directory.
- Organiser review and physical handover confirmation remain necessary. Credits have no cash value.
- Rate limits persist across workers and PHP requests. Behind proxies, configure trusted client IP handling at the web-server layer.
- Image validation checks size, declared type and signature. Uploaded images are public by URL; do not upload confidential material.
- Public demo accounts must not be enabled for a real community.

Email verification, password recovery, automated upload scanning, and comprehensive moderation are future work. Report vulnerabilities privately to the repository owner without including real user records in public issues.
