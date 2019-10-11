<?php

namespace go\core\model;

use go\core\model\Acl;
use go\core\acl\model\AclOwnerEntity;
use go\core\db\Criteria;
use go\core\model\UserGroup;
use go\core\orm\Query;
use go\core\util\ArrayObject;
use go\core\validate\ErrorCode;

/**
 * Group model
 */
class Group extends AclOwnerEntity {

	const ID_ADMINS = 1;
	const ID_EVERYONE = 2;
	const ID_INTERNAL = 3;

	/**
	 *
	 * @var int
	 */
	public $id;
	
	/**
	 *
	 * @var string
	 */
	public $name;

	/**
	 * When this is set this group is the personal group for this user. And only
	 * that user will be member of this group. It's used for granting permissions
	 * to single users but keeping the database simple.
	 * 
	 * @var int
	 */
	public $isUserGroupFor;
	
	/**
	 * Created by user ID 
	 * 
	 * @var int
	 */
	public $createdBy;
	
	/**
	 * The users in this group
	 * 
	 * @var int[]
	 */
	public $users;	

	protected static function defineMapping() {
		return parent::defineMapping()
						->addTable('core_group', 'g')
						->addScalar('users', 'core_user_group', ['id' => 'groupId']);
	}
	
	protected static function defineFilters() {
		return parent::defineFilters()
						->add('hideUsers', function(Criteria $criteria, $value) {
							if($value) {
								$criteria->andWhere(['isUserGroupFor' => null]);	
							}
						})
						->add('excludeEveryone', function(Criteria $criteria, $value) {
							if($value) {
								$criteria->andWhere('id', '!=', Group::ID_EVERYONE);
							}
						})
						->add('excludeAdmins', function(Criteria $criteria, $value) {
							if($value) {
								$criteria->andWhere('id', '!=', Group::ID_ADMINS);
							}
						})->add('forUserId', function(Criteria $criteria, $value, Query $query) {
							
							$query->join('core_user_group','ug', 'ug.groupId=g.id')
											->groupBy(['g.id']);
							
							if($value) {
								$criteria->andWhere(['ug.userId' => $value]);	
							}
						});
						
	}
	
	protected static function textFilterColumns() {
		return ['name'];
	}

	protected function internalValidate()
	{
		if($this->id === self::ID_ADMINS && !in_array(1, $this->users)) {
			$this->setValidationError('users', ErrorCode::FORBIDDEN, GO()->t("You can't remove the admin user from the administrators group"));
		}

		if($this->isUserGroupFor && !in_array($this->isUserGroupFor, $this->users))
		{
			$this->setValidationError('users', ErrorCode::FORBIDDEN, GO()->t("You can't remove the group owner from the group"));
		}

		return parent::internalValidate();
	}

	protected function internalSave() {
		
		if(!parent::internalSave()) {
			return false;
		}
		
		$this->saveModules();

		if(!$this->isNew()) {
			return true;
		}

		return $this->setDefaultPermissions();		
	}

	protected function canCreate()
	{
		return GO()->getAuthState()->isAdmin();
	}
	
	private function setDefaultPermissions() {
		$acl = $this->findAcl();
		//Share group with itself. So members of this group can share with eachother.
		if($this->id !== Group::ID_ADMINS) {
			$acl->addGroup($this->id, Acl::LEVEL_READ);
		}
		
		return $acl->save();
	}
	
	protected function internalDelete() {
		
		if(isset($this->isUserGroupFor)) {
			$this->setValidationError('isUserGroupFor', ErrorCode::FORBIDDEN, "You can't delete a user's personal group");
			return false;
		}
		
		return parent::internalDelete();
	}


	public function getModules() {
		$modules = new ArrayObject();
		$modules->serializeJsonAsObject = true;

		$mods = Module::find()
							->select('id,level')
							->fetchMode(\PDO::FETCH_ASSOC)
							->join('core_acl_group', 'acl_g', 'acl_g.aclId=m.aclId')
							->where(['acl_g.groupId' => $this->id]);

		foreach($mods as $m) {
			$modules[$m['id']] = $m['level'];
		}

		return $modules;
	}

	private $setModules;

	public function setModules($modules) {
		$this->setModules = $modules;
	}

	private function saveModules() {
		if(!isset($this->setModules)) {
			return true;
		}

		foreach($this->setModules as $moduleId => $level) {
			$module = Module::findById($moduleId);
			if(!$module) {
				throw new \Exception("Module with ID " . $moduleId . " not found");
			}
			$module->setAcl([
				$this->id => $level
			]);
			$module->save();
		}
	}

}
