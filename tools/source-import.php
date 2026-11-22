<?php
/**
 * Copy the English source files from a local extension source tree into the repository.
 *
 *   php tools/source-import.php --version=v0 --root=D:\src\tuf_ed [--dry-run|--check] [--keep-missing]
 *
 * --root defaults to the environment variable TUF_ED_ROOT.
 *
 * Finds every .ini file in an "en-GB" folder below --root whose name matches the
 * version's "sourcePattern" in i18n/languages.json (a legacy "en-GB." prefix is
 * removed), and writes it to <version>/source/en-GB/admin/<name>:
 *   - line endings are converted to LF, a UTF-8 BOM is removed (with a warning)
 *   - the file must parse as a Joomla language file
 *   - the same file name found twice with different content is an error
 *   - files only rewritten when their content changed
 *   - repository source files no longer found locally are deleted (keep them with
 *     --keep-missing), so tools/sync.php removes their keys from all languages
 * Folders .git, node_modules and vendor are skipped.
 *
 * Exit code 0 also when nothing changed. Run tools/sync.php afterwards.
 * --check: change nothing; exit code 3 if the repository differs from the local files.
 */
require __DIR__ . '/lib.php';

$o = opts(['version:', 'root:', 'dry-run', 'check', 'keep-missing']);
$version = $o['version'] ?? fail('--version is required');
$root = $o['root'] ?? (getenv('TUF_ED_ROOT') ?: fail('--root is required (or set TUF_ED_ROOT)'));
$root = rtrim(str_replace('\\', '/', $root), '/');
if (!is_dir($root)) fail("source root $root does not exist");
$check = isset($o['check']);
$dry = isset($o['dry-run']) || $check;
$vmeta = languages()['versions'][$version] ?? fail("unknown version $version");
$pattern = $vmeta['sourcePattern'] ?? fail("sourcePattern missing for $version in languages.json");

// collect candidates
$found = [];   // name => [path, content]
$skipDirs = ['.git', 'node_modules', 'vendor'];
$it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    fn($f) => !($f->isDir() && in_array($f->getFilename(), $skipDirs, true))
));
$warnings = [];
foreach ($it as $f) {
    if (!$f->isFile() || basename($f->getPath()) !== 'en-GB' || strtolower($f->getExtension()) !== 'ini') continue;
    $name = preg_replace('/^en-GB\./', '', $f->getFilename());
    if (!preg_match('~' . $pattern . '~', $name)) continue;
    $path = str_replace('\\', '/', $f->getPathname());
    $raw = file_get_contents($path);
    if (str_starts_with($raw, "\xEF\xBB\xBF")) { $raw = substr($raw, 3); $warnings[] = "$path: UTF-8 BOM removed"; }
    if (!mb_check_encoding($raw, 'UTF-8')) fail("$path is not UTF-8");
    $content = str_replace(["\r\n", "\r"], "\n", $raw);
    if (@parse_ini_string($content, false, INI_SCANNER_RAW) === false) fail("$path does not parse as a Joomla language file");
    if (isset($found[$name]) && $found[$name][1] !== $content) {
        fail("$name exists twice with different content:\n  {$found[$name][0]}\n  $path");
    }
    $found[$name] ??= [$path, $content];
}
if (!$found) fail("no en-GB language files matching $pattern below $root");
ksort($found);

// compare with the repository
$dir = ROOT . "/$version/source/en-GB/admin";
$existing = is_dir($dir) ? array_map('basename', glob("$dir/*.ini")) : [];
$added = $changed = $removed = [];
foreach ($found as $name => [$path, $content]) {
    $target = "$dir/$name";
    if (!is_file($target)) $added[] = $name;
    elseif (file_get_contents($target) !== $content) $changed[] = $name;
    else continue;
    if (!$dry) { if (!is_dir($dir)) mkdir($dir, 0777, true); file_put_contents($target, $content); }
}
if (!isset($o['keep-missing'])) {
    foreach (array_diff($existing, array_keys($found)) as $name) {
        $removed[] = $name;
        if (!$dry) unlink("$dir/$name");
    }
}

foreach ($warnings as $w) echo "warning: $w\n";
printf("%s%d files found below %s: %d added, %d changed, %d removed, %d unchanged\n",
    $dry ? '[dry run] ' : '', count($found), $root, count($added), count($changed), count($removed),
    count($found) - count($added) - count($changed));
foreach (['added' => $added, 'changed' => $changed, 'removed' => $removed] as $what => $list) {
    foreach ($list as $n) echo "  $what: $n\n";
}
if ($check && ($added || $changed || $removed)) exit(3);
