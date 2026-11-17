<?php
/**
 * PHPUnit bootstrap for unit tests (no WordPress needed; WP functions are mocked with Brain Monkey).
 *
 * @package DiviBuddy
 */

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/vendor/autoload.php';
