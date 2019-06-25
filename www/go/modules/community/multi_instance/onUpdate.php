<?php
/**
 * Created by PhpStorm.
 * User: pieter
 * Date: 30-1-19
 * Time: 15:50
 */

//TODO this should be a command line controller instead.

use go\core\App;
use go\core\cli\State;
use go\modules\community\multi_instance\model\Instance;

if(!empty($argv[1])) {
	define('GO_CONFIG_FILE', $argv[1]);
}
chdir(__DIR__);
require("../../../../vendor/autoload.php");

//Create the app with the database connection
App::get()->setAuthState(new State());

if(!\go\core\Environment::get()->isCli()) {
	
	return;
}

$instances = Instance::find()->where('enabled','=',1);

foreach ($instances as $instance) {
	$ch = curl_init($instance->hostname . '/install/upgrade.php');
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	
	$result = curl_exec($ch);
	
	var_dump($result);
	
}