<?php
$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('data')
    ->exclude('build')
    ->notName('autoload_classmap.php')
    ->notName('autoload_function.php')
    ->notName('autoload_register.php')
    ->notName('LICENSE')
    ->notName('README.md')
    ->notName('.php_cs')
    ->notName('composer.*')
    ->notName('*.xml')
;

return Symfony\CS\Config\Config::create()
    ->finder($finder)
    ->setUsingCache(true)
;
