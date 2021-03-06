<?php

/**
 * @file pages/index/IndexHandler.inc.php
 *
 * Copyright (c) 2005-2012 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package pages.index
 * @class IndexHandler
 *
 * Handle site index requests.
 *
 */



import('classes.handler.Handler');

class IndexHandler extends Handler {
	function index($args, &$request) {
		$this->setupTemplate($request);
		$this->validate();
		$templateMgr =& TemplateManager::getManager();
		$site =& $request->getSite();

		$templateMgr->assign('intro', $site->getLocalizedIntro());
		$templateMgr->assign('title', $site->getLocalizedTitle());

		$archiveDao = DAORegistry::getDAO('ArchiveDAO');
		$templateMgr->assign('archiveCount', $archiveDao->getArchiveCount());

		$recordDao = DAORegistry::getDAO('RecordDAO');
		$templateMgr->assign('recordCount', $recordDao->getRecordCount());

		$templateMgr->assign('enableSubmit', $site->getSetting('enableSubmit'));

		$templateMgr->display('index/index.tpl');
	}

	/**
	 * Change the locale for the current user.
	 * @param $args array first parameter is the new locale
	 */
	function setLocale($args, &$request) {
		$setLocale = isset($args[0]) ? $args[0] : null;

		$site =& $request->getSite();

		if (AppLocale::isLocaleValid($setLocale) && in_array($setLocale, $site->getSupportedLocales())) {
			$session =& $request->getSession();
			$session->setSessionVar('currentLocale', $setLocale);
		}

		if(isset($_SERVER['HTTP_REFERER'])) {
			$request->redirectUrl($_SERVER['HTTP_REFERER']);
		}

		$source = $request->getUserVar('source');
		if (isset($source) && !empty($source)) {
			$request->redirectUrl($request->getProtocol() . '://' . $request->getServerHost() . $source, false);
		}

		$request->redirect('index');		
	}
}

?>
