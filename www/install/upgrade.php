<?php
use go\core\ErrorHandler;
use go\core\db\Table;
use go\core\orm\Query;

ini_set('zlib.output_compression', 0);
ini_set('implicit_flush', 1);

try {
	
	require('../vendor/autoload.php');

	require("gotest.php");
	if(!systemIsOk()) {
		header("Location: test.php");
		exit();
	}
	
	require('header.php');
	
	GO()->getInstaller()->isValidDb();
	GO()->setCache(new \go\core\cache\None());	
	Table::destroyInstances();
	
	$unavailable = GO()->getInstaller()->getUnavailableModules();

	if (!isset($_GET['confirmed'])) {
	
		echo "<section><div class=\"card\"><h2>Backup before upgrade</h2><p>";
		echo "Please <b>BACKUP</b> your database and files before proceeding. You're database is going to be upgraded and all caches will be cleared.<br />This operation can only be undone by restoring a backup.<br />";
		
		echo 'More details about this upgrade can be found in the <a target="_blank" href="https://github.com/Intermesh/groupoffice/blob/master/CHANGELOG.md">change log</a>.<br /><br />';

		echo "Note: You can also upgrade on the command line by running: <br />

			<code>cli.php core/System/upgrade</code>

			";


		
		echo "</p></div>";
		echo '<a class="button" href="?confirmed=1">Upgrade database</a>';
		echo "</section>";
	} elseif (!isset($_GET['ignore']) && count($unavailable)) {
	
		echo "<section><div class=\"card\"><h2>Upgrading Group-Office</h2><pre>";

		echo "The following modules are not available because they're missing on disk\n"
		. "or you've got an <b>invalid or missing license file</b>: \n"
		. "<ul style=\"font-size:1.5em\"><li>" . implode("</li><li>", array_map(function($a){return ($a['package'] ?? "legacy") .'/'.$a['name'];}, $unavailable)) . "</li></ul>"
		. "Please install the license file(s) and refresh this page or disable these modules.\n"
		. "If you continue the incompatible modules will be disabled.\n\n";
		
		
		echo "</pre></div>";
		echo '<a class="button" href="?ignore=modules&confirmed=1">Disable &amp; Continue</a>';
		echo "</section>";
	} else
	{
			
		echo "<section><div class=\"card\"><h2>Upgrading Group-Office</h2><pre>";


		if(count($unavailable)) {
			
			$where = (new Query);
			foreach($unavailable as $m) {
				$where->orWhere($m);
			}
			$stmt = GO()->getDbConnection()->update("core_module", ['enabled' => false], $where);
			$stmt->execute();
		}	

		\GO::session()->runAsRoot();
	
		GO()->getInstaller()->upgrade();	

		echo "</pre></div>";

		echo '<a class="button" href="../">Continue</a>';

		if(GO()->getDebugger()->enabled) {
			echo "<div style=\"clear:both;margin-bottom:20px;\"></div><div class=\"card\"><h2>Debugger output</h2><pre>" ;
			GO()->getDebugger()->printEntries();
			echo "</pre></div>";
		}

		echo "</section>";

	} 
} catch (Exception $e) {
	echo "<b>Error:</b> ". ErrorHandler::logException($e)."\n\n";
	
	echo $e->getTraceAsString();
	
	echo "</pre></div>";
	
	if(GO()->getDebugger()->enabled) {
		echo "<div style=\"clear:both;margin-bottom:20px;\"></div><div class=\"card\"><h2>Debugger output</h2><pre>";
		
		GO()->getDebugger()->printEntries();
		
		echo "</pre></div>";
	}
	
	echo "</section>";
}

require('footer.php');