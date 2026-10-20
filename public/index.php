<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// All dates should be stored as UTC.  They will be rendered in the site-specific time zone.
date_default_timezone_set('UTC');

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
