<?php

namespace go\core\data;

use go\core\App;
use go\core\data\ArrayableInterface;
use go\core\data\exception\NotArrayable;
use go\core\util\DateTime;
use JsonSerializable;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use go\core\util\ArrayObject;

/**
 * The abstract model class. 
 * 
 * Models implement validation by default and can be converted into an Array for
 * the API.
 * 
 * @copyright (c) 2014, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */
abstract class Model implements ArrayableInterface, JsonSerializable {

	/**
	 * Get all properties exposed to the API
	 * 
	 * eg.
	 * 
	 * [
	 * 	"propName" => [
	 * 		'setter' => true, //Set with setPropName
	 * 		'getter'=> true', //Get with getPropName
	 * 		'access' => ReflectionProperty::IS_PROTECTED // is a protected property
	 * ]
	 * 
	 * @return array
	 */
	public static function getApiProperties() {
		$cacheKey = 'api-props-' . str_replace('\\', '-', static::class);
		
		$ret = App::get()->getCache()->get($cacheKey);
		if ($ret) {
			return $ret;
		}

		$arr = [];
		$reflectionObject = new ReflectionClass(static::class);
		$methods = $reflectionObject->getMethods(ReflectionMethod::IS_PUBLIC);

		foreach ($methods as $method) {
			/* @var $method ReflectionMethod */

			if ($method->isStatic()) {
				continue;
			}

			
			if (substr($method->getName(), 0, 3) == 'get') {

				$params = $method->getParameters();
				foreach ($params as $p) {
					/* @var $p ReflectionParameter */
					if (!$p->isDefaultValueAvailable()) {
						continue 2;
					}
				}

				$propName = lcfirst(substr($method->getName(), 3));
				if(!isset($arr[$propName])) {
					$arr[$propName] = ["setter" => false, "getter" => false, "access" => null];
				}
				$arr[$propName]['getter'] = true;				
			}

			if (substr($method->getName(), 0, 3) == 'set') {
				$propName = lcfirst(substr($method->getName(), 3));
				if(!isset($arr[$propName])) {
					$arr[$propName] = ["setter" => false, "getter" => false, "access" => null];
				}
				$arr[$propName]['setter'] = true;				
			}
		}

		$props = $reflectionObject->getProperties();

		foreach ($props as $prop) {
			if (!$prop->isStatic()) {
				$propName = $prop->getName();
				if(!isset($arr[$propName])) {
					$arr[$propName] = ["setter" => false, "getter" => false, "access" => null];
				}

				if($prop->isPublic()) {	
					$arr[$propName]['access'] = ReflectionProperty::IS_PUBLIC;					
					$arr[$propName]['setter'] = false;
					$arr[$propName]['getter'] = false;
				}				
				if($prop->isProtected()) {
					$arr[$propName]['access'] = ReflectionProperty::IS_PROTECTED;					
				}
			}
		}
		
		App::get()->getCache()->set($cacheKey, $arr);

		return $arr;
	}
	/**
	 * Get the readable property names as array
	 * 
	 * @return string[]
	 */
	protected static function getReadableProperties() {
		return array_keys(array_filter(static::getApiProperties(), function($props){
			return $props['getter'] || $props['access'] == ReflectionProperty::IS_PUBLIC;
		}));
	}
	
	/**
	 * Get the readable property names as array
	 * 
	 * @return string[]
	 */
	protected static function getWritableProperties() {
		return array_keys(array_filter(static::getApiProperties(), function($props){
			return $props['setter'] || $props['access'] == ReflectionProperty::IS_PUBLIC;
		}));
	}

	protected static function isProtectedProperty($name) {
		$props = static::getApiProperties();

		if(!isset($props[$name])) {
			return false;
		}

		return $props[$name]['access'] === ReflectionProperty::IS_PROTECTED;
	}	
	/**
	 * Convert model into array for API output.
	 * 
	 * @param string[] $properties
	 * @return array
	 */
	public function toArray($properties = []) {

		$arr = [];
		
		if(empty($properties)) {
			$properties = $this->getReadableProperties();
		}

		foreach ($properties as $propName) {
			try {
				$arr[$propName] = $this->propToArray($propName);
			} catch (NotArrayable $e) {
				
				App::get()->debug("Skipped prop " . static::class . "::" . $propName . " because type it's not scalar or ArrayConvertable.");
			}
		}
		
		return $arr;
	}

	protected function propToArray($name) {
		$value = $this->getValue($name);
		return $this->convertValue($value);
	}

	/**
	 * Converts value to an array if supported
	 * 
	 * 
	 * @param type $value
	 * @param type $subReturnProperties
	 * @return DateTime
	 * @throws NotArrayable
	 */
	protected function convertValue($value) {
		if ($value instanceof ArrayableInterface) {
			return $value->toArray();
		} elseif (is_array($value)) {
			foreach ($value as $key => $v) {
				$value[$key] = $this->convertValue($v);
			}
			return $value;
		} else if($value instanceof ArrayObject) {
			$arr = clone $value;
			foreach ($arr as $key => $v) {
				$arr[$key] = $this->convertValue($v);
			}
			return $arr;
		} else if (is_scalar($value) || is_null($value)) {
			return $value;
		} else if ($value instanceof \StdClass) {
			return $value;
		} else {
			throw new NotArrayable();
		}
	}


	/**
	 * Set public properties with key value array.
	 * 
	 * This function should also normalize input when you extend this class.
	 * 
	 * For example dates in ISO format should be converted into DateTime objects
	 * and related models should be converted to an instance of their class.
	 * 
	 *
	 * @Example
	 * ```````````````````````````````````````````````````````````````````````````
	 * $model = User::findByIds([1]);
	 * $model->setValues(['username' => 'admin']);
	 * $model->save();
	 * ```````````````````````````````````````````````````````````````````````````
	 *
	 * 
	 * @param array $values  ["propNamne" => "value"]
	 * @return \static
	 */
	public function setValues(array $values) {
		foreach($values as $name => $value) {
			$this->setValue($name, $value);
		}
		return $this;
	}


	/**
	 * Set a property with API input normalization.
	 * 
	 * It also uses a setter function if available
	 * 
	 * @param string $propName
	 * @param mixed $value
	 * @return $this
	 */
	public function setValue($propName, $value) {

		$props = $this->getApiProperties();

		if(!isset($props[$propName])) {
			throw new \Exception("Not existing property $propName for " . static::class);
		}

		if($props[$propName]['setter']) {
			$setter = 'set' . $propName;	
			$this->$setter($value);
		} else if($props[$propName]['access'] == \ReflectionProperty::IS_PUBLIC){
			$this->{$propName} = $this->normalizeValue($propName, $value);
		}	else if($props[$propName]['getter']) {
			GO()->warn("Ignoring setting of read only property ". $propName ." for " . static::class);
		} else{
			throw new \Exception("Invalid property ". $propName ." for " . static::class);
		}

		return $this;
	}

	/**
	 * Normalizes API input for this model.
	 * 
	 * @param string $propName
	 * @param mixed $value
	 * @return mixed
	 */
	protected function normalizeValue($propName, $value) {
		return $value;
	}

	/**
	 * Get's a public property. Also uses getters functions.
	 * 
	 * @param \go\core\data\Model $model
	 * @param string $propName
	 * @return mixed
	 */
	public function getValue($propName) {
		$props = $this->getApiProperties();
		
		if(!isset($props[$propName])) {
			throw new \Exception("Not existing property $propName in " . static::class);
		}

		if($props[$propName]['getter']) {
			$getter = 'get' . $propName;	
			return $this->$getter();
		} elseif($props[$propName]['access'] === \ReflectionProperty::IS_PUBLIC){
			return $this->{$propName};
		}	else{
			throw new \Exception("Can't get write only property ". $propName . " in " . static::class);
		}
	}
	
	public function jsonSerialize() {
		return $this->toArray();
	}
	
	/**
	 * Get's the class name without the namespace
	 * 
	 * eg. class go\modules\community\notes\model\Note becomes just "note"
	 * 
	 * @return string
	 */
	public static function getClassName() {
		$cls = static::class;
		return substr($cls, strrpos($cls, '\\') + 1);
	}
}
