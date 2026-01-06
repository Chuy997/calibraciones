<?php
// clone_script_hw.php
// Clones ingenieria_*.php to assets_hw_*.php and replaces contents

$sourcePrefix = 'ingenieria_';
$targetPrefix = 'assets_hw_';

$files = glob(__DIR__ . '/' . $sourcePrefix . '*.php');

foreach ($files as $file) {
    $filename = basename($file);
    $targetFilename = str_replace($sourcePrefix, $targetPrefix, $filename);
    $targetPath = __DIR__ . '/' . $targetFilename;

    $content = file_get_contents($file);

    // Replacements
    // Order matters to avoid partial replacements if possible, though here prefixes are distinct enough.
    
    // 1. URL/File references: ingenieria_ -> assets_hw_
    $content = str_replace('ingenieria_', 'assets_hw_', $content);
    
    // 2. Table references: ingenieria_items -> assets_hw_items (covered by #1)
    // ingenieria_history -> assets_hw_history (covered by #1)
    // ingenieria_audits -> assets_hw_audits (covered by #1)
    
    // 3. ID Prefix: ING- -> HW-
    $content = str_replace("'ING-'", "'HW-'", $content);
    $content = str_replace('"ING-"', '"HW-"', $content);
    
    // 4. Titles / Display Text
    // "Activos Ingeniería" -> "Assets HW"
    $content = str_replace('Activos Ingeniería', 'Assets HW', $content);
    $content = str_replace('Ingeniería', 'Assets HW', $content); // General replacement for titles

    // 5. Column Names (FKs)
    // IngenieriaID -> AssetsHWID
    $content = str_replace('IngenieriaID', 'AssetsHWID', $content);
    
    // 6. Variable names (optional but good for consistency)
    // $ingenieria -> $assets_hw (might be tricky if valid vars)
    // Let's stick to functional strings first.
    
    // 7. Audit specific
    // GoldenID in audit items?
    // In ingenieria files, I might have left GoldenID if I didn't change the column in DB.
    // Let's check if I updated ingenieria files to use IngenieriaID.
    // Re-reading Step 128: I am creating assets_hw_audit_items with AssetsHWID.
    // If the source (ingenieria) used GoldenID or IngenieriaID, I need to match.
    // In Step 114 I created ingenieria_audit_items LIKE golden_audit_items.
    // golden_audit_items has `GoldenID`.
    // I did NOT run an ALTER on ingenieria_audit_items to change GoldenID -> IngenieriaID.
    // So `ingenieria_audit_items` likely still has `GoldenID` column!
    // BUT for `assets_hw_audit_items` I JUST ran an ALTER to change it to `AssetsHWID` (Step 130).
    // So I MUST replace `GoldenID` with `AssetsHWID` in the new files, assuming the source files used `GoldenID`.
    
    $content = str_replace('GoldenID', 'AssetsHWID', $content);
    // JUST IN CASE I did change it in source and forgot:
    $content = str_replace('IngenieriaID', 'AssetsHWID', $content);

    file_put_contents($targetPath, $content);
    echo "Created $targetFilename\n";
}

echo "Done.\n";
