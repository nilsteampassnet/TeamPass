<?php
header ( "Content-type: text/html; charset==utf-8" );
$path_to_poeditor = "./poeditor/";
// $path_to_languages = "../includes/language/";
$path_to_languages = "./";
function prepareFile($fichier, $path_to_languages, $path_to_poeditor) {
	echo "----<br>Fichier traité : " . $fichier . "<br>";
	if ($fichier != "Teampass_English.php") {
		include "english.php";
	}
	
	include "../includes/include.php";
	$fp = fopen ( $path_to_languages . strtolower ( substr ( $fichier, 9 ) ), "w" );
	fputs ( $fp, "<?php 
/**
 *
 * @file          " . strtolower ( substr ( $fichier, 9 ) ) . "
 * @author        Nils Laumaillé
 * @version       " . $k ['version'] . "
 * @copyright     " . str_replace ( " &copy;", "(c)", $k ['copyright'] ) . " Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
global \$LANG;
\$LANG = array (" );
	$nb = 0;
	$lines = file ( $path_to_poeditor . $fichier );
	foreach ( $lines as $linenumber => $linecontent ) {
		if (strpos ( $linecontent, "'term' => " ) > 0) {
			$term = substr ( $linecontent, 15, strlen ( $linecontent ) - 18 );
		}
		if (strpos ( $linecontent, "'definition' => " ) > 0) {
			if (trim ( $linecontent ) == "'definition' => NULL,") {
				// rechercher la phrase en Anglais
				$definition = addslashes ( $LANG [$term] );
			} else {
				$definition = substr ( $linecontent, 21, strlen ( $linecontent ) - 24 );
			}
		}
		
		if (! empty ( $term ) && ! empty ( $definition )) {
			fputs ( $fp, "
    '" . $term . "' => '" . $definition . "'," );
			$term = "";
			$definition = "";
			$nb ++;
		}
	}
	fputs ( $fp, "
    '' => ''
);" );
	fclose ( $fp );
	echo "&nbsp;&nbsp;&nbsp;> " . $nb . " terms.<br><br>";
}

echo '<html>
    <body>
    <form method="POST">
        <input type="submit" value="LANGUES" id="lang" name="lang" />
        <br /><br />
        <input type="text" name="new_version" /> <input type="submit" value="CHANGE VERSION" id="version" name="version" />
    </form>
    </body>
    </html>';

if (isset ( $_POST ['lang'] )) {
	if ($dossier = opendir ( $path_to_poeditor )) {
		// traiter le cas English
		prepareFile ( "Teampass_English.php", $path_to_languages, $path_to_poeditor );
		
		// faire les autres fichiers
		while ( false !== ($fichier = readdir ( $dossier )) ) {
			if (substr ( $fichier, strlen ( $fichier ) - 4 ) == ".php" && $fichier != "Teampass_English.php") {
				prepareFile ( $fichier, $path_to_languages, $path_to_poeditor );
			}
		}
	} else {
		echo "Pas de répertoire '" . $path_to_poeditor . "'";
	}
} else if (isset ( $_POST ['version'] ) && isset ( $_POST ['new_version'] )) {
	$di = new RecursiveDirectoryIterator ( "../", RecursiveDirectoryIterator::SKIP_DOTS );
	$it = new RecursiveIteratorIterator ( $di );
	foreach ( $it as $file ) {
		if (pathinfo ( $file, PATHINFO_EXTENSION ) == "php" && strpos ( pathinfo ( $file, PATHINFO_DIRNAME ), "_utils" ) == false) {
			// echo $file, PHP_EOL;
			$handle = fopen ( $file, "r" );
			if ($handle) {
				while ( ! feof ( $handle ) ) {
					$buffer = fgets ( $handle );
					if (strpos ( $buffer, " * @version       " ) > 0) {
						
						echo $file . " " . pathinfo ( $file, PATHINFO_DIRNAME ) . "<br>";
						break;
					}
				}
			}
			/*
			 * $data = file($file);
			 * $searchId = ' * @version';
			 * $c = count($data);
			 * for($i = 0; $i < $c; $i++) {
			 * if(strpos($data[$i], $searchId) == 0) {
			 * unset($data[$i]);
			 * break;
			 * }
			 * }
			 * file_put_contents($file, " * @version 2.1.23");
			 * fclose($file);
			 * break;
			 */
		}
	}
}

?>