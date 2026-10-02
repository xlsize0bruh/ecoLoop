<?php
// PHP's development server does not read .htaccess.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
if (preg_match('~(?:^|/)(?:\.|data/|tests/|docs/|manage\.php|Dockerfile|README|router\.php|api/(?:db|loop-core)\.php)~i',$path) || str_contains($path,'..')) {
    http_response_code(404); exit;
}
return false;
