<?php
/**
 * Script to process Pico CSS files and rename .pico class to .wicket
 *
 * This script:
 * 1. Copies pico.conditional.zinc.css to assets/css/wicket-pico.css
 * 2. Copies pico.fluid.classless.conditional.zinc.css to assets/css/wicket-pico-fluid.css
 * 3. Renames all instances of .pico class to .wicket (but leaves --pico-* CSS variables unchanged)
 */

// Define paths
$targetDir = __DIR__ . '/../assets/css';

// Ensure target directory exists
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        echo "Error: Failed to create target directory: $targetDir\n";
        exit(1);
    }
}

// Define CSS files to process
$cssFiles = [
    [
        'source' => __DIR__ . '/../vendor/picocss/pico/css/pico.classless.zinc.css',
        'target' => $targetDir . '/wicket-pico.css'
    ],
    [
        'source' => __DIR__ . '/../vendor/picocss/pico/css/pico.fluid.classless.zinc.css',
        'target' => $targetDir . '/wicket-pico-fluid.css'
    ]
];

// Process each CSS file
foreach ($cssFiles as $file) {
    processCssFile($file['source'], $file['target']);
}

/**
 * Process a CSS file by copying it and replacing .pico class names with .wicket
 *
 * @param string $sourceFile Path to the source CSS file
 * @param string $targetFile Path to the target CSS file
 * @return void
 */
function processCssFile($sourceFile, $targetFile) {
    // Check if source file exists
    if (!file_exists($sourceFile)) {
        echo "Error: Source file does not exist: $sourceFile\n";
        exit(1);
    }

    // Read the source file content
    $content = file_get_contents($sourceFile);
    if ($content === false) {
        echo "Error: Failed to read source file: $sourceFile\n";
        exit(1);
    }

    // Separate header (@charset, comments) from the main CSS content
    $header = '';
    $mainContent = $content;

    // Use a regex to find the header part (everything before the first ruleset)
    // This looks for @charset, and any comments before the first selector like `:root` or `a`
    if (preg_match('/^((?:@charset[^;]+;\s*)?(?:\/\*.*?\*\/\s*)*)/s', $content, $matches)) {
        $header = $matches[1];
        $mainContent = substr($content, strlen($header));
    }

    // Wrap the main content in .wicket class for scoping
    $scopedContent = ".wicket {\n" . trim($mainContent) . "\n}";

    // Combine header and scoped content
    $finalContent = $header . $scopedContent;

    // Write the modified content to the target file
    if (file_put_contents($targetFile, $finalContent) === false) {
        echo "Error: Failed to write processed content to $targetFile\n";
        exit(1);
    }

    echo "Successfully processed Pico CSS file.\n";
    echo "Source: $sourceFile\n";
    echo "Target: $targetFile\n";
}

exit(0);
