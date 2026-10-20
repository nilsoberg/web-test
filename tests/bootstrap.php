<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

passthru(sprintf(
   'APP_ENV=%s php "%s/../bin/console" cache:clear --no-warmup',
   $_ENV['APP_ENV'],
   __DIR__
));

(new \Symfony\Component\Filesystem\Filesystem())->remove(__DIR__.'/../var/test_results');
