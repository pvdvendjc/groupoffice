<?php
namespace go\core\customfield;

use Exception;
use GO;
use go\core\data\Model;
use go\core\db\Criteria;
use go\core\db\Table;
use go\core\db\Utils;
use go\core\ErrorHandler;
use go\core\model\Field;
use go\core\orm\Entity;
use go\core\orm\Filters;
use go\core\orm\Query;
use go\core\util\ClassFinder;
use go\core\validate\ErrorCode;


/**
 * Abstract data type class
 * 
 * The data types handles:
 * 
 * 1. Column creation in database (Override getFieldSql())
 * 2. Input formatting with apiToDb();
 * 3. Output formatting with dbToApi();
 * 
 */
abstract class Base extends Model {
	
	/**
	 *
	 * @var Field
	 */
	protected $field;
	
	public function __construct(Field $field) {
		$this->field = $field;
	}
	
	/**
	 * True if this value is an array. Used by CSV import
	 * @return bool
	 */
	public function hasMany() {
		return false;
	}
	
	/**
	 * Get column definition for SQL.
	 * 
	 * When false is returned no databaseName is required and no field will be created.
	 * 
	 * @return string|boolean
	 */
	protected function getFieldSQL() {
		return "VARCHAR(".($this->field->getOption('maxLength') ?? 190).") DEFAULT " . go()->getDbConnection()->getPDO()->quote($this->field->getDefault() ?? "NULL");
	}

	/**
	 * 
	 * Check if this custom field has a column in the custom field record table.
	 * 
	 * @return bool
	 */
	public function hasColumn() {
		return $this->getFieldSQL() != false;
	}
	
	public function onFieldValidate() {
		$fieldSql = $this->getFieldSQL();
		if(!$fieldSql) {
			return true;
		}
		
		if($this->field->isModified("databaseName") && preg_match('/[^a-zA-Z_0-9]/', $this->field->databaseName)) {
			$this->field->setValidationError('databaseName', ErrorCode::INVALID_INPUT, go()->t("Invalid database name. Only use alpha numeric chars and underscores.", 'core','customfields'));
		}		
	}
	
	/**
	 * Called when the field is saved
	 * 
	 * @return boolean
	 */
	public function onFieldSave() {
		
		$fieldSql = $this->getFieldSQL();
		if(!$fieldSql) {
			return true;
		}
		
		$table = $this->field->tableName();
		
		
		$quotedDbName = Utils::quoteColumnName($this->field->databaseName);
	
		if ($this->field->isNew()) {
			$sql = "ALTER TABLE `" . $table . "` ADD " . $quotedDbName . " " . $fieldSql . ";";
			go()->getDbConnection()->query($sql);
			if($this->field->getUnique()) {
				$sql = "ALTER TABLE `" . $table . "` ADD UNIQUE(". $quotedDbName  . ");";
				go()->getDbConnection()->query($sql);
			}			
		} else {
			
			
			$oldName = $this->field->isModified('databaseName') ? $this->field->getOldValue("databaseName") : $this->field->databaseName;
			$col = Table::getInstance($table)->getColumn($oldName);
			
			$sql = "ALTER TABLE `" . $table . "` CHANGE " . Utils::quoteColumnName($oldName) . " " . $quotedDbName . " " . $fieldSql;
			go()->getDbConnection()->query($sql);
			
			if($this->field->getUnique() && !$col->unique) {
				$sql = "ALTER TABLE `" . $table . "` ADD UNIQUE(". $quotedDbName  . ");";
				go()->getDbConnection()->query($sql);
			} else if(!$this->field->getUnique() && $col->unique) {
				$sql = "ALTER TABLE `" . $table . "` DROP INDEX " . $quotedDbName;
				go()->getDbConnection()->query($sql);
			}
		}
		
		Table::destroyInstance($table);
		
		return true;
	}

	/**
	 * Called when a field is deleted
	 * 
	 * @return boolean
	 */
	public function onFieldDelete() {
		
		$fieldSql = $this->getFieldSQL();
		if(!$fieldSql) {
			return true;
		}
		
		$table = $this->field->tableName();
		$sql = "ALTER TABLE `" . $table . "` DROP " . Utils::quoteColumnName($this->field->databaseName) ;

		try {
			go()->getDbConnection()->query($sql);
		} catch (Exception $e) {
			ErrorHandler::logException($e);
		}
		
		Table::destroyInstance($table);
		
		return true;
	}
	
	/**
	 * Format data from API to model
	 * 
	 * This function is called when the API data is applied to the model with setValues();
	 * 
	 * @see MultiSelect for an advaced example
	 * @param mixed $value The value for this field
	 * @param array $values The values to be saved in the custom fields table
	 * @return mixed
	 */
	public function apiToDb($value, &$values) {
		return $value;
	}
	
	/**
	 * Format data from model to API
	 * 
	 * This function is called when the data is serialized to JSON
	 * 
	 * @see MultiSelect for an advaced example
	 * @param mixed $value The value for this field
	 * @param array $values All the values of the custom fields to be returned to the API
	 * @return mixed
	 */
	public function dbToApi($value, &$values) {
		return $value;
	}

	/**
	 * Get the data as string
	 * Used for templates or export
	 * 
	 * @param mixed $value The value for this field
	 * @param array $values The values inserted in the database
	 * @return string
	 */
	public function dbToText($value, &$values) {
		return $this->dbToApi($value, $values);
	}
	
	/**
	 * Called after the data is saved to API.
	 * 
	 * @see MultiSelect for an advaced example
	 * @param mixed $value The value for this field
	 * @param array $customFieldData The custom fields data
	 * @return boolean
	 */
	public function afterSave($value, &$customFieldData) {
		
		return true;
	}

	/**
	 * Validate the input on the model. 
	 * 
	 * Use setValidationError if data is invalid:
	 * 
	 * 
	 */
	public function validate($value, Field $field,  Entity $model) {		
		return true;
	}
	
	/**
	 * Called before the data is saved to API.
	 * 
	 * @see MultiSelect for an advaced example
	 * @param mixed $value The value for this field
	 * @param array $record The values inserted in the database
	 * @return boolean
	 */
	public function beforeSave($value, &$record) {
		
		return true;
	}
	
	/**
	 *
	 * Get the modelClass for this customfield, only needed if an id of a related record is stored
	 *
	 * @return bool | string
	 */
	public function getModelClass() {
		return false;
	}
	/**
	 * Get the name of this data type
	 * 
	 * @return string
	 */
	public static function getName() {
		$cls = static::class;
		return substr($cls, strrpos($cls, '\\') + 1);
	}
	
	/**
	 * Get all field types
	 * 
	 * @return string[] eg ['functionField' => "go\core\customfield\FunctionField"];
	 */
	public static function findAll() {
		
		$types = go()->getCache()->get("customfield-types");
		
		if(!$types) {
			$classFinder = new ClassFinder();
			$classes = $classFinder->findByParent(self::class);

			$types = [];

			foreach($classes as $class) {
				$types[$class::getName()] = $class;
			}
			
			if(go()->getModule(null, "files")) {
				$types['File'] = \GO\Files\Customfield\File::class;
			}
			
			go()->getCache()->set("customfield-types", $types);
		}
		
		return $types;		
	}
	
	/**
	 * Find the class for a type
	 * 
	 * @param string $name
	 * @return string
	 */
	public static function findByName($name) {
		
		//for compatibility with old version
		//TODO remove when refactored completely
		$pos = strrpos($name, '\\');
		if($pos !== false) {
			$name = lcfirst(substr($name, $pos + 1));
		}
		$all = static::findAll();

		if(!isset($all[$name])) {
			go()->debug("WARNING: Custom field type '$name' not found");			
			return Text::class;
		}
		
		return $all[$name];
	}
	
	
	protected function joinCustomFieldsTable(Query $query) {
		if(!$query->isJoined($this->field->tableName())){
			$cls = $query->getModel();
			$primaryTableAlias = array_values($cls::getMapping()->getTables())[0]->getAlias();
			$query->join($this->field->tableName(),'customFields', 'customFields.id = '.$primaryTableAlias.'.id', 'LEFT');
		}
	}
	
	
	/**
	 * Defines an entity filter for this field.
	 * 
	 * @see Entity::defineFilters()
	 * @param Filters $filter
	 */
	public function defineFilter(Filters $filters) {
		
		
		$filters->addText($this->field->databaseName, function(Criteria $criteria, $comparator, $value, Query $query, array $filter){
			$this->joinCustomFieldsTable($query);						
			$criteria->where('customFields.' . $this->field->databaseName, $comparator, $value);
		});
	}
}
