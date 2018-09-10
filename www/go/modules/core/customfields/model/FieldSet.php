<?php

namespace go\modules\core\customfields\model;

use go\core\acl\model\AclEntity;
use go\core\db\Query;

class FieldSet extends AclEntity {
/**
	 * The Entity ID
	 * 
	 * @var int
	 */
	public $id;

	public $name;
	
	protected $entityId;
	
	public $sortOrder;
	
	protected $entity;
	
	protected $filter;	
	
	public function getFilter() {
		return empty($this->filter) ? [] : json_decode($this->filter , true);
	}
	
	public function setFilter($filter) {
		$this->filter = json_encode($filter);
	}
	
	protected static function defineMapping() {
		return parent::defineMapping()
						->addTable('core_customfields_field_set', 'fs')
						->setQuery((new Query())->select("e.name AS entity")->join('core_entity', 'e', 'e.id = fs.entityId'));						
	}
	
	public function getEntity() {
		return $this->entity;
	}
	
	public function setEntity($name) {
		$this->entity = $name;
		$e = \go\core\orm\EntityType::findByName($name);
		$this->entityId = $e->getId();
	}
	
	public static function filter(Query $query, array $filter) {
		
		if(!empty($filter['entities'])) {
			
			$ids = \go\core\orm\EntityType::namesToIds($filter['entities']);
			
			$query->andWhere('entityId', 'IN', $ids);
		}
		
		return parent::filter($query, $filter);
	}

}
