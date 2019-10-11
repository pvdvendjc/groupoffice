<?php

namespace go\modules\community\addressbook\install;

use Exception;
use go\core\db\Database;
use go\core\db\Query;
use go\core\util\DateTime;
use go\modules\community\addressbook\model\Address;
use go\modules\community\addressbook\model\AddressBook;
use go\modules\community\addressbook\model\Contact;
use go\modules\community\addressbook\model\Date;
use go\modules\community\addressbook\model\EmailAddress;
use go\modules\community\addressbook\model\PhoneNumber;
use go\modules\community\addressbook\model\Url;
use function GO;
use go\core\db\Table;

/*
update addressbook_contact n set filesFolderId = (select files_folder_id from ab_contacts o where o.id=n.id);
update addressbook_contact n set filesFolderId = (select files_folder_id from ab_companies o where n.id = (o.id + (select max(id) from ab_contacts)) );


update comments_comment n set entityTypeId=(select id from core_entity where name='Contact'), entityId = (entityId + (select max(id) from ab_contacts)) where entityTypeId = 3;
*/
class Migrate63to64 {
	
	private $countries;

	public function run() {
		
		//clear cache for ClassFinder fail in custom field type somehow.
		GO()->getCache()->flush();
		Table::destroyInstances();
		
		$this->countries = GO()->t('countries');
		
		$db = GO()->getDbConnection();
		//Start from scratch
		// $db->query("DELETE FROM addressbook_contact");
		// $db->query("DELETE FROM addressbook_addressbook");
		
			
		$this->migrateCustomFields();
		
		$this->migrateCompanyLinksAndComments();		

		$addressBooks = $db->select('a.*')->from('ab_addressbooks', 'a')
						->join("ab_contacts", 'c', 'c.addressbook_id = a.id', 'left')
						->join("ab_companies", 'o', 'c.addressbook_id = a.id', 'left')
						->groupBy(['a.id'])
						->having("count(c.id)>0 or count(o.id)>0");		

		foreach ($addressBooks as $abRecord) {
			echo "Migrating addressbook ". $abRecord['name'] . "\n";
			flush();

			$addressBook = AddressBook::find()->where(['name'=>$abRecord['name']])->single();
			if(!$addressBook) {
				$addressBook = new AddressBook();
				$addressBook->id = $abRecord['id'];
				$addressBook->createdBy = $abRecord['user_id'];
				$addressBook->aclId = $abRecord['acl_id'];
				$addressBook->name = $abRecord['name'];
				$addressBook->filesFolderId = $abRecord['files_folder_id'];
				
				if (!$addressBook->save()) {
					throw new Exception("Could not save addressbook");
				}
			}

			$this->copyCompanies($addressBook);
			
			$this->copyContacts($addressBook);	
			
			echo "\n";
			flush();
		}
		
		//$this->migrateCompanyLinks();		
		$this->addCustomFieldKeys();

		$m = new \go\core\install\MigrateCustomFields63to64();
		$m->migrateEntity("Contact");				
		
		$this->migrateCustomField();

		
		
	}
	
	private function addCustomFieldKeys() {
		$c = GO()->getDbConnection();
		$c->query("delete from addressbook_contact_custom_fields where id not in (select id from addressbook_contact);");	
		$c->query("ALTER TABLE `addressbook_contact_custom_fields` ADD FOREIGN KEY (`id`) REFERENCES `addressbook_contact`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT;");

	}
	public function migrateCustomFields() {
		echo "Migrating custom fields\n";
		flush();
		$c = GO()->getDbConnection();
		$c->query("DROP TABLE IF EXISTS addressbook_contact_custom_fields");
		$c->query("CREATE TABLE addressbook_contact_custom_fields LIKE cf_ab_contacts;");		
		$c->query("INSERT addressbook_contact_custom_fields SELECT * FROM cf_ab_contacts;");
		$c->query("ALTER TABLE `addressbook_contact_custom_fields` CHANGE `model_id` `id` INT(11) NOT NULL;");

		
		
		try{
			$this->mergeCompanyCustomFields();
		} catch(\Exception $e) {
			echo "WARNING: Will shrink column sizes because of error: " .$e->getMessage() ."\n";
			$this->shrinkToFit('addressbook_contact_custom_fields');
			$this->shrink = true;
			$this->mergeCompanyCustomFields();
		}
	}

	private $shrink = false;


	private function shrinkToFit($tableName) {
		$table = Table::getInstance($tableName);

		foreach($table->getColumns() as $c) {
			if($c->dbType == 'varchar' || $c->dbType == 'char') {
				$length = go()->getDbConnection()->selectSingleValue("max(length(`" . $c->name . "`))")->from($tableName)->single();

				$c->dataType = $c->dbType . '(' . $length . ')';
				$colDef = $c->getCreateSQL();

				go()->getDbConnection()->exec("ALTER TABLE `" . $tableName . "` CHANGE `".$c->name."` `".$c->name."` " . $colDef);
			}
		}
	}
	
	private function mergeCompanyCustomFields() {
		
		$companyTable = \go\core\db\Table::getInstance("cf_ab_companies");
		$cfTable = \go\core\db\Table::getInstance("addressbook_contact_custom_fields");
		$cols = $companyTable->getColumns();
		$cols = array_filter($cols, function($n) {return $n->name != "model_id";});
		
		if(empty($cols)) {
			return;
		}		
		
		$alterSQL = "ALTER TABLE addressbook_contact_custom_fields ";
		
		$renameMap = [];
		foreach($cols as $col) {
			$i = 1;
			$name = $stripped = preg_replace('/\s+/', '_', $col->name);
			while($cfTable->hasColumn($name)) {
				$name = $stripped . '_' . $i++;
			}
			$renameMap[$col->name] = $name;

			if($this->shrink && ($col->dbType == 'varchar' || $col->dbType == 'char')) {
				//prevent max row size error by shrinking column to fit
				$length = go()->getDbConnection()->selectSingleValue("max(length(`" . $col->name . "`))")->from("cf_ab_companies")->single();
				$col->dataType = $col->dbType . '(' . $length . ')';
			}
			
			$alterSQL .= 'ADD `' . $name . '` ' . $col->getCreateSQL() . ",\n";
		}
		
		$alterSQL = substr($alterSQL, 0, -2) . ';';
		
		echo $alterSQL."\n\n";
		
		GO()->getDbConnection()->query($alterSQL);
		
				
		$data = GO()->getDbConnection()
						->select('(`model_id` + '. $this->getCompanyIdIncrement().') as id')
						->select(array_map([\go\core\db\Utils::class, "quoteColumnName"],array_keys($renameMap)), true)
						->from('cf_ab_companies');
		
		GO()->getDbConnection()->insert('addressbook_contact_custom_fields', $data, array_merge(['id'], array_values($renameMap)))->execute();
		
		$companyEntityType = \go\core\orm\EntityType::findByName("Company");
		
		if($companyEntityType) {
			
			foreach($renameMap as $old => $new) {
				GO()->getDbConnection()
							->update("core_customfields_field", 
											['databaseName' => $new],
											(new \go\core\orm\Query)
											->where(	'fieldSetId', 'IN', 
															(new \go\core\db\Query)
																->select('id')
																->from('core_customfields_field_set')
																->where(['entityId' => $companyEntityType->getId()])
															)
											->andWhere('databaseName', '=', $old)
											)
							->execute();
			}
			
			GO()->getDbConnection()
							->update("core_customfields_field_set", 
											['entityId' => Contact::entityType()->getId()], 
											['entityId' => $companyEntityType->getId()])
							->execute();
		}
		
		\go\core\db\Table::destroyInstances();
	}
	
	public function migrateCompanyLinksAndComments() {		
		echo "Migrating links\n";
		flush();
		$companyEntityType = \go\core\orm\EntityType::findByName("Company");
		if(!$companyEntityType) {
			return;
		}
		
//		GO()->getDbConnection()
//						->delete(
//										'core_link', 
//										(new \go\core\db\Query)
//										->where(['fromEntityTypeId' => Contact::entityType()->getId()])
//										->andWhere('fromId', 'NOT IN', Contact::find()->select('id'))
//										)->execute();
//		
//		GO()->getDbConnection()
//						->delete(
//										'core_link', 
//										(new \go\core\db\Query)
//										->where(['toEntityTypeId' => Contact::entityType()->getId()])
//										->andWhere('toId', 'NOT IN', Contact::find()->select('id'))
//										)->execute();
		
		go()->getDbConnection()->beginTransaction();
		GO()->getDbConnection()
						->update("core_link", 
										[
												'fromEntityTypeId' => Contact::entityType()->getId(),
												'fromId' => new \go\core\db\Expression('fromId + ' . $this->getCompanyIdIncrement())
										], 
										['fromEntityTypeId' => $companyEntityType->getId()])
						->execute();
		
		GO()->getDbConnection()
						->update("core_link", 
										[
												'toEntityTypeId' => Contact::entityType()->getId(),
												'toId' => new \go\core\db\Expression('toId + ' . $this->getCompanyIdIncrement())
										], 
										['toEntityTypeId' => $companyEntityType->getId()])
						->execute();


		GO()->getDbConnection()->exec("update comments_comment n set entityTypeId=(select id from core_entity where name='Contact'), entityId = (entityId + (select max(id) from ab_contacts)) where entityTypeId = (select id from core_entity where name='Company');");

		GO()->getDbConnection()->delete("core_entity", ['name' => "Company"])->execute();

		go()->getDbConnection()->commit();
		
//		GO()->getDbConnection()
//						->update("core_search", 
//										[
//												'entityTypeId' => Contact::entityType()->getId(),
//												'entityId' => new \go\core\db\Expression('entityId + ' . $this->getCompanyIdIncrement())
//										], 
//										['entityTypeId' => $companyEntityType->getId()])
//						->execute();
	}
	
	public function migrateCustomField() {
		
		echo "Migrating address book custom field types\n";
		flush();
	
		$cfMigrator = new \go\core\install\MigrateCustomFields63to64();
		$fields = \go\core\model\Field::find()->where(['type' => [
				'Contact', 
				'Company'
				]]);
		
		foreach($fields as $field) {
			try{
				echo "Migrating ".$field->databaseName ."\n";
				if($field->type == "Company") {
					$field->type = "Contact";
					$field->setOption("isOrganization", true);
					$incrementID = $this->getCompanyIdIncrement();
				} else
				{
					$field->setOption("isOrganization", false);
					$incrementID = 0;
				}
				$cfMigrator->updateSelectEntity($field, Contact::class, $incrementID);
			} catch(\Exception $e) {
				echo "ERROR: Failed to migrate ".$field->databaseName .' - '. $field->id."\n";
			}
		}
	}
	
	private $companyIdIncrement;
	
	public function getCompanyIdIncrement() {
		if(!isset($this->companyIdIncrement)) {
			$this->companyIdIncrement = (int) GO()->getDbConnection()->selectSingleValue('max(id)')->from('ab_contacts')->execute()->fetch();
		}
		return $this->companyIdIncrement;
	}


	private function copyContacts(AddressBook $addressBook) {
		
		
		$db = GO()->getDbConnection();

		$contacts = $db->select()->from('ab_contacts')
						->where(['addressbook_id' => $addressBook->id])
						->andWhere('id not in (select id from addressbook_contact)')
						->orderBy(['id' => 'ASC']);
		
		//continue where we left last time if failed.
		$max = $db->selectSingleValue('max(id)')
						->from("addressbook_contact")
						->where('id', '<', $this->getCompanyIdIncrement())
						->andWhere(['addressBookId' => $addressBook->id])
						->single();
		
		if($max>0) {
			$contacts->andWhere('id', '>', $max);
		}
						

		foreach ($contacts as $r) {
			$r = array_map("trim", $r);
			echo ".";
			flush();
		
			$contact = new Contact();
			$contact->id = $r['id'];
			$contact->addressBookId = $addressBook->id;
			$contact->initials = $r['initials'];
			$contact->firstName = $r['first_name'];
			$contact->middleName = $r['middle_name'];
			$contact->lastName = $r['last_name'];

			$contact->prefixes = $r['title'];
			$contact->suffixes = $r['suffix'];
			$contact->gender = $r['sex'];

			if (!empty($r['birthday'])) {
				$contact->dates[] = (new Date())
								->setValues([
						'type' => Date::TYPE_BIRTHDAY,
						'date' => DateTime::createFromFormat('Y-m-d', $r['birthday'])
				]);
			}

			if (!empty($r['action_date'])) {
				$contact->dates[] = (new Date())
								->setValues([
						'type' => "action",
						'date' => DateTime::createFromFormat('Y-m-d', $r['action_date'])
				]);
			}

			if (!empty($r['email'])) {
				$contact->emailAddresses[] = (new EmailAddress())
								->setValues([
						'type' => EmailAddress::TYPE_WORK,
						'email' => $r['email']
				]);
			}

			if (!empty($r['email2'])) {
				$contact->emailAddresses[] = (new EmailAddress())
								->setValues([
						'type' => EmailAddress::TYPE_WORK,
						'email' => $r['email2']
				]);
			}
			if (!empty($r['email3'])) {
				$contact->emailAddresses[] = (new EmailAddress())
								->setValues([
						'type' => EmailAddress::TYPE_WORK,
						'email' => $r['email3']
				]);
			}


			//$r['department'] ???

			$contact->jobTitle = $r['function'];

			if (!empty($r['home_phone'])) {
				$contact->phoneNumbers[] = (new PhoneNumber())
								->setValues([
						'type' => PhoneNumber::TYPE_HOME,
						'number' => $r['home_phone']
				]);
			}

			if (!empty($r['work_phone'])) {
				$contact->phoneNumbers[] = (new PhoneNumber())
								->setValues([
						'type' => PhoneNumber::TYPE_WORK,
						'number' => $r['work_phone']
				]);
			}

			if (!empty($r['fax'])) {
				$contact->phoneNumbers[] = (new PhoneNumber())
								->setValues([
						'type' => PhoneNumber::TYPE_FAX,
						'number' => $r['fax']
				]);
			}

			if (!empty($r['work_fax'])) {
				$contact->phoneNumbers[] = (new PhoneNumber())
								->setValues([
						'type' => PhoneNumber::TYPE_FAX,
						'number' => $r['work_fax']
				]);
			}

			if (!empty($r['cellular'])) {
				$contact->phoneNumbers[] = (new PhoneNumber())
								->setValues([
						'type' => PhoneNumber::TYPE_MOBILE,
						'number' => $r['cellular']
				]);
			}

			if (!empty($r['cellular2'])) {
				$contact->phoneNumbers[] = (new PhoneNumber())
								->setValues([
						'type' => PhoneNumber::TYPE_MOBILE,
						'number' => $r['cellular2']
				]);
			}

			if (!empty($r['homepage'])) {
				$contact->urls[] = (new Url())
								->setValues([
						'type' => Url::TYPE_HOMEPAGE,
						'url' => $r['homepage']
				]);
			}

			if (!empty($r['url_facebook'])) {
				$contact->urls[] = (new Url())
								->setValues([
						'type' => Url::TYPE_FACEBOOK,
						'url' => $r['url_facebook']
				]);
			}

			if (!empty($r['url_linkedin'])) {
				$contact->urls[] = (new Url())
								->setValues([
						'type' => Url::TYPE_LINKEDIN,
						'url' => $r['url_linkedin']
				]);
			}

			if (!empty($r['url_twitter'])) {
				$contact->urls[] = (new Url())
								->setValues([
						'type' => Url::TYPE_TWITTER,
						'url' => $r['url_twitter']
				]);
			}

			if (!empty($r['skype_name'])) {
				$contact->urls[] = (new Url())
								->setValues([
						'type' => "skype",
						'url' => $r['skype_name']
				]);
			}


			$address = new Address();
			$address->type = Address::TYPE_HOME;
			$address->countryCode = isset($r['country']) && \go\core\validate\CountryCode::validate(strtoupper($r['country'])) ? strtoupper($r['country']) : null;
			$address->state = $r['state'] ?? null;
			$address->city = $r['city'] ?? null;
			$address->zipCode = $r['zip'] ?? null;
			$address->street = $r['address'] ?? null;
			$address->street2 = $r['address_no'] ?? null;
			$address->latitude = $r['latitude'] ?? null;
			$address->longitude = $r['longitude'] ?? null;

			if ($address->isModified()) {
				$contact->addresses[] = $address;
			}

			$contact->notes = $r['comment'];

			$contact->filesFolderId = $r['files_folder_id'];

			$contact->createdAt = new DateTime("@" . $r['ctime']);
			$contact->modifiedAt = new DateTime("@" . $r['mtime']);
			$contact->createdBy = \go\core\model\User::findById($r['user_id'], ['id']) ? $r['user_id'] : 1;
			$contact->modifiedBy = \go\core\model\User::findById($r['muser_id'], ['id']) ? $r['muser_id'] : 1;			
			$contact->goUserId = empty($r['go_user_id']) || !\go\core\model\User::findById($r['go_user_id'], ['id']) || Contact::findForUser($r['go_user_id'], ['id']) ? null : $r['go_user_id'];

			if ($r['photo']) {

				$file = GO()->getDataFolder()->getFile($r['photo']);
				if ($file->exists()) {
					$tmpFile = \go\core\fs\File::tempFile($file->getExtension());
					$file->copy($tmpFile);
					$blob = \go\core\fs\Blob::fromTmp($tmpFile);
					if (!$blob->save()) {
						throw new \Exception("Could not save blob");
					}

					$contact->photoBlobId = $blob->id;
				}
			}

			if (!$contact->save()) {
				GO()->debug($r);
				throw new \Exception("Could not save contact" . var_export($contact->getValidationErrors(), true));
			}
			
			if($r['company_id']) {				
				$orgId = $r['company_id'] + $this->getCompanyIdIncrement();
				
				$org = Contact::findById($orgId);
				if($org) {
					\go\core\model\Link::create($contact, $org);
				}
			}
		}
	}
	
	
	
	private function copyCompanies(AddressBook $addressBook) {
		$db = GO()->getDbConnection();		

		$contacts = $db->select()
		->from('ab_companies')
		->where(['addressbook_id' => $addressBook->id])
		->andWhere('id not in (select id + '.$this->getCompanyIdIncrement().' from addressbook_contact)');
		
		//continue where we left last time if failed.
		$max = $db->selectSingleValue('max(id)')->from("addressbook_contact")->andWhere(['addressBookId' => $addressBook->id])->single();
		if($max>0) {
			$contacts->andWhere('id', '>', $max - $this->getCompanyIdIncrement());
		}

		foreach ($contacts as $r) {
			$r = array_map("trim", $r);
			
			echo ".";
			flush();
			
			$contact = new Contact();
			$contact->isOrganization = true;
			$contact->id = $r['id'] + $this->getCompanyIdIncrement();
			$contact->addressBookId = $addressBook->id;
			$contact->name = $r['name'];		
			
			//name2 ??
			
			if (!empty($r['email'])) {
				$contact->emailAddresses[] = (new EmailAddress())
								->setValues([
						'type' => EmailAddress::TYPE_WORK,
						'email' => $r['email']
				]);
			}

			if (!empty($r['invoice_email'])) {
				$contact->emailAddresses[] = (new EmailAddress())
								->setValues([
						'type' => EmailAddress::TYPE_BILLING,
						'email' => $r['invoice_email']
				]);
			}
			

			if (!empty($r['phone'])) {
				$contact->phoneNumbers[] = (new PhoneNumber())
								->setValues([
						'type' => PhoneNumber::TYPE_WORK,
						'number' => $r['phone']
				]);
			}

			if (!empty($r['fax'])) {
				$contact->phoneNumbers[] = (new PhoneNumber())
								->setValues([
						'type' => PhoneNumber::TYPE_FAX,
						'number' => $r['fax']
				]);
			}

		
			if (!empty($r['homepage'])) {
				$contact->urls[] = (new Url())
								->setValues([
						'type' => Url::TYPE_HOMEPAGE,
						'url' => $r['homepage']
				]);
			}



			$address = new Address();
			$address->type = Address::TYPE_HOME;
			$address->countryCode = isset($r['country']) && \go\core\validate\CountryCode::validate(strtoupper($r['country'])) ? strtoupper($r['country']) : null;
			$address->state = $r['state'] ?? null;
			$address->city = $r['city'] ?? null;
			$address->zipCode = $r['zip'] ?? null;
			$address->street = $r['address'] ?? null;
			$address->street2 = $r['address_no'] ?? null;
			$address->latitude = $r['latitude'] ?? null;
			$address->longitude = $r['longitude'] ?? null;

			if ($address->isModified()) {				
				$contact->addresses[] = $address;
			}
			
			$address = new Address();
			$address->type = Address::TYPE_POSTAL;
			$address->countryCode = isset($r['post_country']) && \go\core\validate\CountryCode::validate(strtoupper($r['post_country'])) ? strtoupper($r['post_country']) : null;
			$address->state = $r['post_state'] ?? null;
			$address->city = $r['post_city'] ?? null;
			$address->zipCode = $r['post_zip'] ?? null;
			$address->street = $r['post_address'] ?? null;
			$address->street2 = $r['post_address_no'] ?? null;
			$address->latitude = $r['post_latitude'] ?? null;
			$address->longitude = $r['post_longitude'] ?? null;

			if ($address->isModified()) {
				$contact->addresses[] = $address;
			}

			$contact->notes = $r['comment'];

			$contact->filesFolderId = $r['files_folder_id'];

			$contact->createdAt = new DateTime("@" . $r['ctime']);
			$contact->modifiedAt = new DateTime("@" . $r['mtime']);
			$contact->createdBy = \go\core\model\User::findById($r['user_id'], ['id']) ? $r['user_id'] : 1;
			$contact->modifiedBy = \go\core\model\User::findById($r['muser_id'], ['id']) ? $r['muser_id'] : 1;
			
			$contact->IBAN = $r['bank_no'];
			
			//bank_bic???
			
			$contact->vatNo = $r['vat_no'];
			
							

			if ($r['photo']) {

				$file = GO()->getDataFolder()->getFile($r['photo']);
				if ($file->exists()) {
					$tmpFile = \go\core\fs\File::tempFile($file->getExtension());
					$file->copy($tmpFile);
					$blob = \go\core\fs\Blob::fromTmp($tmpFile);
					if (!$blob->save()) {
						throw new \Exception("Could not save blob");
					}

					$contact->photoBlobId = $blob->id;
				}
			}

			if (!$contact->save()) {
				
				GO()->debug($r);
				
				throw new \Exception("Could not save contact" . var_export($contact->getValidationErrors(), true));
			}
		}
	}
	
	public function addInitials() {

		if(!go()->getDatabase()->hasTable('ab_contacts')) {
			return;
		}

		go()->getDbConnection()
			->update("addressbook_contact", 
				[
					"initials" => (new Query)
						->select("initials")
						->from('ab_contacts', 'old')
						->where("old.id = t.id")
				])->execute();		
	}

}

