<?php
/**
 * Build the translations for shipping.
 *
 *   php tools/build.php --version=v0 [--format=embed|packs] [--lang=de-DE] [--exclude=de-DE]
 *                       [--into=<package folder> [--manifest=pkg_tuf_ed.xml]] [--out=dist]
 *
 * --format=embed (default): translations for the pkg_tuf_ed package itself.
 *   dist/<version>/embed/language/<tag>/<file>.ini   all languages, all extensions
 *   dist/<version>/embed/languages.xml               <languages> block for pkg_tuf_ed.xml
 *   Copy the language/ folder next to pkg_tuf_ed.xml and paste the block into it. Joomla
 *   installs a language file only if the site already has that language (its folder
 *   administrator/language/<tag> exists), and removes them again on uninstall.
 *
 *   --into=<package folder> does that copy for you: the files go to <folder>/language/<tag>/
 *   and the <languages> block of <folder>/pkg_<slug>.xml (or --manifest=<file name>) is
 *   updated between "tuf-languages: begin/end" markers. Your own <language> lines outside
 *   the markers are kept. Files written by an earlier run that are no longer generated
 *   are removed.
 *
 * --exclude=de-DE[,..]: leave out languages you maintain yourself in your sources.
 *
 * --format=packs: separate installable language packs.
 *   dist/<version>/file_<slug>_lang_<tag>-<pkgversion>.zip per language, a Joomla "file"
 *   extension (site/* -> language/<tag>/, admin/* -> administrator/language/<tag>/), and
 *   without --lang also pkg_<slug>_languages-<pkgversion>.zip with all of them.
 *
 * File names are unprefixed (com_tuf_ed.ini), the form Joomla documents since 4.0.
 * Product name, slug and package version come from i18n/languages.json.
 *
 * Review-required strings: keys matching a pattern in "reviewRequired" of the version
 * (security warnings, the key-management statement ...) are shipped in English until a
 * native speaker has reviewed them. A machine translation of such a key stays in the
 * repository, but never reaches a site.
 */
require __DIR__ . '/lib.php';

$o = opts(['version:', 'lang:', 'out:', 'format:', 'exclude:', 'into:', 'manifest:']);
$date = date('Y-m-d');
$version = $o['version'] ?? fail('--version is required');
$out = ROOT . '/' . trim($o['out'] ?? 'dist', '/') . "/$version";
$meta = languages();
$vmeta = $meta['versions'][$version] ?? fail("unknown version $version (see i18n/languages.json)");
$pkgVersion = $vmeta['packageVersion'] ?? fail("packageVersion missing for $version in languages.json");
$product = $vmeta['product'] ?? fail("product missing for $version in languages.json");
$slug = $vmeta['slug'] ?? fail("slug missing for $version in languages.json");
$state = loadState($version);
$langs = isset($o['lang']) ? [$o['lang']] : $vmeta['languages'];
$exclude = array_filter(array_map('trim', explode(',', $o['exclude'] ?? '')));
$langs = array_values(array_diff($langs, $exclude));
if (isset($o['into']) && ($o['format'] ?? 'embed') !== 'embed') fail('--into works with --format=embed only');
if (!$langs) fail("no languages listed for $version in languages.json");
if (!is_dir($out)) mkdir($out, 0777, true);

/** Rendered translation of one file, with unreviewed review-required strings in English. */
$renderShipped = function (string $lang, string $rel) use ($version, $state): string {
    $values = parseIni(transPath($version, $lang, $rel)) ?? fail("$version/translations/$lang/$rel does not parse");
    foreach ($values as $k => $v) {
        $st = $state[$lang][$rel][$k] ?? null;
        if (reviewRequired($version, $k) && ($st['s'] ?? '') !== 'reviewed') unset($values[$k]);
    }
    return render($version, $rel, $values, $lang);
};

/** Delete a folder tree (portable, also on Windows). */
function removeTree(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    rmdir($dir);
}

if (($o['format'] ?? 'embed') === 'embed') {
    // One <languages> block for the pkg_tuf_ed manifest. Joomla only reads the first
    // <languages> element of a manifest and takes the uninstall path from its client
    // attribute, so all files must belong to the administrator client.
    $files = sourceFiles($version);
    $site = array_filter($files, fn($r) => !str_starts_with($r, 'admin/'));
    if ($site) fail('embed format supports administrator language files only, found: ' . implode(', ', $site));
    $dir = "$out/embed";
    removeTree($dir);
    $lines = [];
    foreach ($langs as $lang) {
        mkdir("$dir/language/$lang", 0777, true);
        foreach ($files as $rel) {
            $file = basename($rel);
            file_put_contents("$dir/language/$lang/$file", $renderShipped($lang, $rel));
            $lines[] = "<language tag=\"$lang\" client=\"administrator\">$lang/$file</language>";
        }
    }
    $note = "$product translations, generated by tuf-languages tools/build.php."
        . " Joomla installs a language only if the site has that language installed.";
    $snippet = "\t<!-- $note -->\n\t<languages folder=\"language\" client=\"administrator\">\n"
        . implode("\n", array_map(fn($l) => "\t\t$l", $lines)) . "\n\t</languages>\n";
    file_put_contents("$dir/languages.xml", $snippet);
    printf("%d languages, %d files: %s/language/ and languages.xml\n", count($langs), count($lines), substr($dir, strlen(ROOT) + 1));
    if (isset($o['into'])) installInto(rtrim(str_replace('\\', '/', $o['into']), '/'), $o['manifest'] ?? "pkg_$slug.xml", $dir, $langs, $lines, $note);
    exit(0);
}

/**
 * Copy the generated language files into a package source folder and keep its manifest's
 * <languages> block up to date. Only the part between the tuf-languages markers is
 * rewritten; other <language> lines (e.g. your own en-GB or de-DE files) stay as they are.
 */
function installInto(string $pkgDir, string $manifestName, string $built, array $langs, array $lines, string $note): void
{
    $manifest = "$pkgDir/$manifestName";
    if (!is_file($manifest)) fail("manifest $manifest not found (use --manifest=<file name>)");
    $xml = file_get_contents($manifest);
    $eol = str_contains($xml, "\r\n") ? "\r\n" : "\n";
    $begin = '<!-- tuf-languages: begin -->';
    $end = '<!-- tuf-languages: end -->';

    preg_match_all('~^([ \t]*)<languages\b([^>]*)>(.*?)</languages>~ms', $xml, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    if (count($m) > 1) fail("$manifestName has more than one <languages> block; Joomla only reads the first one. Merge them.");
    $previous = [];   // tag => [file, ...] from the last generated section
    if ($m) {
        [$whole, $off] = $m[0][0];
        $indent = $m[0][1][0];
        $attrs = $m[0][2][0];
        $inner = $m[0][3][0];
        if (!preg_match('~\bfolder="language"~', $attrs)) fail("<languages> in $manifestName must have folder=\"language\" (the generated files are copied to $pkgDir/language/<tag>/).");
        if (!preg_match('~\bclient="administrator"~', $attrs)) {
            fail("<languages> in $manifestName needs client=\"administrator\": Joomla uses it to remove the files on uninstall. "
                . "Add it, and add client=\"administrator\" to each of your own <language> lines as well (package .sys.ini files belong to the administrator).");
        }
        $own = $inner;
        if (($b = strpos($inner, $begin)) !== false && ($e = strpos($inner, $end, $b)) !== false) {
            $section = substr($inner, $b, $e - $b);
            preg_match_all('~tag="([^"]+)"[^>]*>([^<]+)</language>~', $section, $pm, PREG_SET_ORDER);
            foreach ($pm as $p) $previous[$p[1]][] = basename(trim($p[2]));
            $own = substr($inner, 0, $b) . substr($inner, $e + strlen($end));
        }
        preg_match_all('~<language\b[^>]*\btag="([^"]+)"~', $own, $om);
        $clash = array_intersect($om[1], $langs);
        if ($clash) fail("$manifestName already lists your own files for " . implode(', ', array_unique($clash)) . ". Exclude these languages (--exclude) or remove those lines.");
        $own = rtrim(preg_replace('~[ \t]*' . preg_quote($eol, '~') . '\s*$~', '', $own));
        $section = "$indent\t$begin$eol$indent\t<!-- $note -->$eol"
            . implode($eol, array_map(fn($l) => "$indent\t$l", $lines)) . "$eol$indent\t$end";
        $block = "$indent<languages$attrs>" . ($own !== '' ? "$own$eol" : $eol) . "$section$eol$indent</languages>";
        $xml = substr($xml, 0, $off) . $block . substr($xml, $off + strlen($whole));
    } else {
        $pos = strrpos($xml, '</extension>');
        if ($pos === false) fail("$manifestName has no </extension>");
        $block = "\t<languages folder=\"language\" client=\"administrator\">$eol\t\t$begin$eol\t\t<!-- $note -->$eol"
            . implode($eol, array_map(fn($l) => "\t\t$l", $lines)) . "$eol\t\t$end$eol\t</languages>$eol";
        $xml = substr($xml, 0, $pos) . $block . substr($xml, $pos);
    }

    // remove files written last time that are no longer generated
    $removed = 0;
    foreach ($previous as $tag => $list) {
        foreach ($list as $file) {
            if (in_array($tag, $langs, true) && is_file("$built/language/$tag/$file")) continue;
            if (is_file("$pkgDir/language/$tag/$file")) { unlink("$pkgDir/language/$tag/$file"); $removed++; }
        }
        if (is_dir("$pkgDir/language/$tag") && !glob("$pkgDir/language/$tag/*")) rmdir("$pkgDir/language/$tag");
    }
    $copied = 0;
    foreach ($langs as $tag) {
        if (!is_dir("$pkgDir/language/$tag")) mkdir("$pkgDir/language/$tag", 0777, true);
        foreach (glob("$built/language/$tag/*.ini") as $f) { copy($f, "$pkgDir/language/$tag/" . basename($f)); $copied++; }
    }
    file_put_contents($manifest, $xml);
    printf("into %s: %d files copied, %d old files removed, <languages> block in %s updated\n", $pkgDir, $copied, $removed, $manifestName);
}

$repoUrl = 'https://github.com/schultz-it-solutions/tuf-languages';
$targets = ['site' => 'language', 'admin' => 'administrator/language'];
$built = [];

foreach ($langs as $lang) {
    $element = $slug . '_lang_' . strtolower(str_replace('-', '_', $lang));
    $name = $meta['names'][$lang]['english'] ?? $lang;
    // coverage for the description; held-back review-required strings count as untranslated
    $n = 0; $tr = 0; $rev = 0; $held = 0;
    foreach (sourceFiles($version) as $rel) {
        foreach (sourceStrings($version, $rel) as $k => $e) {
            if ($e === '') continue;
            $n++;
            $st = $state[$lang][$rel][$k] ?? null;
            if (!$st || $st['h'] !== srcHash($e) || $st['s'] === 'needs-fix') continue;
            if ($st['s'] !== 'reviewed' && reviewRequired($version, $k)) { $held++; continue; }
            $tr++;
            if ($st['s'] === 'reviewed') $rev++;
        }
    }
    $pct = intdiv(100 * $tr, max(1, $n)); $revPct = intdiv(100 * $rev, max(1, $n));
    $quality = $revPct >= 90 ? 'reviewed by native speakers'
        : ($revPct > 0 ? "$revPct% reviewed by native speakers, the rest machine-translated" : 'machine-translated, not yet reviewed');
    $heldNote = $held ? " $held security-relevant strings are shown in English until a native speaker has reviewed them." : '';

    $filesets = '';
    $zipName = "file_{$element}-{$pkgVersion}.zip";
    $zip = new ZipArchive();
    $zip->open("$out/$zipName", ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($targets as $area => $target) {
        $list = array_values(array_filter(sourceFiles($version), fn($r) => str_starts_with($r, "$area/")));
        if (!$list) continue;
        $filesets .= "\t\t<files folder=\"$area\" target=\"$target/$lang\">\n";
        foreach ($list as $rel) {
            $zip->addFromString($rel, $renderShipped($lang, $rel));
            $filesets .= "\t\t\t<filename>" . basename($rel) . "</filename>\n";
        }
        $filesets .= "\t\t</files>\n";
    }
    $desc = htmlspecialchars("$product language pack: $name ($lang). $pct% translated, $quality.$heldNote "
        . "Help improve it at $repoUrl", ENT_XML1);
    $manifest = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<extension type="file" method="upgrade">
	<name>$product language pack $lang</name>
	<element>$element</element>
	<author>Schultz IT Solutions and $product translators</author>
	<authorUrl>https://it-solutions.schultz.ch</authorUrl>
	<creationDate>$date</creationDate>
	<copyright>Copyright (C) Schultz IT Solutions</copyright>
	<license>GNU General Public License version 3 or later</license>
	<version>$pkgVersion</version>
	<description>$desc</description>
	<fileset>
$filesets	</fileset>
</extension>

XML;
    $zip->addFromString("file_$element.xml", $manifest);
    $zip->close();
    $built[] = [$element, $zipName];
    printf("%-6s %3d%% translated (%3d%% reviewed, %d held back)  %s\n", $lang, $pct, $revPct, $held, $zipName);
}

if (!isset($o['lang'])) {
    $files = implode("\n", array_map(fn($b) => "\t\t<file type=\"file\" id=\"{$b[0]}\">{$b[1]}</file>", $built));
    $manifest = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<extension type="package" method="upgrade">
	<name>$product language packs (all languages)</name>
	<packagename>{$slug}_languages</packagename>
	<author>Schultz IT Solutions and $product translators</author>
	<authorUrl>https://it-solutions.schultz.ch</authorUrl>
	<creationDate>$date</creationDate>
	<copyright>Copyright (C) Schultz IT Solutions</copyright>
	<license>GNU General Public License version 3 or later</license>
	<version>$pkgVersion</version>
	<description>All $product $version language packs. Translations are maintained at $repoUrl</description>
	<files>
$files
	</files>
</extension>

XML;
    $pkg = "pkg_{$slug}_languages-$pkgVersion.zip";
    $zip = new ZipArchive();
    $zip->open("$out/$pkg", ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($built as $b) $zip->addFile("$out/{$b[1]}", $b[1]);
    $zip->addFromString("pkg_{$slug}_languages.xml", $manifest);
    $zip->close();
    echo "package: $pkg\n";
}
