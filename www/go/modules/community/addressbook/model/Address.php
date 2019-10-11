<?php
namespace go\modules\community\addressbook\model;
						
use go\core\orm\Property;
						
/**
 * Address model
 *
 * @copyright (c) 2018, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */

class Address extends Property {	
	
	const TYPE_POSTAL = "postal";
	const TYPE_VISIT = "visit";
	const TYPE_WORK = "work";
	const TYPE_HOME = "home";

	/**
	 * 
	 * @var int
	 */							
	protected $contactId;

	/**
	 * 
	 * @var string
	 */							
	public $type;

	/**
	 * 
	 * @var string
	 */							
	public $street = '';
	
	/**
	 * 
	 * @var string
	 */							
	public $street2 = '';

	/**
	 * 
	 * @var string
	 */							
	public $zipCode = '';

	/**
	 * 
	 * @var string
	 */							
	public $city = '';

	/**
	 * 
	 * @var string
	 */							
	public $state = '';

	/**
	 * 
	 * @var string
	 */							
	public $country;
	
	/**
	 * ISO 3601 2 char country code. eg. "NL".
	 * @var string
	 */
	public $countryCode;
	
	public $latitude;
	public $longitude;

	protected static function defineMapping() {
		return parent::defineMapping()
						->addTable("addressbook_address");
	}
	
	protected function internalValidate() {
		$this->validateCountry();		
		return parent::internalValidate();
	}
	
	private function validateCountry() {
		if($this->isModified('countryCode')) {			
			if(isset($this->countryCode)) {
				$countries = go()->t('countries');
				if(!isset($countries[$this->countryCode])) {
					$this->setValidationError('countryCode', \go\core\validate\ErrorCode::INVALID_INPUT, "Unknown ISO 3601 2 char country code provided: " . $this->countryCode);
					return false;
				}
				$this->country = $countries[$this->countryCode];
			}
		} elseif($this->isModified('country')) {
			$countryCodes = array_flip(go()->t('countries'));
			if(isset($countryCodes[$this->country])) {
				$this->countryCode = $countryCodes[$this->country] ?? null;
			}
		}
	}
	
	
	public function getFormatted() {
			
		if(empty($this->street) && empty($this->city) && empty($this->state)){
			return "";
		}
		require(\go\core\Environment::get()->getInstallFolder() . '/language/addressformats.php');

		$format = isset($af[$this->countryCode]) ? $af[$this->countryCode] : $af['default'];

		$format= str_replace('{address}', $this->street, $format);
		$format= str_replace('{address_no}', $this->street2, $format);
		$format= str_replace('{city}', $this->city, $format);
		$format= str_replace('{zip}', $this->zipCode, $format);
		$format= str_replace('{state}', $this->state, $format);
		$format= str_replace('{country}', $this->country, $format);
		
		return preg_replace("/(\r\n)+|(\n|\r)+/", "\n", $format);
	}
	
	public function getCombinedStreet() {
		return trim($this->street . ' ' . $this->street2);
	}
	
	public function setCombinedStreet($v) {
		$lastSpace = strrpos($v, ' ');
		if($lastSpace === false) {
			$this->street = $v;
			$this->street2 = null;
		} else
		{
			$this->street = substr($v, 0, $lastSpace);
			$this->street2 = substr($v, $lastSpace + 1);
		}
	}

}