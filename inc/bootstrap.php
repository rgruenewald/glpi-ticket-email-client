<?php
/** Plugin controllers must be dispatched by GLPI's router, including its CSRF listener. */
if (!defined('GLPI_ROOT') || !isset($GLOBALS['DB'])) {
    http_response_code(403);
    exit;
}
