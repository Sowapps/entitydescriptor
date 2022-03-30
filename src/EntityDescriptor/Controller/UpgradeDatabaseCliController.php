<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Controller;

use Exception;
use Orpheus\EntityDescriptor\EntityDescriptor;
use Orpheus\EntityDescriptor\PermanentEntity;
use Orpheus\EntityDescriptor\SqlGenerator\SqlGeneratorMySql;
use Orpheus\InputController\CliController\CliController;
use Orpheus\InputController\CliController\CliRequest;
use Orpheus\InputController\CliController\CliResponse;
use Orpheus\SqlAdapter\SqlAdapter;

class UpgradeDatabaseCliController extends CliController {
	
	/**
	 * @param CliRequest $request The input CLI request
	 * @return CliResponse
	 * @throws Exception
	 */
	public function run($request): CliResponse {
		$generator = new SqlGeneratorMySql();
		$query = '';
		/** @var PermanentEntity $entityClass */
		foreach( PermanentEntity::listKnownEntities() as $entityClass ) {
			$entityDescriptor = EntityDescriptor::load($entityClass::getTable(), $entityClass);
			$entityQuery = strip_tags($generator->matchEntity($entityDescriptor, $entityClass::getSqlAdapter()));
			if( $entityQuery ) {
				$query .= ($query ? "\n\n" : '') . $entityQuery;
			}
		}
		
		if( !$query ) {
			return new CliResponse(0, 'No changes');
		}
		
		$this->printLine(sprintf("Available changes:
%s\n", $query));
		
		$answer = $this->requestInputLine('Do you want to apply changes ? [Y/n] ', false);
		
		if( $answer && strtolower($answer) !== 'y' ) {
			return new CliResponse(0, 'Aborting changes');
		}
		echo 'Applying changes... ';
		
		$defaultAdapter = SqlAdapter::getInstance();
		$defaultAdapter->query($query, PDOEXEC);
		
		$this->printLine('Done!');
		
		return new CliResponse(0, 'All changes were applied.');
	}
	
	
}
