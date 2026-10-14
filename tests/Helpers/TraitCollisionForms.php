<?php

use Symfony\Component\Process\Process;

function loadTraitCollisionForm(string $class): string
{
    $root = realpath(__DIR__ . "/../..");
    $namespace = "GeneaLabs\\LaravelModelCaching\\Tests\\TraitCollisionForms";
    $code = sprintf(
            "require %s; require %s; echo class_exists(%s, false) ? 'loaded' : 'missing';",
            var_export("{$root}/vendor/autoload.php", true),
            var_export("{$root}/tests/TraitCollisionForms/{$class}.php", true),
            var_export("{$namespace}\\{$class}", true),
        );
    $process = new Process(
            [PHP_BINARY, "-d", "display_errors=stderr", "-r", $code],
            env: ["XDEBUG_MODE" => "off"],
        );
    $process->run();
    $output = $process->getOutput();

    return trim($output . $process->getErrorOutput());
}
