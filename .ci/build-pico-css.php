<?php

// Build Pico CSS (fluid classless, zinc theme, --wicket- prefix) using Dart Sass CLI.

$root = __DIR__ . '/..';
$scss = __DIR__ . '/scss/wicket-pico.fluid.classless.zinc.scss';
$outDir = $root . '/assets/css';
$outCss = $outDir . '/_wicket-pico-fluid.classless.zinc.css';
$vendorLoadPath = $root . '/vendor/picocss/pico/scss';
$localLoadPath  = __DIR__ . '/scss';

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
if (!is_dir($vendorLoadPath)) { $missing[] = $vendorLoadPath; }
if (!is_dir($localLoadPath)) { $missing[] = $localLoadPath; }
if ($missing) {
    fwrite(STDERR, "Error: Missing required paths:\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
}

$cmd = escapeshellcmd($sassBin) . ' ' .
    '--style=expanded ' .
    '--no-source-map ' .
    // Prefer local overrides before vendor
    '--load-path ' . escapeshellarg($localLoadPath) . ' ' .
    '--load-path ' . escapeshellarg($vendorLoadPath) . ' ' .
    escapeshellarg($scss) . ' ' . escapeshellarg($outCss);

exec($cmd, $out, $code);
if ($code !== 0) {
    fwrite(STDERR, "Error: sass build failed with code {$code}.\n");
    exit($code);
}

// Run PostCSS to scope global rules to .wicket
$postcssBin = trim(shell_exec('command -v postcss') ?? '');
if ($postcssBin === '') {
    fwrite(STDERR, "Error: 'postcss' CLI not found. Please install postcss-cli globally and re-run.\n");
    fwrite(STDERR, "npm install -g postcss-cli\n");
    exit(1);
}

$postcssCmd = escapeshellcmd($postcssBin) . ' ' .
    escapeshellarg($outCss) . ' ' .
    '--config ' . escapeshellarg(__DIR__ . '/postcss.config.js') . ' ' .
    '--replace';

exec($postcssCmd, $postcssOut, $postcssCode);
if ($postcssCode !== 0) {
    fwrite(STDERR, "Error: postcss failed with code {$postcssCode}.\n");
    exit($postcssCode);
}

echo "Built and scoped Pico CSS:\n{$outCss}\n";
