<?php

/**
 * @file ZendSearchHandler.inc.php
 *
 * Copyright (c) 2005-2012 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SendSearchHandler
 * @ingroup plugins_generic_zendSearch
 *
 * @brief Handle requests for search functions
 */



import('classes.handler.Handler');

class ZendSearchHandler extends Handler {
	/**
	 * Display search form
	 */
	function index($args, &$request) {
		$this->setupTemplate($request);
		$plugin =& PluginRegistry::getPlugin('generic', ZEND_SEARCH_PLUGIN_NAME);

		$searchFormElementDao = DAORegistry::getDAO('SearchFormElementDAO');
		$searchFormElements =& $searchFormElementDao->getSearchFormElements();

		$templateMgr =& TemplateManager::getManager($request);
		$templateMgr->assign_by_ref('searchFormElements', $searchFormElements);
		$templateMgr->display($plugin->getTemplatePath() . 'search.tpl');
	}

	function luceneEscape($str) {
		return str_replace(array('+', '-', '&', '|', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':', '\\'), array('\\+', '\\-', '\\&', '\\|', '\\!', '\\(', '\\)', '\\{', '\\}', '\\[', '\\]', '\\^', '\\"', '\\~', '\\*', '\\?', '\\:', '\\\\'), $str);
	}

	/**
	 * Display search results.
	 */
	function searchResults($args, &$request) {
		ZendSearchHandler::setupTemplate($request);
		$plugin =& PluginRegistry::getPlugin('generic', ZEND_SEARCH_PLUGIN_NAME);
		$isUsingSolr = $plugin->isUsingSolr();

		if ($isUsingSolr) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $plugin->getSetting('solrUrl') . '/select');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_ENCODING, '');
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			$query = '';
		} else {
			$index =& $plugin->getIndex();
			$query = new Zend_Search_Lucene_Search_Query_Boolean();
		}

		$q = $request->getUserVar('q');
		if (!empty($q)) {
			if ($isUsingSolr) {
				$query .= 'text:"' . ZendSearchHandler::luceneEscape($q) . '" ';
			} else {
				$query->addSubquery(Zend_Search_Lucene_Search_QueryParser::parse($q));
			}
		}

		$searchFormElementDao = DAORegistry::getDAO('SearchFormElementDAO');
		$searchFormElements =& $searchFormElementDao->getSearchFormElements();
		while ($searchFormElement =& $searchFormElements->next()) {
			$searchFormElementId = $searchFormElement->getSearchFormElementId();
			$symbolic = $searchFormElement->getSymbolic();
			switch ($searchFormElement->getType()) {
				case SEARCH_FORM_ELEMENT_TYPE_SELECT:
				case SEARCH_FORM_ELEMENT_TYPE_STRING:
					$term = $request->getUserVar($symbolic);
					if (!empty($term)) {
						if ($isUsingSolr) {
							$query .= $symbolic . ':"' . ZendSearchHandler::luceneEscape($term) . '" ';
						} else {
							$query->addSubquery(new Zend_Search_Lucene_Search_Query_Term(new Zend_Search_Lucene_Index_Term($term, $symbolic)), true);
						}
					}
					break;
				case SEARCH_FORM_ELEMENT_TYPE_DATE:
					$from = $request->getUserDateVar($symbolic . '-from');
					$to = $request->getUserDateVar($symbolic . '-to');
					if (!empty($from) && !empty($to)) {
						if ($isUsingSolr) {
							$query .= $symbolic . ':[' . strftime('%Y-%m-%dT%H:%M:%SZ', $from) . ' TO ' . strftime('%Y-%m-%dT%H:%M:%SZ', $to) . '] ';
						} else {
							$fromTerm = new Zend_Search_Lucene_Index_Term($from, $symbolic);
							$toTerm = new Zend_Search_Lucene_Index_Term($to, $symbolic);
							$query->addSubquery(new Zend_Search_Lucene_Search_Query_Range($fromTerm, $toTerm, true), true);
						}
					}
					break;
				default:
					fatalError('Unknown element type!');
			}
			unset($searchFormElement);
		}

		$rangeInfo = $this->getRangeInfo($request, 'results');

		if ($isUsingSolr) {
			$itemsPerPage = Config::getVar('interface', 'items_per_page');
			curl_setopt(
				$ch, CURLOPT_POSTFIELDS,
				'q=' . trim(urlencode($query)) .
				'&rows=' . urlencode($itemsPerPage) .
				($rangeInfo?('&start=' . ($rangeInfo->getPage() * $itemsPerPage)):'')
			);
			$data = curl_exec($ch);
			$xmlParser = new XMLParser();
			$result = null;
			$numFound = 0;
			@$result =& $xmlParser->parseTextStruct($data, array('str', 'result'));
			$recordIds = array();
			if ($result) foreach ($result as $nodeSet) foreach ($nodeSet as $node) {
				if (isset($node['attributes']['name']) && $node['attributes']['name'] == 'id') {
					$recordIds[] = $node['value'];
				} elseif (isset($node['attributes']['numFound'])) {
					$numFound = $node['attributes']['numFound'];
				}
			}
			$plugin->import('SolrResultIterator');
			$resultsIterator =& SolrResultIterator::fromRangeInfo($recordIds, $numFound, $rangeInfo);
			unset($recordIds);
		} else {
			$resultsArray = $index->find($query);
			$plugin->import('ZendSearchResultIterator');
			$resultsIterator =& ZendSearchResultIterator::fromRangeInfo($resultsArray, $rangeInfo);
			unset($resultsArray);
		}

		$templateMgr =& TemplateManager::getManager($request);
		$templateMgr->assign_by_ref('recordDao', DAORegistry::getDAO('RecordDAO'));
		$templateMgr->assign_by_ref('results', $resultsIterator);
		$templateMgr->assign_by_ref('q', $q);
		$templateMgr->display($plugin->getTemplatePath() . 'results.tpl');
	}

	/**
	 * Setup common template variables.
	 */
	function setupTemplate($request) {
		parent::setupTemplate($request);
		parent::validate();

		$templateMgr =& TemplateManager::getManager($request);
		$templateMgr->assign('pageHierachy', array(
			array($request->url('search'), 'navigation.search')
		));
	}
}

?>
