<?php
namespace go\core\model;

use Exception;
use GO;
use go\core;
use go\core\http\Request;
use go\core\util\Crypt;
use go\modules\community\addressbook\model\AddressBook;

class Settings extends core\Settings {
	
	protected function __construct() {
		parent::__construct();
		
		$save = false;
		
		if(!isset($this->URL)) {
			$this->URL = $this->detectURL();	
			$save = true;
		}
		
		if(!isset($this->language)) {
			$this->language = $this->getDefaultLanguage();
			$save = true;
		}
		
		if($save) {
			try {
				$this->save();
			}catch(Exception $e) {
				
				//ignore error on install becasuse core module is not there yet
				if(!core\Installer::isInProgress()) {
					throw $e;
				}
			}
		}
	}
	
	protected function getModuleName() {
		return "core";
	}
	
	protected function getModulePackageName() {
		return "core";
	}
	
	private function getDefaultLanguage() {		
		//can't use Language here because an infite loop will occur as it depends on this model.
		if(isset($_GET['SET_LANGUAGE']) && $this->hasLanguage($_GET['SET_LANGUAGE'])) {
			return $_GET['SET_LANGUAGE'];
		}
		
		$browserLanguages= Request::get()->getAcceptLanguages();
		foreach($browserLanguages as $lang){
			$lang = str_replace('-','_',explode(';', $lang)[0]);
			if(core\Environment::get()->getInstallFolder()->getFile('go/modules/core/language/'.$lang.'.php')->exists()){
				return $lang;
			}
		}
		
		return "en";
	}
	
	
	/**
	 * Auto detects URL to Group-Office if we're running in a webserver
	 * 
	 * @return string
	 */
	private function detectURL() {
		
		if(!isset($_SERVER['REQUEST_URI'])) {
			return null;
		}		
		
		$path = $_SERVER['REQUEST_URI'];

		$scriptName = basename($_SERVER['SCRIPT_NAME']);
		$lastSlash = strrpos($path, $scriptName);
		if($lastSlash !== false) {
			$path = substr($path, 0, $lastSlash);
		}
		//replace double slashes as they also resolve
		$path = preg_replace('/\/+/', '/', $path);
		
			//trim install folder
		if(substr($path, -9) == '/install/') {
			$path = substr($path, 0, -8);
		}		
		
		$https = (isset($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] == "on" || $_SERVER["HTTPS"] == "1")) || !empty($_SERVER["HTTP_X_SSL_REQUEST"]) || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on');
		$protocol = $https ? 'https://' : 'http://';
		
		$url = $protocol . $_SERVER['HTTP_HOST'] . $path;
		
		return $url;
	}

	const SMTP_ENCRYPTION_TLS = 'tls';
	const SMTP_ENCRYPTION_SSL = 'ssl';
	
	/**
	 * System default language ISO code
	 * 
	 * @var string  eg. "en"
	 */
	public $language;
	
	/**
	 * The title of the Group-Office environment
	 * 
	 * @var string
	 */
	public $title = 'Group-Office';
	
	
	/**
	 * The e-mail address for sending out system messages.
	 * 
	 * @var string
	 */
	public $systemEmail = 'admin@intermesh.dev';
	
	
	/**
	 * SMTP host name
	 * 
	 * @var string
	 */
	public $smtpHost = 'localhost';
	
	/**
	 * SMTP port
	 * 
	 * @var string
	 */
	public $smtpPort = 587;
	
	/**
	 * SMTP username
	 * @var string
	 */
	public $smtpUsername = null;
	
	/**
	 * SMTP Password
	 * 
	 * @var string
	 */
	protected $smtpPassword = null;
	
	
	public function getSmtpPassword() {
		return Crypt::decrypt($this->smtpPassword);
	}
	
	public function setSmtpPassword($value) {
		$this->smtpPassword = Crypt::encrypt($value);
	}
	
	
	protected $locale;
	
	/**
	 * Get locale for the system. We need a UTF8 locale so command line functions
	 * work with UTF8.
	 * 
	 * @return string
	 */
	public function getLocale() {

		if(GO()->getInstaller()->isInProgress()) {
			return 'C.UTF-8';
		}
		
		if(isset($this->locale)) {
			return $this->locale;
		}
		
		try {
			exec('locale -a', $output);

			if(isset($output) && is_array($output)){
				foreach($output as $locale){
					if(stripos($locale,'utf')!==false){
						$this->locale = $locale;						
						$this->save();						
						return $this->locale;
					}
				}
			}
		} catch(Exception $e) {
			GO()->debug("Could not determine locale");
		}

		//This locale is often installed so try to fallback on C.UTF8
		$this->locale = "C.UTF8";
		$this->save();		
		
		return $this->locale;
	}
	
	public function setLocale($locale) {
		$this->locale = $locale;
	}

	/**
	 * Encryption to use for SMTP
	 * @var string|bool
	 */
	public $smtpEncryption = self::SMTP_ENCRYPTION_TLS;
	
	/**
	 * Set to false to ignore certificate errors. 
	 * 
	 * @var boolean
	 */
	public $smtpEncryptionVerifyCertificate = true;
	
	/**
	 * If set then all system notifications go to this email address
	 * 
	 * @var string 
	 */
	public $debugEmail = null;
	
	
	/**
	 * When maintenance mode is enabled, only admin users can login.
	 * @var boolean 
	 */
	public $maintenanceMode = false;
	
	
	/**
	 * Enable HTML message that will show on the login screen.
	 * 
	 * @var string 
	 */
	public $loginMessageEnabled = false;
	
	/**
	 * HTML message that will show on the login screen.
	 * 
	 * @var string 
	 */
	public $loginMessage = null;
	
	
	/**
	 * Minimum password length
	 * 
	 * @var int
	 */
	public $passwordMinLength = 6;
	
	
	/**
	 * Default domain name to append to username for authentication
	 * 
	 * @var string
	 */
	public $defaultAuthenticationDomain;
	
	
	/**
	 * The full URL to Group-Office. With trailing /.
	 * 
	 * eg. https://my.groupoffice.net/
	 * 
	 * @var string 
	 */
	public $URL;


	/**
	 * Keep log in core_change for this number of days.
	 * 
	 * When a client has not logged in for this period the sync data will be deleted and resynchronized.
	 * 
	 * @var int
	 */
	public $syncChangesMaxAge = 90;
	
	/**
	 * This variable is checked against the code version.
	 * If it doesn't match /install/upgrade.php will be executed.
	 * 
	 * @var string
	 */
	public $databaseVersion;
	
	
	/**
	 * Primary color in html notation 000000;
	 * 
	 * @var string
	 */
	public $primaryColor;
	
	/**
	 * Blob ID for the logo
	 * 
	 * @var string
	 */
	public $logoId;
	
	
	/**
	 * Get's the transparent color based on the primary color.
	 * 
	 * @return string
	 */
	public function getPrimaryColorTransparent() {
		list($r, $g, $b) = sscanf($this->primaryColor, "%02x%02x%02x");
		
		return "rgba($r, $g, $b, .16)";
	}
	
	/**
	 * Default time zone for users
	 * 
	 * @var string
	 */
	public $defaultTimezone = "Europe/Amsterdam";
	
	/**
	 * Default date format for users
	 * 
	 * @link https://secure.php.net/manual/en/function.date.php
	 * @var string
	 */
	public $defaultDateFormat = "d-m-Y";
	
	/**
	 * Default time format for users
	 * 
	 * @link https://secure.php.net/manual/en/function.date.php
	 * @var string 
	 */
	public $defaultTimeFormat = "G:i";
	
	/**
	 * Default currency
	 * @var string
	 */
	public $defaultCurrency = "€";
	
	/**
	 * Default first week day
	 * 
	 * 0 = sunday
	 * 1 = monday
	 * 
	 * @var int 
	 */
	public $defaultFirstWeekday = 1;
	
	
	/**
	 * The default address book for new users
	 * @var int 
	 */
	protected $userAddressBookId = null;
	
	/**
	 * @return AddressBook
	 */
	public function getUserAddressBook() {
		if(!Module::findByName('community', 'addressbook')) {
			return null;
		}
		
		if(isset($this->userAddressBookId)) {
			$addressBook = AddressBook::findById($this->userAddressBookId);
		} else{
			$addressBook = false;
		}

		if(!$addressBook) {
			$addressBook = new AddressBook();	
			$addressBook->name = GO()->t("Users");
			if(!$addressBook->save()) {
				throw new \Exception("Could not save address book");
			}
			$this->userAddressBookId = $addressBook->id;
			if(!$this->save()) {
				throw new \Exception("Could not save core settings");
			}
		}

		return $addressBook;		
	}
	
	public function setUserAddressBookId($id) {
		$this->userAddressBookId = $id;
	}
	
	
	/**
	 * Default list separator for import and export
	 * 
	 * @var string
	 */
	public $defaultListSeparator = ';';
	
	/**
	 * Default text separator for import and export
	 * 
	 * @var string
	 */
	public $defaultTextSeparator = '"';
	
	/**
	 * Default thousands separator for numbers
	 * @var string
	 */
	public $defaultThousandSeparator = '.';
	
	/**
	 * Default decimal separator for numbers
	 * 
	 * @var string
	 */
	public $defaultDecimalSeparator = ',';	
	
	/**
	 * Default setting for users to have short date and times in lists.
	 * @var boolean
	 */
	public $defaultShortDateInList = true;	
	
	/**
	 * New users will be member of these groups
	 * 
	 * @return int[]
	 */
	public function getDefaultGroups() {		
		return array_map("intval", (new core\db\Query)
						->selectSingleValue('groupId')
						->from("core_group_default_group")
						->all());

	}
	
	/**
	 * Set default groups for new groups
	 * 
	 * @param array eg [['groupId' => 1]]
	 */
	public function setDefaultGroups($groups) {	
		
		GO()->getDbConnection()->exec("TRUNCATE TABLE core_group_default_group");
		
		foreach($groups as $groupId) {
			if(!GO()->getDbConnection()->insert("core_group_default_group", ['groupId' => $groupId])->execute()) {
				throw new Exception("Could not save group id ".$groupId);
			}
		}
	}
	
	
	public function save() {
		
		//for old framework config caching in GO\Base\Config
		if(isset($_SESSION)) {
			unset($_SESSION['GO_SESSION']['newconfig']);
		}
		
		//Make sure URL has trailing slash
		if(isset($this->URL)) {
			$this->URL = rtrim($this->URL, '/ ').'/';
		}
		
		return parent::save();
	}
}
