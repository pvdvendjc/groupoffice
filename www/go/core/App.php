<?php

namespace go\core {

use Exception;
use GO;
use GO\Base\Observable;
use go\core\auth\State as AuthState;
use go\core\cache\CacheInterface;
use go\core\cache\Disk;
use go\core\db\Connection;
use go\core\db\Database;
use go\core\db\Query;
use go\core\db\Table;
    use go\core\event\EventEmitterTrait;
    use go\core\event\Listeners;
use go\core\exception\ConfigurationException;
use go\core\fs\Folder;
use go\core\jmap\State;
use go\core\mail\Mailer;
use go\core\util\Lock;
use go\core\webclient\Extjs3;
use go\core\model\Settings;
use const GO_CONFIG_FILE;

	/**
	 * Application class.
	 * 
	 * A singleton instance that can be accessed from anywhere in the framework with:
	 * 
	 * ```
	 * App::get()
	 * ```
	 * 
	 * 
	 * 
	 */
	class App extends Module {
		
		use SingletonTrait;

		use EventEmitterTrait;


		/**
		 * Fires when the application is loaded in the <head></head> section of the webclient.
		 * Can also be used to adjust the Content Security Policy
		 */
		const EVENT_HEAD = 'head';

		/**
		 *
		 * @var Connection
		 */
		private $dbConnection;

		/**
		 *
		 * @var ErrorHandler 
		 */
		private $errorHandler;

		/**
		 * @var Mailer
		 */
		private $mailer;

		/**
		 *
		 * @var CacheInterface 
		 */
		private $cache;
		
		private $version;

		protected function __construct() {
			date_default_timezone_set("UTC");

			$this->errorHandler = new ErrorHandler();
			$this->initCompatibility();
		}
		
		/**
		 * Required for app being a go\core extend
		 *
		 * @return string
		 */
		public function getAuthor() {
			return "Intermesh BV";
		}

		/**
		 * Required for app being a go\core extend
		 *
		 * @return string
		 */
		public static function getName() {
			return "core";
		}

		/**
		 * Required for app being a go\core extend
		 *
		 * @return string
		 */
		public static function getPackage() {
			return "core";
		}

		/**
		 * Get version number
		 *
		 * @return string eg. 6.4.1
		 */
		public function getVersion() {
			if(!isset($this->version)) {
				$this->version = require(Environment::get()->getInstallFolder()->getPath() . '/version.php');
			}
			return $this->version;
		}

		/**
		 * Major version
		 *
		 * @return string eg. 6.4
		 */
		public function getMajorVersion() {
			
			return substr($this->getVersion(), 0, strrpos($this->getVersion(), '.') );
		}

		private function initCompatibility() {
			require(Environment::get()->getInstallPath() . "/go/GO.php");
			spl_autoload_register(array('GO', 'autoload'));
		}

		/**
		 * The mail object
		 * ```
		 * $message = App::getMailer()->compose();
		 * $message->setTo()->setFrom()->setBody()->send();
		 * ```
		 * @return Mailer
		 */
		public function getMailer() {
			if (!isset($this->mailer)) {
				$this->mailer = new Mailer();
			}
			return $this->mailer;
		}

		/**
		 * Get the installer object
		 * 
		 * @return Installer
		 */
		public function getInstaller() {
			if (!isset($this->installer)) {
				$this->installer = new Installer();
			}
			return $this->installer;
		}

		/**
		 * Get the data folder
		 * 
		 * @return Folder
		 */
		public function getDataFolder() {
			return new Folder($this->getConfig()['core']['general']['dataPath']);
		}
		
		/**
		 * Get total space of the data folder in bytes
		 * 
		 * @return float
		 */
		public function getStorageQuota() {
			$quota = $this->getConfig()['core']['limits']['storageQuota'];
			if(empty($quota)) {
				$quota = disk_total_space($this->getConfig()['core']['general']['dataPath']);
			}
			
			return $quota;
		}		
		
		/**
		 * Get free space in bytes
		 * 
		 * @return float
		 */
		public function getStorageFreeSpace() {
			$quota = $this->getConfig()['core']['limits']['storageQuota'];
			if(empty($quota)) {
				return disk_free_space($this->getConfig()['core']['general']['dataPath']);
			} else
			{
				 $usage = \GO::config()->get_setting('file_storage_usage');				 
				 return $quota - $usage;
			}
		}

		/**
		 * Get the temporary files folder
		 * 
		 * @return Folder
		 */
		public function getTmpFolder() {
			return new Folder($this->getConfig()['core']['general']['tmpPath']);
		}

		private $config;
		
		/**
		 * Load configuration
		 * 
		 * ```
		 * "general" => [
		 * 	  "dataPath" => "/foo/bar"
		 * 	],
		 * 
		 * "db" => [
		 * 	  "dsn" => 'mysql:host=localhost;dbname=groupoffice,
		 * 	  "username" => "user",
		 * 	  "password" => "secret"
		 *   ]
		 * "limits" => [
		 * 		"maxUsers" => 0,
		 * 		"storageQuota" => 0,
		 * 		"allowedModules" => ""
		 * 	 ]
		 * ]
		 * 
		 * ```
		 * 
		 * @param array $config
		 * @return $this;
		 */
		public function setConfig(array $config) {
			$this->config = $config;
			
			return $this;
		}
		
		private function getGlobalConfig() {
			try {
				$globalConfigFile = '/etc/groupoffice/globalconfig.inc.php';
				if (file_exists($globalConfigFile)) {
					require($globalConfigFile);
				}
			}catch(\Exception $e) {
				//openbasedir might complain here. Ignore.
				
			}
			
			return $config ?? [];
		}
		
		private function getInstanceConfig() {
			$configFile = $this->findConfigFile();
			if(!$configFile) {
				
				$host = isset($_SERVER['HTTP_HOST']) ? explode(':', $_SERVER['HTTP_HOST'])[0] : '<HOSTNAME>';
				
				$msg = "No config.php was found. Possible locations: \n\n".
								"/etc/groupoffice/multi_instance/" .$host . "/config.php\n\n".
								 dirname(dirname(__DIR__)) . "/config.php\n\n".
								"/etc/groupoffice/config.php";
				
				throw new Exception($msg);
			}
			
			require($configFile);
			
			if(!isset($config)) {
				throw new ConfigurationException();
			}
			
			return $config;
		}
		

		/**
		 * Get the configuration data
		 * 
		 * ```
		 * 
		  "general" => [
		  "dataPath" => "/foo/bar"
		  ],
		  "db" => [
		  "dsn" => 'mysql:host=localhost;dbname=groupoffice,
		  "username" => "user",
		  "password" => "secret"
		  ]
		  ]
		 * ```
		 * @return array
		 */
		public function getConfig() {

			if (isset($this->config)) {
				return $this->config;
			}
			
			$config = array_merge($this->getGlobalConfig(), $this->getInstanceConfig());
			
			
			
			$this->config = (new util\ArrayObject([
					"core" => [
							"general" => [
									"dataPath" => $config['file_storage_path'] ?? '/home/groupoffice', //TODO default should be /var/lib/groupoffice
									"tmpPath" => $config['tmpdir'] ?? sys_get_temp_dir() . '/groupoffice',
									"debug" => $config['debug'] ?? null,
									
									"servermanager" => $config['servermanager'] ?? false,

									"sseEnabled" => $config['sseEnabled'] ?? true
							],
							"db" => [
									"host" => ($config['db_host'] ?? "localhost"),
									"port" => $config['db_port'] ?? 3306,
									"name" => $config['db_name'],
									"dsn" => 'mysql:host=' . ($config['db_host'] ?? "localhost") . ';port=' . ($config['db_port'] ?? 3306) . ';dbname=' . ($config['db_name'] ?? "groupoffice-com"),
									"username" => $config['db_user'] ?? "groupoffice",
									"password" => $config['db_pass'] ?? ""
							],
							"limits" => [
									"maxUsers" => $config['max_users'] ?? 0,
									"storageQuota" => $config['quota'] ?? 0,
									"allowedModules" => $config['allowed_modules'] ?? ""
							],
							"branding" => [
								"name" => $config['product_name'] ?? "GroupOffice"
							],
					],
					
//					"package" => [
//							"name" => [
//									"foo" => 'bar'
//							]
//					]
			]))->mergeRecursive($config)->getArray();
			
			if(!isset($this->config['core']['general']['cache'])) {
				if(cache\Apcu::isSupported()) {
					$this->config['core']['general']['cache'] = cache\Apcu::class;
				} else
				{
					$this->config['core']['general']['cache'] = cache\Disk::class;
				}
			}
			
			return $this->config;
		}

		/**
		 * Get the database connection
		 * 
		 * @return Connection
		 */
		public function getDbConnection() {

			if (!isset($this->dbConnection)) {
				$db = $this->getConfig()['core']['db'];
				$this->dbConnection = new Connection(
								$db['dsn'], $db['username'], $db['password']
				);
			}
			return $this->dbConnection;
		}

		/**
		 *
		 * @var Database
		 */
		private $database;

		/**
		 * Get the database object
		 * 
		 * @return Database
		 */
		public function getDatabase() {
			if (!isset($this->database)) {
				$this->database = new Database();
			}

			return $this->database;
		}

		/**
		 *
		 * @var Installer
		 */
		private $installer;

		/**
		 * Get a simple key value caching object
		 * 
		 * @return Disk
		 */
		public function getCache() {
			if (!isset($this->cache)) {
				$cls = $this->getConfig()['core']['general']['cache'];
				$this->cache = new $cls;
			}
			return $this->cache;
		}
		
		
		/**
		 * Get a module
		 *
		 * return the module if it's installed and available.
		 *
		 * @param string $package Set to null for legacy modules
		 * @param string $name
		 * @return \go\core\model\Module
		 */
		public function getModule($package, $name) {
			$model = \go\core\model\Module::find()->where(['package' => $package, 'name' => $name, 'enabled' => true])->single();
			if(!$model || !$model->isAvailable()) {
				return false;
			}
			
			return $model;
		}
		
		/**
		 * Set the cache provider
		 * 
		 * @param CacheInterface $cache
		 * @return $this
		 */
		public function setCache(CacheInterface $cache) {
			$this->cache = $cache;
			
			return $this;
		}
		
		private $rebuildCacheOnDestruct = false;
		
		public function rebuildCache($onDestruct = false) {
			
			if($onDestruct) {				
				$this->rebuildCacheOnDestruct = $onDestruct;
			}
			
			$lock = new Lock("rebuildCache");
			if($lock->lock()) {
				\GO::clearCache(); //legacy

				GO()->getCache()->flush(false);
				Table::destroyInstances();

				$webclient = Extjs3::get();
				$webclient->flushCache();

				Observable::cacheListeners();

				Listeners::get()->init();
			}
		}
		
		public function __destruct() {
			if($this->rebuildCacheOnDestruct) {
				$this->rebuildCache();
			}
		}

		/**
		 * Get a simple key value caching object
		 * 
		 * @return Debugger
		 */
		public function getDebugger() {
			if (!isset($this->debugger)) {
				$this->debugger = new Debugger();
			}

			return $this->debugger;
		}

		/**
		 * Add debug output
		 * 
		 * {@see Debugger::debug()}
		 * 
		 * @todo calls that happen in jsonSerialize() are never sent to output
		 * 
		 * @param string|callable|array|object $msg
		 */
		public function debug($msg, $traceBackSteps = 0) {
			$this->getDebugger()->log($msg, $traceBackSteps);
		}
		
		public function log($msg, $traceBackSteps = 0) {
			$this->getDebugger()->log($msg, $traceBackSteps);
		}
		
		public function warn($msg, $traceBackSteps = 0) {
			$this->getDebugger()->warn($msg, $traceBackSteps);
		}
		
		public function error($msg, $traceBackSteps = 0) {
			$this->getDebugger()->error($msg, $traceBackSteps);
		}
		
		public function info($msg, $traceBackSteps = 0) {
			$this->getDebugger()->info($msg, $traceBackSteps);
		}

		private $authState;

		/**
		 * Set the authentication state
		 * 
		 * @param AuthState $authState
		 * @return $this
		 */
		public function setAuthState(AuthState $authState) {
			$this->authState = $authState;
			
			return $this;
		}

		/**
		 * Get the authentication handler
		 * 
		 * @return State
		 */
		public function getAuthState() {
			return $this->authState;
		}
		
		/**
		 * Get the server environment
		 *
		 * @return Environment
		 */
		public function getEnvironment() {
			return Environment::get();
		}

		/**
		 * Get the authenticated user ID
		 * 
		 * If you need to get the full user use:
		 *
		 * ```
		 * GO()->getAuthState()->getUser();
		 * ```
		 * @return int
		 */
		public function getUserId() {
			if ($this->getAuthState() instanceof AuthState) {
				return $this->authState->getUserId();
			}
			return null;
		}

		/**
		 * Get the application settings
		 * 
		 * @return Settings
		 */
		public function getSettings() {
			return Settings::get();
		}

		/**
		 * Translates a language variable name into the local language.
		 * 
		 * @param String $str String to translate
		 * @param String $module Name of the module to find the translation
		 * @param String $package Only applies if module is set to 'base'
		 */
		public function t($str, $package = 'core', $module = 'core') {
			return $this->getLanguage()->t($str, $package, $module);
		}
		
		private $language;
		
		/**
		 * 
		 * @return Language
		 */
		public function getLanguage() {
			if(!isset($this->language)) {
				$this->language = new Language();
			}
			
			return $this->language;
		}

		/**
		 * Find the config.php file location.
		 *
		 * It will search for:
		 *
		 * - 'GO_CONFIG_FILE' constant or environment variable ($_SERVER['GO_CONFIG_FILE']).
		 * - /etc/groupoffice/multi_instance/<HOSTNAME>/config.php
		 * - <GROUPOFFICEDIR>/config.php
		 * - /etc/groupoffice/config.php
		 *
		 * @param string $name
		 * @return boolean|string
		 */
		public static function findConfigFile($name = 'config.php') {
			
			if(defined("GO_CONFIG_FILE")) {
				return GO_CONFIG_FILE;
			}
			
			//environment variable
			if(isset($_SERVER['GO_CONFIG_FILE'])) {
				return $_SERVER['GO_CONFIG_FILE'];
			}

			if (!empty($_SERVER['HTTP_HOST'])) {
				$workingFile = '/etc/groupoffice/multi_instance/' . explode(':', $_SERVER['HTTP_HOST'])[0] . '/' . $name;
				try {
					if (file_exists($workingFile)) {
						return $workingFile;
					}
				}
				catch(\Exception $e) {
					//ignore open_basedir error
				}
			}
			
			$workingFile = dirname(dirname(__DIR__)) . '/' . $name;
			try {
				if (file_exists($workingFile)) {
					return $workingFile;
				}
			}
			catch(\Exception $e) {
				//ignore open_basedir error
			}

			$workingFile = '/etc/groupoffice/' . $name;
			try {
				if (file_exists($workingFile)) {
					return $workingFile;
				}
			}
			catch(\Exception $e) {
				//ignore open_basedir error
			}
			
			return false;
		}
		
		/**
		 * Resets all entity state so all clients must resync data.
		 *
		 * @todo resync per entity
		 */
		public function resetSyncState() {
			//reset all mod seqs
			GO()->getDbConnection()->update('core_entity', ['highestModSeq' => 0])->execute();
			GO()->getDbConnection()->exec("TRUNCATE TABLE core_change");
			GO()->getDbConnection()->exec("TRUNCATE TABLE core_acl_group_changes");
			GO()->getDbConnection()->insert('core_acl_group_changes', (new Query())->select("null, aclId, groupId, '0', null")->from("core_acl_group"))->execute();
		}

		/**
		 * Download method for module icons
		 *
		 * /api/download.php?blob=core/moduleIcon/community/addressbook
		 */
		public function downloadModuleIcon($package, $name) {

			if($package == "legacy") {
				$file = GO()->getEnvironment()->getInstallFolder()->getFile('modules/' . $name .'/themes/Default/images/'.$name.'.png');
				if(!$file->exists()) {
					$file = GO()->getEnvironment()->getInstallFolder()->getFile('modules/' . $name .'/views/Extjs3/themes/Default/images/'.$name.'.png');
				}

				if(!$file->exists()) {
					$file = GO()->getEnvironment()->getInstallFolder()->getFile('modules/' . $name .'/themes/Default/'.$name.'.png');
				}

				

			} else {
				$file = GO()->getEnvironment()->getInstallFolder()->getFile('go/modules/' . $package . '/' . $name .'/icon.png');
			}

			if(!$file->exists()) {
				$file = GO()->getEnvironment()->getInstallFolder()->getFile('views/Extjs3/themes/Paper/img/default-avatar.svg');
			}
			$file->output(true, true, ['Content-Disposition' => 'inline; filename="module.svg"']);
		}
	}
}

namespace {

	use go\core\App;
	/**
	 * @return go\core\App
	 */
	function GO() {
		return App::get();
	}

}
