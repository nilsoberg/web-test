<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.1',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    'bootstrap' => [
        'version' => '5.3.7',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'bootstrap/dist/css/bootstrap.min.css' => [
        'version' => '5.3.7',
        'type' => 'css',
    ],
    'bootstrap-icons/font/bootstrap-icons.min.css' => [
        'version' => '1.13.1',
        'type' => 'css',
    ],
    'stimulus-autocomplete' => [
        'version' => '3.1.0',
    ],
    'lodash-es' => [
        'version' => '4.17.21',
    ],
    'bootstrap_ui' => [
        'path' => './assets/bootstrap_ui.js',
        'entrypoint' => true,
    ],
    'cgfp_heatmap_ui' => [
        'path' => './assets/cgfp_heatmap_ui.js',
        'entrypoint' => true,
    ],
    'gnd_app_styles' => [
        'path' => './assets/gnd_app_styles.js',
        'entrypoint' => true,
    ],
    'skylign_app' => [
        'path' => './assets/skylign_app.js',
        'entrypoint' => true,
    ],
    'sweetalert2' => [
        'path' => 'vendor/sweetalert2/sweetalert2.index.js',
    ],
];
