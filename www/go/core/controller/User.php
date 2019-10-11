<?php

namespace go\core\controller;

use GO;
use go\core\exception\Forbidden;
use go\core\jmap\EntityController;
use go\core\jmap\exception\InvalidArguments;
use go\core\jmap\Response;
use go\core\orm\Entity;
use go\core\model;



class User extends EntityController {	
	
	protected function canUpdate(Entity $entity) {
		
		if(!GO()->getAuthState()->isAdmin()) {
			if($entity->isModified('groups')) {
				return false;
			}
		}
		
		return parent::canUpdate($entity);
	}
	
	/**
	 * The class name of the entity this controller is for.
	 * 
	 * @return string
	 */
	protected function entityClass() {
		return model\User::class;
	}
	
	public function loginAs($params) {
		
		if(!isset($params['userId'])) {
			throw new InvalidArguments("Missing parameter userId");
		}
		
		if(!GO()->getAuthState()->isAdmin()) {
			throw new Forbidden();
		}
		
		$user = model\User::findById($params['userId']);
		
		if(!$user->enabled) {
			throw new Exception("This user is disabled");
		}
		
		$token = GO()->getAuthState()->getToken();
		$token->userId = $params['userId'];
		$success = $token->setAuthenticated();
		
		$_SESSION['GO_SESSION'] = array_filter($_SESSION['GO_SESSION'], function($key) {
			return in_array($key, ['user_id', 'accessToken', 'security_token']);
		}, ARRAY_FILTER_USE_KEY); 
		
		Response::get()->addResponse(['success' => true]);
	}
	
	/**
	 * Handles the Foo entity's Foo/query command
	 * 
	 * @param array $params
	 * @see https://jmap.io/spec-core.html#/query
	 */
	public function query($params) {
		return $this->defaultQuery($params);
	}
	
	/**
	 * Handles the Foo entity's Foo/get command
	 * 
	 * @param array $params
	 * @see https://jmap.io/spec-core.html#/get
	 */
	public function get($params) {
		return $this->defaultGet($params);
	}
	
	/**
	 * Handles the Foo entity's Foo/set command
	 * 
	 * @see https://jmap.io/spec-core.html#/set
	 * @param array $params
	 */
	public function set($params) {
		return $this->defaultSet($params);
	}
	
	
	/**
	 * Handles the Foo entity's Foo/changes command
	 * 
	 * @param array $params
	 * @see https://jmap.io/spec-core.html#/changes
	 */
	public function changes($params) {
		return $this->defaultChanges($params);
	}
}
