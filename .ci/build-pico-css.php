<?php

// Build Pico CSS (fluid classless, zinc theme, --wicket- prefix) using Dart Sass CLI.

$root = __DIR__ . '/..';
$scss = __DIR__ . '/scss/wicket-pico.fluid.classless.zinc.scss';
$outDir = $root . '/assets/css';
$outCss = $outDir . '/_wicket-pico-fluid.classless.zinc.css';
$loadPath = $root . '/vendor/picocss/pico/scss';

if (!is_dir($outDir) && !mkdir($outDir, 0755, true)) {
    fwrite(STDERR, "Error: Unable to create output directory: {$outDir}\n");
    exit(1);
}

// Find sass binary
$sassBin = trim(shell_exec('command -v sass') ?? '');
if ($sassBin === '') {
    fwrite(STDERR, "Error: 'sass' CLI not found. Please install Dart Sass and re-run.\n");
    fwrite(STDERR, "macOS (Homebrew): brew install sass/sass/sass\n");
    fwrite(STDERR, "Or via npm: npm i -g sass\n");
    exit(1);
}

$missing = [];
if (!file_exists($scss)) { $missing[] = $scss; }
if (!is_dir($loadPath)) { $missing[] = $loadPath; }
if ($missing) {
    fwrite(STDERR, "Error: Missing required paths:\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
}

$cmd = escapeshellcmd($sassBin) . ' ' .
    '--style=compressed ' .
    '--no-source-map ' .
    '--load-path ' . escapeshellarg($loadPath) . ' ' .
    escapeshellarg($scss) . ' ' . escapeshellarg($outCss);

exec($cmd, $out, $code);
if ($code !== 0) {
    fwrite(STDERR, "Error: sass build failed with code {$code}.\n");
    exit($code);
}

echo "Built Pico CSS:\n{$outCss}\n";
