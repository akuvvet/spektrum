<?php status_header(404); get_header();
echo spektrum_ersetzen((string)file_get_contents(__DIR__ . '/content/404.html'));
get_footer();
