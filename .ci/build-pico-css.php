<?php

// Build Pico CSS (fluid classless, zinc theme, --wicket- prefix) using Dart Sass CLI.

// Function to execute shell commands and handle errors
function executeCommand($command) {
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        echo "Error executing command: $command\n";
        echo "Output: " . implode("\n", $output) . "\n";
        exit(1);
    }

    return $output;
}

$root = __DIR__;
$scss = $root . '/build-pico-css.scss';
$outDir = $root . '/../assets/css';
$outCss = $outDir . '/vanilla/_wicket-pico-fluid.classless.light.zinc.css';
$vendorLoadPath = $root . '/../vendor/picocss/pico/scss';
$localLoadPath  = $root . '/';

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

// Define paths
$pluginDir = __DIR__;
$projectRoot = dirname($pluginDir);
$scssFile = $root . '/build-pico-css.scss';
$cssOutputFile = $outCss;

// Compile SCSS to CSS using Dart Sass CLI with local overrides taking precedence
$sassCommand = "sass --no-source-map --silence-deprecation=if-function --load-path={$root}/ --load-path={$root}/../vendor/picocss/pico/scss {$scssFile} {$cssOutputFile}";
executeCommand($sassCommand);

// Run PostCSS to scope all selectors under .wicket
$postcssCommand = "postcss {$cssOutputFile} --config {$pluginDir}/.ci/postcss.config.js --replace";
executeCommand($postcssCommand);

$cssContent = file_get_contents($cssOutputFile);

file_put_contents($cssOutputFile, $cssContent);

echo "Built and scoped Pico CSS: " . realpath($cssOutputFile) . "\n";
