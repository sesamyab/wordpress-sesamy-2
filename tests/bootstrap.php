<?php
/**
 * PHPUnit bootstrap: composer autoload + WP_Mock.
 *
 * @package Sesamy2
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

WP_Mock::bootstrap();
