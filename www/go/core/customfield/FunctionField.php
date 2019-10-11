<?php

namespace go\core\customfield;

class FunctionField extends Number {
	
	//no db field for functions
	public function onFieldSave() {
		return true;
	}
	
	public function onFieldDelete() {
		return true;
	}
	
	/**
	 * Get column definition for SQL
	 * 
	 * @return string
	 */
	protected function getFieldSQL() {
		$d = $this->field->getDefault();
		$d = isset($d) && $d != "" ? number_format($d, 4) : "NULL";
		
		$decimals = $this->field->getOption('numberDecimals') + 2;
		
		return "decimal(19,$decimals) DEFAULT " . $d;
	}

	public function dbToApi($dummy, &$values) {
		$f = $this->field->getOption("function");
		
		foreach ($values as $key => $value) {
			if(is_numeric($value)) {
				$f = str_replace('{' . $key . '}', $value, $f);
			}
		}
		$f = preg_replace('/\{[^}]*\}/', '0', $f);
		
		// GO()->debug("Function field formula: \$result = " .  $f. ";");
		
		if(empty($f)) {
			return null;
		}

		$result = null;		
		eval("\$result = " . $f . ";");		
		return $result;
	}

	public function beforeSave($value, &$record)
	{
		//remove data because it's not saved to the database
		unset($record[$this->field->databaseName]);

		return true;
	}

}
