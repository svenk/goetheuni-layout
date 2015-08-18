<?php
/**
 * Server Side LESS compiler, initially written for PhysikOnline (POTT #641),
 * Using http://lessphp.gpeasy.com/.
 * Diese PHP-File gibt eine CSS-Datei aus.
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
 $url_root = '/customizing/goetheuni-layout/less';

 // kein Support mehr fuer weird @imports an dieser Stelle ($less_import_dir)

 // Soll Browser-Cache-Information verwendet werden (spart Bandbreite) oder
 // nicht? Default: false.
 $ignore_http_caching = false;

 // Ab hier das Programm
 $mtime_cache_file = @filemtime($cssfile);
 $mtime_test_files = array_map(
	function($x){return @filemtime($x);},
	$watch_files);
 $mtime_test_max = array_reduce($mtime_test_files, 'max');
 $cache_is_valid = $mtime_cache_file
			&& $mtime_test_max < $mtime_cache_file;
 $debug = isset($_GET["debug"]);
 $regenerate = !$cache_is_valid || $debug;

 header("Last-Modified: ".gmdate("D, d M Y H:i:s", $mtime_cache_file)." GMT");

 if($regenerate) {
    // LESS-Files erzeugen
    require "../externals/less.php/Less.php";
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
	header((isset($_SERVER['SERVER_PROTOCOL'])?$_SERVER['SERVER_PROTOCOL']:'HTTP') . ' 500 Internal Server Error', true, 500);
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

?>
/*!
 * Koeppel ~ITP CSS Code - using the PhysikOnline LESS compiler (POTT #641)
 * Generated from LESS files
 * --- DIESE DATEI NICHT VON HAND BEARBEITEN ---
 * Design by Sven Koeppel 2015
 *
 * Cache monitor: <?php echo implode(' ', $watch_files); ?> 
 * All included Files: <?php echo $allIncluded; ?> 
 * Arguments:  ?debug=true  - regenerate cache file
 * Generation Date: <?php print date('r', $mtime_cache_file); ?><?php if($regenerate) print " - Just regenerating"; ?> 
 **/
<?php if($regenerate) print $out; else readfile($cssfile); ?>

