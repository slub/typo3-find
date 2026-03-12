<?php

namespace Subugoe\Find\Controller;

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2013
 *      Ingo Pfennigstorf <pfennigstorf@sub-goettingen.de>
 *      Sven-S. Porst
 *      Göttingen State and University Library
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Core\Utility\HttpUtility;
use Subugoe\Find\Service\ServiceProviderInterface;
use Subugoe\Find\Utility\ArrayUtility;
use Subugoe\Find\Utility\FrontendUtility;
use TYPO3\CMS\Core\Log\LogManagerInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility as CoreArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class SearchController extends ActionController
{
    protected array $requestArguments = [];

    protected ?object $searchProvider = null;

    private LoggerInterface $logger;

    public function __construct(LogManagerInterface $logManager)
    {
        $this->logger = $logManager->getLogger('find');
    }

    /**
     * @throws NoSuchArgumentException
     * @throws StopActionException
     */
    public function detailAction(string $id): ResponseInterface
    {
        $arguments = $this->searchProvider->getRequestArguments();
        $detail = $this->searchProvider->getDocumentById($id);

        if ($arguments['underlyingQuery']) {
            $underlyingQueryInfo = $arguments['underlyingQuery'];
            FrontendUtility::addQueryInformationAsJavaScript(
                $underlyingQueryInfo['q'],
                $this->settings,
                (int) $underlyingQueryInfo['position'],
                $arguments
            );
        }

        $this->addStandardAssignments();

        $this->view->assignMultiple($detail);
        $this->view->assignMultiple([
            'arguments' => $arguments,
            'config' => $this->searchProvider->getConfiguration()
        ]);
        return $this->htmlResponse();
    }

    /**
	 * Citation Action.
	 */
	public function citationAction(): ResponseInterface {

		$arguments = $this->requestArguments;
        $detail = $this->searchProvider->getDocumentById($arguments["id"]);

        $this->addStandardAssignments();
        
        $this->view->assignMultiple($detail);
        $this->view->assignMultiple([
            'arguments' => $arguments,
            'config' => $this->searchProvider->getConfiguration(),
            'type' => $arguments['type']
        ]);
        return $this->htmlResponse();
	}

    /**
     * Index Action.
     */
    public function indexAction(): ResponseInterface
    {
        if (array_key_exists('id', $this->requestArguments)) {
            return new ForwardResponse('detail');
        } elseif (array_key_exists('rsn', $this->requestArguments)) {
			return new ForwardResponse('redirect');
		} elseif (array_key_exists('bc', $this->requestArguments)) {
			return new ForwardResponse('redirect');
		} elseif (array_key_exists('ppn', $this->requestArguments)) {
			return new ForwardResponse('redirect');
        } elseif (array_key_exists('oclc', $this->requestArguments)) {
			return new ForwardResponse('redirect');
        } else {
            $this->searchProvider->setCounter();
            FrontendUtility::addQueryInformationAsJavaScript(
                $this->searchProvider->getRequestArguments()['q'],
                $this->settings,
                null,
                $this->searchProvider->getRequestArguments()
            );

            $this->addStandardAssignments();
            $defaultQuery = $this->searchProvider->getDefaultQuery();

            // Decode facet keys for display
            $arguments = $this->searchProvider->getRequestArguments();
            if (isset($arguments['facet']) && is_array($arguments['facet'])) {
                foreach ($arguments['facet'] as $facetId => $facetTerms) {
                    if (is_array($facetTerms)) {
                        $decodedTerms = [];
                        foreach ($facetTerms as $term => $value) {
                            $decodedTerm = urldecode($term);
                            $decodedTerms[$decodedTerm] = $value;
                        }
                        $arguments['facet'][$facetId] = $decodedTerms;
                    }
                }
            }

            $viewValues = [
                'arguments' => $arguments,
                'config' => $this->searchProvider->getConfiguration(),
            ];

            CoreArrayUtility::mergeRecursiveWithOverrule($viewValues, $defaultQuery);
            $this->view->assignMultiple($viewValues);

            // if there are no search parameters provided, redirect to the URL given in setting 'nosearchRedirect'
            if ($defaultQuery['noSearch']) {
                if(strlen($this->settings['nosearchRedirect']) > 0) {
                    HttpUtility::redirect($this->settings['nosearchRedirect']);
                }
            }
        }
        return $this->htmlResponse();
    }

    /**
	 * Redirect View to detail action.
	 */
	public function redirectAction() {
		$queryArguments = ['q' => []];
        $queryArgumentsDefault = '';

		if (array_key_exists('rsn', $this->requestArguments)) {
			$queryArguments['q']['rsn'] = $this->requestArguments['rsn'];
            $queryArgumentsDefault = $this->requestArguments['rsn'];
		} elseif (array_key_exists('bc', $this->requestArguments)) {
			$queryArguments['q']['barcode'] = $this->requestArguments['bc'];
            $queryArgumentsDefault = $this->requestArguments['bc'];
		} elseif (array_key_exists('ppn', $this->requestArguments)) {
			$queryArguments['q']['ppn'] = $this->requestArguments['ppn'];
            $queryArgumentsDefault = $this->requestArguments['ppn'];
		} elseif (array_key_exists('oclc', $this->requestArguments)) {
			$queryArguments['q']['oclc'] = $this->requestArguments['oclc'];
            $queryArgumentsDefault = $this->requestArguments['oclc'];
		}

        $selectResults =$this->searchProvider->search($queryArguments);

		if (count($selectResults) === 1) {
			$resultSet = $selectResults->getDocuments();

			$arguments = [
				'tx_find_find' => [
					'action' => 'detail',
					'controller' => 'Search',
					'id' => $resultSet[0]['id']
                ]
			];
		} else {
            $arguments = [
				'tx_find_find' => [
					'action' => 'index',
					'controller' => 'Search',
                    'q' => [
                        'default' => $queryArgumentsDefault
					]
				]
			];
		}

        $uri = $this->uriBuilder->reset()->setTargetPageUid(intval($GLOBALS['TSFE']->id))->setCreateAbsoluteUri(true)->setArguments($arguments)->build();
        HttpUtility::redirect($uri);

		die();
	}

    /**
     * Initialisation and setup.
     */
    protected function initializeAction()
    {
        ksort($this->settings['queryFields']);

        $this->initializeConnection($this->settings['activeConnection']);

        $this->requestArguments = $this->request->getArguments();
        $this->requestArguments = ArrayUtility::cleanArgumentsArray($this->requestArguments);

        $this->searchProvider->setRequestArguments($this->requestArguments);
        $this->searchProvider->setAction($this->request->getControllerActionName());
        $this->searchProvider->setControllerExtensionKey($this->request->getControllerExtensionKey());
    }

    /**
     * Suggest/Autocomplete action.
     */
    public function suggestAction(): ResponseInterface
    {
        $results = $this->searchProvider->suggestQuery($this->searchProvider->getRequestArguments());
        $this->view->assign('suggestions', $results);
        return $this->htmlResponse();
    }

    /**
     * Query indexed terms for given fields.
     */
    public function termAction(): ResponseInterface{
        $results = $this->searchProvider->getTerms($this->searchProvider->getRequestArguments());
        $this->view->assign('terms', $results);
        return $this->htmlResponse();
    }

    /**
     * Assigns standard variables to the view.
     */
    protected function addStandardAssignments()
    {
        $this->searchProvider->setConfigurationValue('extendedSearch', $this->searchProvider->isExtendedSearch());
        $this->searchProvider->setConfigurationValue(
            'uid',
            $this->configurationManager->getContentObject()->data['uid']
        );
        $this->searchProvider->setConfigurationValue('prefixID', 'tx_find_find');
        $this->searchProvider->setConfigurationValue('pageTitle', $GLOBALS['TSFE']->page['title']);
        $this->searchProvider->setConfigurationValue('language', $GLOBALS['TSFE']->config['config']['language']);
    }

    /**
     * @param string $activeConnection
     */
    protected function initializeConnection($activeConnection)
    {
        $connectionConfiguration = $this->settings['connections'][$activeConnection];

        /* @var ServiceProviderInterface $searchProvider */
        $this->searchProvider = GeneralUtility::makeInstance($connectionConfiguration['provider']);
        $this->searchProvider->initialize($activeConnection, $this->settings);
        $this->searchProvider->connect();
    }
}
