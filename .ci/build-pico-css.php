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
$sassCommand = "sass --no-source-map --load-path={$root}/ --load-path={$root}/../vendor/picocss/pico/scss {$scssFile} {$cssOutputFile}";
executeCommand($sassCommand);

// Run PostCSS to scope all selectors under .wicket
$postcssCommand = "postcss {$cssOutputFile} --config {$pluginDir}/.ci/postcss.config.js --replace";
executeCommand($postcssCommand);

// Manually replace any remaining :host selectors with .wicket
$cssContent = file_get_contents($cssOutputFile);
$cssContent = str_replace(':host', '.wicket', $cssContent);
// Fix redundant .wicket selectors
$cssContent = preg_replace('/\.wicket[\s,]*\.wicket/', '.wicket', $cssContent);
// Fix redundant .wicket selectors with parentheses
$cssContent = preg_replace('/\.wicket\(([\s\S]*?)\)[\s,]*\.wicket/', '.wicket$1', $cssContent);
// Fix malformed selectors with extra closing parenthesis
$cssContent = preg_replace('/\.wicket:not\(\[data-theme=light\]\)\)/', '.wicket:not([data-theme=light])', $cssContent);
$cssContent = preg_replace('/\.wicket:not\(\[data-theme=dark\]\)\)/', '.wicket:not([data-theme=dark])', $cssContent);
// Fix malformed selectors missing theme value
$cssContent = preg_replace('/\.wicket:not\(\[data-theme\]\)/', '.wicket:not([data-theme=dark])', $cssContent);
// Fix specific malformed selector in dark theme section
$cssContent = preg_replace('/\.wicket:not\(\[data-theme\]\(:not\(\[data-theme\]\)/', '.wicket:not([data-theme=dark])', $cssContent);
// Fix extra closing parenthesis in dark theme selector
$cssContent = preg_replace('/\.wicket:not\(\[data-theme=dark\]\)\)\s*\{/', '.wicket:not([data-theme=dark]) {', $cssContent);
// Fix the specific malformed selector pattern we're seeing
$cssContent = preg_replace('/\.wicket\s*\[data-theme=light\]\s*input:is\(\[type=submit\],\s*\[type=button\],\s*\[type=reset\],\s*\[type=checkbox\],\s*\[type=radio\],\s*\[type=file\]\s*:root:not\(\[data-theme=dark\]\)\s*input:is\(\[type=submit\],\s*\[type=button\],\s*\[type=reset\],\s*\[type=checkbox\],\s*\[type=radio\],\s*\[type=file\]\),/', '.wicket [data-theme=light] input:is([type=submit], [type=button], [type=reset], [type=checkbox], [type=radio], [type=file]) {  --wpico-form-element-focus-color: var(--wpico-primary-focus);} :root:not([data-theme=dark]) input:is([type=submit], [type=button], [type=reset], [type=checkbox], [type=radio], [type=file]) {  --wpico-form-element-focus-color: var(--wpico-primary-focus);}', $cssContent);
// Fix the remaining malformed selector
$cssContent = preg_replace('/\.wicket:not\(\[data-theme=dark\]\)\s*input:is\(\[type=submit\],\s*\[type=button\],\s*\[type=reset\],\s*\[type=checkbox\],\s*\[type=radio\],\s*\[type=file\]\)\s*\{/', '.wicket:not([data-theme=dark]) input:is([type=submit], [type=button], [type=reset], [type=checkbox], [type=radio], [type=file]) {', $cssContent);
// Fix extra closing parenthesis in dark theme selector
$cssContent = preg_replace('/\.wicket:not\(\[data-theme=dark\]\)\)\s*input:is\(\[type=submit\],/', '.wicket:not([data-theme=dark]) input:is([type=submit],', $cssContent);
// Fix extra closing brace
$cssContent = preg_replace('/\}\s*\.wicket\s*\[data-theme=dark\]\s*\{/', '.wicket [data-theme=dark] {', $cssContent);
// Fix missing closing parenthesis in :where selector
$cssContent = preg_replace('/\.wicket :where\(:root :where\(.wicket\)\s*\{/', '.wicket :where(:root :where(.wicket)) {', $cssContent);
// Ensure the file ends with the proper closing brace for the main .wicket block
if (substr($cssContent, -3) !== '\n}\n') {
    $cssContent = rtrim($cssContent, "\n") . "\n}\n";
}
file_put_contents($cssOutputFile, $cssContent);

echo "Built and scoped Pico CSS: " . realpath($cssOutputFile) . "\n";
