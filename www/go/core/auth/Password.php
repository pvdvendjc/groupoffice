<?php
namespace go\core\auth;

use go\core\orm\Query;
use go\core\model\Token;
use go\core\model\User;
use go\core\util\DateTime;
use go\core\validate\ErrorCode;

class Password extends PrimaryAuthenticator {	
	
	public static function isAvailableFor($username) {		
		return User::find()->selectSingleValue('id')->where(['username' => $username])->andWhere('password', '!=', 'null')->single() !== false;
	}
	
	/**
	 * Checks if the given password matches the password in the core_auth_password table.
	 * 
	 * @param string $password
	 * @return boolean 
	 */
	public function authenticate($username, $password, $logoutOtherDevices = false) {
		$user = User::find(['id', 'username', 'password', 'enabled'])->where(['username' => $username])->single();
		if(!$user) {
            $this->setValidationError('username', ErrorCode::INVALID_INPUT, go()->t('Bad username or password'));
			return false;
		}
		if(!$user->passwordVerify($password)) {
            $this->setValidationError('username', ErrorCode::INVALID_INPUT, go()->t('Bad username or password'));
			return false;
		}

		if (go()->getConfig()['core']['limits']['checkMultipleDevices']) {

            $oldTokens = Token::find()
                ->where('userId', '=', $user->id)
                ->where('passedMethods', 'LIKE', '%password%')
                ->where('expiresAt', '>', (new DateTime()))
                ->all();

            if ($logoutOtherDevices && count($oldTokens) > 0) {
                $query = new Query();
                foreach ($oldTokens as $oldToken) {
                    $query->orWhere(Token::parseId($oldToken->loginToken));
                }
                Token::delete($query);
            }

            if (count($oldTokens) > 0 && !$logoutOtherDevices) {
                $this->setValidationError('password', 100, 'Ingelogd op meerdere devices');
                return false;
            }

        }
	
		return $user;
	}
}
