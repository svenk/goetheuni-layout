<?php
/**
 * Server Side LESS compiler, initially written for PhysikOnline (POTT #641),
 * Using http://lessphp.gpeasy.com/.
 * Diese PHP-File gibt eine CSS-Datei aus.
 *
 * Usage:
 *   - without parameters: Tests if cache file is valid, else compiles
 *     the less files. Always gives out a CSS file (except in case of errors).
 *     Obeys clients cache.
 *   ?recompile=true	Fires a invalidation of the cache
 *   ?debug=true	Fires invalidation and disables minification of CSS
 *   ?status_only=true	Never gives out CSS. Good for eg. an AJAXy interface
 *
 **/
 header('Content-type: text/css');
 
 // Parameter:

 // Temporaere nur interne Cache-Datei auf dem Server
 $cssfile = 'goetheuni-layout.min.css';
 // Einstiegs-LESS-File, die kompiliert wird. Sie kann per @include
 // andere CSS/LESS-Files einbinden, diese Einbindungen werden aufgeloest.
 $lessfile = "../less/main.less";
 // Alle Dateien, die auf diese Globbing-Pattern passen, werden bei jedem
 // PHP-Aufruf auf Aenderungen ueberprueft.
 $watch_files = glob("../less/*");
 // URL root to prepend to any relative image or @import urls in the .less file.
 $url_root = '/~koeppel/src/lib/goetheuni-layout/less';
 // path to directory where less.php is installed.
 $lessphp_path = '../externals/less.php';

 // kein Support mehr fuer weird @imports an dieser Stelle ($less_import_dir)

 // Soll Browser-Cache-Information verwendet werden (spart Bandbreite) oder
 // nicht? Default: false.
 $ignore_http_caching = false;

 // Ab hier das Programm
 $mtime_cache_file = @filemtime($cssfile);
 $mtime_test_files = array_map(function($x){return @filemtime($x);}, $watch_files);
 $mtime_test_max = array_reduce($mtime_test_files, 'max');
 $cache_is_valid = $mtime_cache_file && $mtime_test_max < $mtime_cache_file;
 $debug = isset($_GET["debug"]);
 $fire_recompile = isset($_GET['recompile']);
 $regenerate = !$cache_is_valid || $debug || $fire_recompile;
 $status_only = isset($_GET['status-only']);

 header("Last-Modified: ".gmdate("D, d M Y H:i:s", $mtime_cache_file)." GMT");

 if($regenerate) {
    // LESS-Files erzeugen
    require "$lessphp_path/Less.php";
    $options = array();
    if(!$debug)
        $options['compress'] = true;
    if(isset($_GET['sourcemap']))
        $options['sourcemap'] = true;

    $parser = new Less_Parser($options);
    //$parser->SetImportDirs($less_import_dir); // <- ist jetzt eine Abbildung, deaktiviert.

    try {
        $parser->parseFile($lessfile, $url_root);
        $out = $parser->getCss();
    } catch(Exception $e) {
        $error = $e->getMessage();
	header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
	print "Less compilation error: ".$error;
	exit;
    }
    file_put_contents($cssfile, $out);
 } else {
    // Cache is valid!
    // HTTP Caching verwenden, wenn Client bereits neuste Version hat, nicht nochmal
    // uebertragen

    if(!$ignore_http_caching && @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $mtime_cache_file) {
        header("HTTP/1.1 304 Not Modified");
        // important - no more output!
	if($status_only) print "Cache file is valid";
        exit;
    } // else: Ausgaben machen, siehe unten:
 }

 // some helper for nicer output of included files:
 function getCommonPath($paths) {
	$lastOffset = 1;
	$common = '/';
	while (($index = strpos($paths[0], '/', $lastOffset)) !== FALSE) {
		$dirLen = $index - $lastOffset + 1;	// include /
		$dir = substr($paths[0], $lastOffset, $dirLen);
		foreach ($paths as $path) {
			if (substr($path, $lastOffset, $dirLen) != $dir)
				return $common;
		}
		$common .= $dir;
		$lastOffset = $index + 1;
	}
	return substr($common, 0, -1);
 }

 if($regenerate) {
	// chop absolute path to a common denominator
	$allIncluded = $parser->allParsedFiles();
	$prefix = getCommonPath($allIncluded);
	$allIncluded = array_map(function($x) use ($prefix) { return substr($x, strlen($prefix)); }, $allIncluded);
	$allIncluded = implode(' ', $allIncluded);
 } else {
	$allIncluded = 'Can only say when regenerating.';
 }

 if($status_only) {
	if($regenerated) {
		print "Regenerated CSS file.";
	} else {
		print "Did not regenerate CSS file. Would just output the cache file.";
	}
	exit;
 }

?>
/*!
 * Goethe Universität Frankfurt as Bootstrap layout
 * https://github.com/svenk/goetheuni-layout - by Sven Köppel 2015, public domain
 *
 * Generated from LESS files by the bundled LESS compiler
 * --- DO NOT EDIT THIS FILE BY HAND ---
 *
 * Cache monitor: <?php echo implode(' ', $watch_files); ?> 
 * All included Files: <?php echo $allIncluded; ?> 
 * Arguments:  ?debug=true  - regenerate cache file
 * Generation Date: <?php print date('r', $mtime_cache_file); ?><?php if($regenerate) print " - Just regenerating"; ?> 
 **/
<?php if($regenerate) print $out; else readfile($cssfile); ?>

