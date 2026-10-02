"""Production WSGI entry point: gunicorn wsgi:application."""
from ecoloop.config import load_env, data_path, upload_path

load_env()

# Ensure storage directories exist (Render's filesystem is ephemeral).
data_path().parent.mkdir(parents=True, exist_ok=True)
upload_path().mkdir(parents=True, exist_ok=True)

from ecoloop.web import create_application

application = create_application()
