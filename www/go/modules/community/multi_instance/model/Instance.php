<?php
namespace go\modules\community\multi_instance\model;

use Exception;
use go\core\db\Criteria;
use go\core\fs\File;
use go\core\http\Client;
use go\core\http\Request;
use go\core\jmap\Entity;
use go\core\validate\ErrorCode;
use go\modules\community\multi_instance\Module;
use function GO;
use go\core\util\DateTime;

class Instance extends Entity {
	
	public $id;	
	public $hostname;	
	public $createdAt;
	
	/**
	 * Number of users
	 * 
	 * @var int
	 */
	public $userCount;
	
	/**
	 * Maximum amount of users
	 * 
	 * @var int
	 */
	public $usersMax;
	
	public $lastLogin;	
	public $adminDisplayName;	
	public $adminEmail; 	
	public $loginCount;	
	public $modifiedAt;

	public $version;
	
	public $enabled;
	
	/**
	 * Trails will be deactivated automatically after a configurable period.
	 * 
	 * @var boolean
	 */
	public $isTrial;
	
	/**
	 * Storage usage in bytes
	 * @var int
	 */
	public $storageUsage;
	
	
	/**
	 * Storage quota in bytes
	 * 
	 * @var int
	 */
	public $storageQuota;
	
	
	protected $welcomeMessage;

	protected static function defineMapping() {
		return parent::defineMapping()
						->addTable('multi_instance_instance');
	}

	protected static function textFilterColumns()
	{
		return ['hostname', 'adminEmail', 'adminDisplayName'];
	}

	protected static function defineFilters() {
		return parent::defineFilters()
			->add('enabled', function(Criteria $c, $value){
				$c->andWhere(['enabled' => $value]);
			})
			->add('isTrial', function(Criteria $c, $value) {
				$c->andWhere('isTrial', '=', $value);
			});
	}


	public function getMajorVersion() {
		if(!$this->version) {
			return null;
		}
		return substr($this->version, 0, strrpos($this->version, '.'));
	}

	
	
	protected function init() {
		parent::init();
		
		if(!$this->isNew()) {
			//update model from instance db once a day
			if(!isset($this->modifiedAt) || $this->modifiedAt <= new \DateTime("-10 minute")) {
				$this->getInstanceDbData();
				
				if($this->isModified() && !$this->save()) {
					throw new \Exception("Could not save instance data! ". var_export($this->getValidationErrors(), true));
				}
			}
		}
	}
	
	protected function internalValidate() {
		
		if($this->isNew()) {
			$this->hostname = trim(strtolower($this->hostname));

			if(!preg_match('/^[a-z0-9-_\.]*$/', $this->hostname)) {
				$this->setValidationError('hostname', ErrorCode::MALFORMED, 'The hostname was malformed');
			}

			if($this->getDbName() == GO()->getDatabase()->getName()) {
				$this->setValidationError('hostname', ErrorCode::UNIQUE, 'This hostname is not available (Database exists).');
			}

			if(\go\core\db\Utils::databaseExists($this->getDbName())) {
				$this->setValidationError('hostname', ErrorCode::UNIQUE, 'This hostname is not available (Database exists).');
			}

			//do get folder for compatibility with old config.php files
			if($this->isNew() && $this->getConfigFile()->getFolder()->exists()) {
				$this->setValidationError('hostname', ErrorCode::UNIQUE, 'This hostname is not available (config file exists).');
			}

			if($this->isNew() && $this->getDataFolder()->exists()) {
				$this->setValidationError('hostname', ErrorCode::UNIQUE, 'This hostname is not available (data folder exists).');
			}

			if(!$this->getConfigFile()->isWritable()) {
				$this->setValidationError('hostname', ErrorCode::FORBIDDEN, 'The configuration file is not writable');
			}

			if(!$this->getDataFolder()->isWritable()) {
				$this->setValidationError('hostname', ErrorCode::FORBIDDEN, 'The data folder is not writable');
			}

			if(!$this->getTempFolder()->isWritable()) {
				$this->setValidationError('hostname', ErrorCode::FORBIDDEN, 'The temporary files folder is not writable');
			}
		} else {
		
			if($this->isModified('hostname')) {
				$this->setValidationError('hostname', ErrorCode::FORBIDDEN, "You can't modify the hostname.");
			}
		}
		
		return parent::internalValidate();
	}
	
	/**
	 * Get configuration file
	 * @return File
	 */
	public function getConfigFile() {
		return new File('/etc/groupoffice/multi_instance/' . $this->hostname . '/config.php');
	}
	
	private function getDataFolder() {
		return GO()->getDataFolder()->getFolder('multi_instance/' . $this->hostname);
	}
	
	private function getTrashFolder() {
		return GO()->getDataFolder()->getFolder('multi_instance/_trash_')->create();
	}
	
	private function getTempFolder() {
		return GO()->getTmpFolder()->getFolder('multi_instance/' . $this->hostname);
	}
	
	private function getDbName() {
		return str_replace(['.','-'], '_', $this->hostname);
	}
	
	private function getDbUser() {
		return substr($this->getDbName(), 0, 16);
	}
	
	protected function internalSave() {		
		
		if(!parent::internalSave()) {
			return false;
		}
		
		if($this->isNew()) {		
			$this->createInstance();
		} 
		
		if($this->isModified(['storageQuota', 'userMax', 'enabled'])) {
			$config = $this->getInstanceConfig();
			$config['quota'] = $this->storageQuota / 1024;
			$config['max_users'] = $this->usersMax;
			$config['enabled'] = $this->enabled;
			$this->setInstanceConfig($config);
		}
		
		//$this->createWelcomeMessage();
		
		return true;	
	}
	
	
	public function onInstall() {
		$this->createWelcomeMessage();
		
		$this->copySystemSettings();		
		
		$this->save();
	}
	
	private function copySystemSettings() {
		$core = GO()->getSettings()->toArray();
		$groups = \go\core\model\Settings::get()->toArray();
		$users = \go\core\model\Settings::get()->toArray();
		
		$coreModuleId = (new \go\core\db\Query)
						->setDbConnection($this->getInstanceDbConnection())
						->selectSingleValue('id')
						->from('core_module')
						->where(['package'=>'core', 'name'=>'core'])->single();
		
		foreach($core as $name => $value) {
			if($name === "databaseVersion" || $name === "title" || $name === "URL") {
				continue;
			}
			
			$this->getInstanceDbConnection()
							->replace('core_setting', ['name' => $name, 'value' => $value, "moduleId" => $coreModuleId])->execute();
		}
	}	
	
	private function createWelcomeMessage() {
		
		if(isset($this->welcomeMessage)) {
			$this->getInstanceDbConnection()
							->insert("core_acl", [
									'ownedBy' => 1,
									'usedIn' => 'su_announcements.acl_id',
									'modifiedAt' => new \DateTime()
							])->execute();
			
			$aclId = $this->getInstanceDbConnection()->getPDO()->lastInsertId();
			
			$this->getInstanceDbConnection()
							->insert("core_acl_group", [
									'aclId' => $aclId,
									'groupId' => 1,
									'level' => 50
							])->execute();
			
			$this->getInstanceDbConnection()
							->insert("core_acl_group", [
									'aclId' => $aclId,
									'groupId' => 2,
									'level' => 10
							])->execute();
			
			$this->getInstanceDbConnection()
							->insert('su_announcements', [
									'user_id' => 1,
									'acl_id' => $aclId,
									'due_time' => 0,
									'ctime' => time(),
									'mtime' => time(),
									'title' => GO()->t("Welcome to Group-Office"),
									"content" => $this->welcomeMessage
							])->execute();
		}
		
		$this->welcomeMessage = null;
		
	}
	
	
	private function createInstance() {
		$dbName =  $this->getDbName();
		$dbUsername = $this->getDbUser();	
		$dbPassword = bin2hex(random_bytes(8));
		$dataFolder = $this->getDataFolder();
		$tmpFolder = $this->getTempFolder();	
		$configFile = $this->getConfigFile();
		$databaseCreated = $databaseUserCreated = false;
		try {			
			if(!$dataFolder->create()) {
				throw new Exception("Could not create data folder");
			}
			
			if(!$tmpFolder->create()) {
				throw new Exception("Could not create temporary files folder");
			}

		
			$this->createDatabase($dbName);
			$databaseCreated = true;
			$this->createDatabaseUser($dbName, $dbUsername, $dbPassword);
			$databaseUserCreated = true;
			
			if(!$configFile->putContents($this->createConfigFile($dbName, $dbUsername, $dbPassword, $tmpFolder->getPath(), $dataFolder->getPath()))) {
				throw new Exception("Could not write to config file");
			}
		} catch(\Exception $e) {
			
			//cleanup
			$tmpFolder->delete();
			$dataFolder->delete();
			$configFile->getFolder()->delete();
			if($databaseCreated) {
				$this->dropDatabase($dbName);
			}

			if($databaseUserCreated) {
				$this->dropDatabaseUser($dbUsername);
			}
			
			parent::internalDelete();
			
			throw $e;
		}
	}
	
	private function dropDatabase($dbName) {		
		return GO()->getDbConnection()->query("DROP DATABASE IF EXISTS `".$dbName."`");
	}
	
	private function createDatabase($dbName) {		
		return GO()->getDbConnection()->query("CREATE DATABASE IF NOT EXISTS `".$dbName."`");
	}
	
	private function dropDatabaseUser($dbUser) {
		GO()->getDbConnection()->query("DROP USER '" . $dbUser . "'@'%'");
	}
	
	private function createDatabaseUser($dbName, $dbUsername, $dbPassword) {
		$sql = "GRANT ALL PRIVILEGES ON `" . $dbName . "`.*	TO ".
								"'".$dbUsername."'@'%' ".
								"IDENTIFIED BY '" . $dbPassword . "' WITH GRANT OPTION";			

		GO()->getDbConnection()->query($sql);
		GO()->getDbConnection()->query('FLUSH PRIVILEGES');		
	}
	
	private function createConfigFile($dbName, $dbUsername, $dbPassword, $tmpPath, $dataPath) {
		
		$tpl = Module::getFolder()->getFile('config.php.tpl');
		
		$dsn = \go\core\db\Utils::parseDSN(GO()->getConfig()['core']['db']['dsn']);

		
		return str_replace([
				'{dbHost}',
				'{dbName}',
				'{dbUsername}',
				'{dbPassword}',
				'{tmpPath}',
				'{dataPath}',
				'{servermanager}',
		], [
				$dsn['options']['host'],
				$dbName,
				$dbUsername,
				$dbPassword,
				$tmpPath,
				$dataPath,
				GO()->findConfigFile()
		],
		$tpl->getContents());		
	}
	
	private $instanceDbConn;
	
	
	private $instanceConfig;
	private $globalConfig;
	
	private function getInstanceConfig() {
		if(!isset($this->instanceConfig)) {						
			include($this->getConfigFile()->getPath());
			$this->instanceConfig = $config;
		}		
		return $this->instanceConfig;
	}
	
	private function setInstanceConfig($config) {
		$this->getConfigFile()->putContents("<?php\n\$config = " . var_export($config, true) . ";\n");
		
		if(function_exists("opcache_invalidate")) {
			opcache_invalidate($this->getConfigFile()->getPath());
		}
		
		$this->instanceConfig = $config;
	}
	
	private function getGlobalConfig() {
		
		if(!isset($this->instanceConfig)) {			
			$globalConfigFile = "/etc/groupoffice/globalconfig.inc.php";
			if(file_exists($globalConfigFile)) {
				include("/etc/groupoffice/globalconfig.inc.php");
				$this->globalConfig	= $config ?? [];
			} else
			{
				$this->globalConfig	= [];
			}
		}
		
		return $this->globalConfig;
	}
	
	/**
	 * 
	 * @return \go\core\db\Connection
	 */
	private function getInstanceDbConnection() {
		if(!isset($this->instanceDbConn)) {		
			
			$config = $this->getInstanceConfig();
			
			$dsn = 'mysql:host=' . ($config['db_host'] ?? "localhost") . ';port=' . ($config['db_port'] ?? 3306) . ';dbname=' . $config['db_name'];
			$this->instanceDbConn = new \go\core\db\Connection($dsn, $config['db_user'], $config['db_pass']);
		}
		
		return $this->instanceDbConn;
	}
	

	public function createAccessToken() {
		$now = new DateTime();
		$expiresAt = new DateTime("+1 hour");
		
		$data = [
				"loginToken" => uniqid().bin2hex(random_bytes(16)),
				"accessToken" => uniqid().bin2hex(random_bytes(16)),
				"expiresAt" => $expiresAt,
				"userAgent" => "Multi Instance Module",
				"userId" => 1,
				"createdAt" => $now,
				"lastActiveAt" => $now,
				"remoteIpAddress" => $_SERVER['REMOTE_ADDR']
		];
		
		if(!$this->getInstanceDbConnection()->insert('core_auth_token', $data)->execute()) {
			throw new \Exception("Failed to create access token");
		}
		
		return $data['accessToken'];	
	}
	
	private function getInstanceDbData(){
		try {

			//Correct old bug
			$this->getInstanceDbConnection()->exec("DELETE FROM core_setting WHERE moduleId=0");

			$record = (new \go\core\db\Query())
						->setDbConnection($this->getInstanceDbConnection())
						->select('count(*) as userCount, max(lastLogin) as lastLogin, sum(loginCount) as loginCount')
						->from('core_user')
						->where('enabled', '=', true)
						->single();	
			
			$this->loginCount = (int) $record['loginCount'];
			$this->userCount = (int) $record['userCount'];
			$this->lastLogin = !empty($record['lastLogin']) ? new \go\core\util\DateTime($record['lastLogin']) : null;
			
			$record = (new \go\core\db\Query())
						->setDbConnection($this->getInstanceDbConnection())
						->select('displayName, email')
						->from('core_user')
						->where('id', '=', 1)
						->single();
			
			$this->adminDisplayName = $record['displayName'];
			$this->adminEmail = $record['email'];
			
			$this->storageUsage = (int) (new \go\core\db\Query())
						->setDbConnection($this->getInstanceDbConnection())
						->selectSingleValue('value')
						->from('go_settings')
						->where('name', '=', "file_storage_usage")
						->single();

			$this->version = (new \go\core\db\Query())
						->setDbConnection($this->getInstanceDbConnection())
						->selectSingleValue('value')
						->from('core_setting')
						->where('name', '=', "databaseVersion")
						->single();
			
			$config = array_merge($this->getGlobalConfig(), $this->getInstanceConfig());
			
			$this->storageQuota = isset($config['quota']) ? $config['quota'] * 1024 : null; 
			$this->enabled = $config['enabled'] ?? true;
		}
		catch(\Exception $e) {
			//ignore
		}
	}	
	
	
	/**
	 * Create a mysql dump of the installation database.
	 * 
	 * @param StringHelper $outputDir
	 * @param StringHelper $filename Optional filename. If omitted then $config['db_name'] will be used.
	 * @return boolean
	 * @throws Exception
	 */
	private function mysqldump(){
		
		$c = $this->getInstanceConfig();
		
		$file = $this->getDataFolder()->getFile('database.sql');
		$file->delete();
			
	
		$cmd = "mysqldump --force --opt --host=" . ($c['db_host'] ?? "localhost") . " --port=" . ($c['db_port'] ?? 3306) . " --user=" . $c['db_user'] . " --password=" . $c['db_pass'] . " " . $c['db_name'] . " > \"" . $file->getPath() . "\"";
		GO()->debug($cmd);
		exec($cmd, $output, $retVar);
		
		if($retVar != 0) {
			throw new Exception("Mysqldump error: " .$retVar ." : ". implode("\n", $output));
		}
		
		if(!$file->exists()) {
			throw new Exception("Could not create MySQL dump");
		}
		
		return true;
	}
	
	protected function internalDelete() {
		$this->getTempFolder()->delete();
		
		$this->mysqldump();
		
		$this->getConfigFile()->move($this->getDataFolder()->getFile('config.php'));
		$this->getConfigFile()->getFolder()->delete();

		$dest =	$this->getTrashFolder()->getFolder($this->getDataFolder()->getName());
		if($dest->exists()) {
			$dest = $dest->getParent()->getFolder($this->getDataFolder()->getName() . '-' . uniqid());
		}
		$this->getDataFolder()->move($dest);
		
		$this->dropDatabaseUser($this->getDbUser());
		$this->dropDatabase($this->getDbName());
		
		return parent::internalDelete();
	}
	
	
		
	public function setWelcomeMessage($html) {
		$this->welcomeMessage = $html;
	}


	public function upgrade() {
		$http = new Client();

		$proto = Request::get()->isHttps() ? 'https://' : 'http://';

		$http->setOption(CURLOPT_SSL_VERIFYHOST, false);
		$http->setOption(CURLOPT_SSL_VERIFYPEER, false);

		$response = $http->get($proto . $this->hostname . '/install/upgrade.php?confirmed=1&ignore=modules');

		//echo $response['body'];

		return $response['status'] == 200;
	}
}
