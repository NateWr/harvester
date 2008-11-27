<?php

/**
 * @file OAIHandler.inc.php
 *
 * Copyright (c) 2005-2008 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OAIHandler
 * @ingroup pages_oai
 *
 * @brief Handle OAI protocol requests. 
 */

// $Id$


define('SESSION_DISABLE_INIT', 1); // FIXME?

import('oai.harvester.ArchiveOAI');
import('core.PKPHandler');

class OAIHandler extends PKPHandler {
	function index() {
		OAIHandler::validate();
		PluginRegistry::loadCategory('schemas', true);

		$oai = new ArchiveOAI(new OAIConfig(Request::getRequestUrl(), Config::getVar('oai', 'repository_id')));
		$oai->execute();
	}

	function validate() {
		// Site validation checks not applicable
		//parent::validate();

		if (!Config::getVar('oai', 'oai')) {
			Request::redirect('index');
		}
	}
}

?>