<?php

namespace Orpheus\EntityDescriptor\Controller;

use Orpheus\EntityDescriptor\EntityDescriptor;
use Orpheus\EntityDescriptor\SQLGenerator\SQLGeneratorMySQL;
use Orpheus\InputController\CLIController\CLIController;
use Orpheus\InputController\CLIController\CLIRequest;
use Orpheus\InputController\CLIController\CLIResponse;

/**
 * Class UpgradeDatabaseCliController
 * Controller to upgrade database using cli
 *
 */
class UpgradeDatabaseCliController extends CLIController {
	
	/**
	 * @param CLIRequest $request The input CLI request
	 * @return CLIResponse
	 */
	public function run($request) {
		
		$generator = new SQLGeneratorMySQL();
		
		$query = '';
		foreach( EntityDescriptor::getAllEntities() as $entity ) {
			$query .= strip_tags($generator->matchEntity(EntityDescriptor::load($entity)));
		}
		
		if( !$query ) {
			return new CLIResponse(0, 'No changes');
		}
		
		$this->printLine("Available changes:
{$query}\n");
		
		$answer = $this->requestInputLine('Do you want to apply changes ? [Y/n] ', false);
		
		if( $answer && strtolower($answer) !== 'y' ) {
			return new CLIResponse(0, 'Aborting changes');
		}
		echo 'Applying changes... ';
		
		pdo_query($query, PDOEXEC);
		
		$this->printLine('Done!');
		
		return new CLIResponse(0, 'All changes were applied.');
	}
	
	
}
