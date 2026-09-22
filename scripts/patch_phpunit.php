<?php

$phpunitBin = __DIR__ . '/../vendor/bin/phpunit';

if (file_exists($phpunitBin)) {
    $content = file_get_contents($phpunitBin);
    if (!str_contains($content, "if (file_exists(__DIR__ . '/pest'))")) {
        $patch = <<<'PHP'
if (file_exists(__DIR__ . '/pest')) {
    return include __DIR__ . '/pest';
}

PHP;
        if (str_contains($content, "namespace Composer;")) {
            $content = str_replace("namespace Composer;", "namespace Composer;\n\n" . $patch, $content);
        } else {
            $content = preg_replace('/<\?php\s*/', "<?php\n\n" . $patch, $content, 1);
        }
        file_put_contents($phpunitBin, $content);
    }
}

