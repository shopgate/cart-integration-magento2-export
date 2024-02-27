<?php

// For more information visit the GitHub repository: https://github.com/FriendsOfPHP/PHP-CS-Fixer

// How to set up PHP-CS-Fixer in PHPStorm:
// https://hackernoon.com/how-to-configure-phpstorm-to-use-php-cs-fixer-1844991e521f

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$fixers = array(
    '@PSR1' => true,
    '@PSR2' => true,
    'blank_line_after_opening_tag' => true,
    'method_argument_space' => false,
);
$finder = (new Finder())
    ->in(__DIR__)
    ->exclude('tests/node_modules')
    ->exclude('vendor')
    ->exclude('vendors')
    ->exclude('release');

$config = new Config();
$config->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setRules($fixers)
    ->setFinder($finder);

return $config;
