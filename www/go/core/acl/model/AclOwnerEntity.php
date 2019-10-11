<?php
namespace go\core\acl\model;

use go\core\model\Acl;
use go\core\model\AclGroup;
use go\core\App;
use go\core\orm\Query;
use go\core\jmap\Entity;
use go\core\orm\Mapping;
use go\core\exception\Forbidden;
use go\core\db\Expression;

/**
 * The AclEntity
 * 
 * Is an entity that has an "aclId" property. The ACL is used to restrict access
 * to the entity.
 * 
 * @see Acl
 */
abstract class AclOwnerEntity extends AclEntity {
	
	/**
	 * The ID of the {@see Acl}
	 * 
	 * @var int
	 */
	protected $aclId;
	
	/**
	 * The acl entity
	 * @var Acl 
	 */
	private $acl;


	protected function internalSave() {
		
		if($this->isNew() && !isset($this->aclId)) {
			$this->createAcl();
		}

		if(!$this->saveAcl()) {
			return false;
		}
		
		if(!parent::internalSave()) {
			return false;
		}

		if($this->isNew() && isset($this->acl)) {
			$this->acl->entityId = $this->id;
			if(!$this->acl->save()) {
				return false;
			}
		}

		return true;
	}

	private $aclChanges;

	/**
	 * This is set with the new and old groupLevel values
	 * 
	 * @var array [groupId => [newLevel, oldLevel]]
	 */
	protected function getAclChanges() {
		return $this->aclChanges;
	}

	private function saveAcl() {
		if(!isset($this->setAcl)) {
			return true;
		}

		$a = $this->findAcl();

		$this->checkManagePermission();

		foreach($this->setAcl as $groupId => $level) {
			$a->addGroup($groupId, $level);
		}

		
		$mod = $a->getModified(['groups']);
		if(isset($mod['groups'])) {
			$this->aclChanges = [];
			foreach($mod['groups'][0] as $new) {
				$this->aclChanges[$new->groupId] = [$new->level, null];
			}
			foreach($mod['groups'][1] as $old) {
				if(!isset($this->aclChanges[$new->groupId])) {
					$this->aclChanges[$new->groupId] = [null, $old->level];
				} else {
					$this->aclChanges[$new->groupId][1] = $old->level;
				}
			}
		}

		return $a->save();
	}

	/**
	 * Returns an array with group ID as key and permission level as value.
	 * 
	 * @return array eg. ["2" => 50, "3" => 10]
	 */
	public function getAcl() {
		$a = $this->findAcl();
		
		$acl = [];
		if($a) {
			foreach($a->groups as $group) {
				$acl[$group->groupId] = $group->level;
			}
		}

		return $acl;
	}

	protected $setAcl;

	/**
	 * Set the ACL
	 * 
	 * @param $acl an array with group ID as key and permission level as value. eg. ["2" => 50, "3" => 10]
	 * 
	 * @example
	 * ```
	 * $addressBook->setAcl([
	 * 	Group::ID_INTERNAL => Acl::LEVEL_DELETE
	 * ]);
	 * ```
	 */
	public function setAcl($acl) {		
		$this->setAcl = $acl;		
	}

	/**
	 * Permissions are set via AclOwnerEntity models through setAcl(). When this propery is used it will configure the Acl models.
	 * This permission is not checked in the controller as usal but checked on save here.
	 */
	protected function checkManagePermission() {
		if($this->findAcl()->ownedBy == go()->getUserId()) {
			return true;
		}

		if(!$this->findAcl()->hasPermissionLevel(Acl::LEVEL_MANAGE)) {		
			throw new Forbidden("You are not allowed to manage permissions on this ACL");
		}
	}
	
	protected function createAcl() {
		
		// Copy the default one. When installing the default one can't be accessed yet.
		// When ACL has been provided by the client don't copy the default.
		if(isset($this->setAcl) || go()->getInstaller()->isInProgress()) {
			$this->acl = new Acl();
		} else
		{
			$defaultAcl = Acl::findById(static::entityType()->getDefaultAclId());		
			$this->acl = $defaultAcl->copy();
		}
		$aclColumn = $this->getMapping()->getColumn('aclId');
		if(!$aclColumn) {
			throw new \Exception("Column aclId is required for AclOwnerEntity ". static::class);
		}
		$this->acl->usedIn = $aclColumn->table->getName().'.aclId';
		try {
			$this->acl->entityTypeId = $this->entityType()->getId();
		} catch(\Exception $e) {

			//During install this will throw a module not found error due to chicken / egg problem.
			//We'll fix the data with the Group::check() function in the installer.
			if(!go()->getInstaller()->isInProgress()) {
				throw $e;
			}
			$this->acl->entityTypeId = null;
		}
		//$this->acl->entityId = $this->id;
		$this->acl->ownedBy = !empty($this->createdBy) ? $this->createdBy : $this->getDefaultCreatedBy();

		if(!$this->acl->save()) {	
			throw new \Exception("Could not create ACL");
		}

		$this->aclId = $this->acl->id;		
	}
	
	protected function internalDelete() {
		if(!parent::internalDelete()) {
			return false;
		}
		
		if(!method_exists($this, 'aclEntityClass')) {
			$this->deleteAcl();
		}
		
		return true;
	}
	
	protected function deleteAcl() {		
		$acl = Acl::find()->where(['id' => $this->aclId])->single();
		if(!$acl->delete()) {
			throw new \Exception("Could not delete ACL ".$this->aclId);
		}
	}
	
	/**
	 * Get the ACL entity
	 * 
	 * @return Acl
	 */
	public function findAcl() {
		if(empty($this->aclId)) {
			return null;
		}
		if(!isset($this->acl)) {
			$this->acl = Acl::internalFind()->where(['id' => $this->aclId])->single();
		}
		
		return $this->acl;
	}
	
	/**
	 * Get the permission level of the current user
	 * 
	 * @return int
	 */
	public function getPermissionLevel() {

		if($this->isNew()) {
			return parent::getPermissionLevel();
		}

		if(!isset($this->permissionLevel)) {
			$this->permissionLevel = Acl::getUserPermissionLevel($this->aclId, App::get()->getAuthState()->getUserId());
		}

		return $this->permissionLevel;
	}
	
	/**
	 * Applies conditions to the query so that only entities with the given 
	 * permission level are fetched.
	 * 
	 * Note: when you join another table with an acl ID you can use Acl::applyToQuery():
	 * 
	 * ```
	 * $query = User::find();
	 * 
	 * $query	->join('applications_application', 'a', 'a.createdBy = u.id')
							->groupBy(['u.id']);
			
	 * //We don't want to use the Users acl but the applications acl.
			\go\core\model\Acl::applyToQuery($query, 'a.aclId');
	 * 
	 * ```
	 * 
	 * @param Query $query
	 * @param int $level
	 * @param int $userId
	 * @param int[] $groups Supply user groups to check. $userId must be null when usoing this. Leave to null for the current user
	 */
	public static function applyAclToQuery(Query $query, $level = Acl::LEVEL_READ, $userId = null, $groups = null) {			
		$tables = static::getMapping()->getTables();
		$firstTable = array_shift($tables);
		$tableAlias = $firstTable->getAlias();
		Acl::applyToQuery($query, $tableAlias . '.aclId', $level, $userId, $groups);
		
		return $query;
	}
	
	/**
	 * Finds all aclId's for this entity
	 * 
	 * This query is used in the "getFooUpdates" methods of entities to determine if any of the ACL's has been changed.
	 * If so then the server will respond that it cannot calculate the updates.
	 * 
	 * @see \go\core\jmap\EntityController::getUpdates()
	 * 
	 * @return Query
	 */
	public static function findAcls() {
		$tables = static::getMapping()->getTables();
		$firstTable = array_shift($tables);
		return (new Query)->selectSingleValue('aclId')->from($firstTable->getName());
	}
	
	public function findAclId() {
		return $this->aclId;
	}

	/**
	 * Get the table alias holding the aclId
	 */
	public static function getAclEntityTableAlias() {
		return static::getMapping()->getColumn('aclId')->table->getAlias();
	}

	/**
	 * Check database integrity
	 */
	public static function check()
	{
		$tables = static::getMapping()->getTables();
		$table = array_values($tables)[0]->getName();
		
		$stmt = go()->getDbConnection()->update(
      'core_acl', 
      [
        'acl.entityTypeId' => static::entityType()->getId(), 
        'acl.entityId' => new Expression('entity.id')],
      (new Query())
        ->tableAlias('acl')
        ->join($table, 'entity', 'entity.aclId = acl.id'));
  
		if(!$stmt->execute()) {
			throw new \Exception("Could not update ACL");
		}
	}

}
