<?php

/**
 * @file tests/classes/core/PKPRouterTest.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPRouterTest
 * @ingroup tests
 * @see PKPRouter
 *
 * @brief Tests for the PKPRouter class.
 */

import('tests.PKPTestCase');
import('core.PKPRouter');
import('core.PKPRequest');
import('plugins.HookRegistry'); // This imports a mock HookRegistry implementation.
import('core.PKPApplication');
import('db.DAORegistry');

class PKPRouterTest extends PKPTestCase {
	const
		PATHINFO_ENABLED = true,
		PATHINFO_DISABLED = false;

	protected
		$router,
		$request;

	protected function setUp() {
		$this->router = new PKPRouter();
	}

	/**
	 * @covers PKPRouter::getApplication
	 * @covers PKPRouter::setApplication
	 */
	public function testGetSetApplication() {
		$application = $this->_setUpMockEnvironment();
		self::assertSame($application, $this->router->getApplication());
	}

	/**
	 * @covers PKPRouter::getDispatcher
	 * @covers PKPRouter::setDispatcher
	 */
	public function testGetSetDispatcher() {
		$application = $this->_setUpMockEnvironment();
		$dispatcher = $application->getDispatcher();
		self::assertSame($dispatcher, $this->router->getDispatcher());
	}

	/**
	 * @covers PKPRouter::supports
	 */
	public function testSupports() {
		$this->request = new PKPRequest();
		self::assertTrue($this->router->supports($this->request));
	}

	/**
	 * @covers PKPRouter::isCacheable
	 */
	public function testIsCacheable() {
		$this->request = new PKPRequest();
		self::assertFalse($this->router->isCacheable($this->request));
	}

	/**
	 * @covers PKPRouter::getCacheFilename
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testGetCacheFilename() {
		$this->request = new PKPRequest();
		$this->router->getCacheFilename($this->request);
	}

	/**
	 * @covers PKPRouter::getRequestedContextPath
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function testGetRequestedContextPathWithInvalidLevel() {
		// Context depth = 1 but we try to access context level 2
		$this->_setUpMockEnvironment(self::PATHINFO_ENABLED, 1, array('oneContext'));
		$this->router->getRequestedContextPath($this->request, 2);
	}

	/**
	 * @covers PKPRouter::getRequestedContextPath
	 */
	public function testGetRequestedContextPathWithEmptyPathInfo() {
		$this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
		$_SERVER['PATH_INFO'] = null;
		self::assertEquals(array('index', 'index'),
				$this->router->getRequestedContextPath($this->request));
	}

	/**
	 * @covers PKPRouter::getRequestedContextPath
	 */
	public function testGetRequestedContextPathWithFullPathInfo() {
		$this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
		HookRegistry::resetCalledHooks();
		$_SERVER['PATH_INFO'] = '/context1/context2/other/path/vars';
		self::assertEquals(array('context1', 'context2'),
				$this->router->getRequestedContextPath($this->request));
		self::assertEquals(array('context1'),
				$this->router->getRequestedContextPath($this->request, 1));
		self::assertEquals(array('context2'),
				$this->router->getRequestedContextPath($this->request, 2));
		self::assertEquals('context1',
				$this->router->getRequestedContextPath($this->request, 1, false));
		self::assertEquals('context2',
				$this->router->getRequestedContextPath($this->request, 2, false));
		self::assertEquals(
			array(array('Router::getRequestedContextPath', array(array('context1', 'context2')))),
			HookRegistry::getCalledHooks()
		);
	}

	/**
	 * @covers PKPRouter::getRequestedContextPath
	 */
	public function testGetRequestedContextPathWithPartialPathInfo() {
		$this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
		$_SERVER['PATH_INFO'] = '/context';
		self::assertEquals(array('context', 'index'),
				$this->router->getRequestedContextPath($this->request));
	}

	/**
	 * @covers PKPRouter::getRequestedContextPath
	 */
	public function testGetRequestedContextPathWithInvalidPathInfo() {
		$this->_setUpMockEnvironment(self::PATHINFO_ENABLED);
		$_SERVER['PATH_INFO'] = '/context:?#/';
		self::assertEquals(array('context', 'index'),
				$this->router->getRequestedContextPath($this->request));
	}

	/**
	 * @covers PKPRouter::getRequestedContextPath
	 */
	public function testGetRequestedContextPathWithEmptyContextParameters() {
		$this->_setUpMockEnvironment(self::PATHINFO_DISABLED);
		$_GET['firstContext'] = null;
		$_GET['secondContext'] = null;
		self::assertEquals(array('index', 'index'),
				$this->router->getRequestedContextPath($this->request));
	}

	/**
	 * @covers PKPRouter::getRequestedContextPath
	 */
	public function testGetRequestedContextPathWithFullContextParameters() {
		$this->_setUpMockEnvironment(self::PATHINFO_DISABLED);
		HookRegistry::resetCalledHooks();
		$_GET['firstContext'] = 'context1';
		$_GET['secondContext'] = 'context2';
		self::assertEquals(array('context1', 'context2'),
				$this->router->getRequestedContextPath($this->request));
		self::assertEquals(array('context1'),
				$this->router->getRequestedContextPath($this->request, 1));
		self::assertEquals(array('context2'),
				$this->router->getRequestedContextPath($this->request, 2));
		self::assertEquals(
			array(array('Router::getRequestedContextPath', array(array('context1', 'context2')))),
			HookRegistry::getCalledHooks()
		);
	}

	/**
	 * @covers PKPRouter::getRequestedContextPath
	 */
	public function testGetRequestedContextPathWithPartialContextParameters() {
		$this->_setUpMockEnvironment(self::PATHINFO_DISABLED);
		$_GET['firstContext'] = 'context';
		self::assertEquals(array('context', 'index'),
				$this->router->getRequestedContextPath($this->request));
	}

	/**
	 * @covers PKPRouter::getContext
	 * @covers PKPRouter::getContextByName
	 * @covers PKPRouter::_contextLevelToContextName
	 * @covers PKPRouter::_contextNameToContextLevel
	 */
	public function testGetContext() {
		// We use a 1-level context
		$this->_setUpMockEnvironment(true, 1, array('someContext'));
		$_SERVER['PATH_INFO'] = '/contextPath';

		// Simulate a context DAO
		$mockDAO = $this->getMock('SomeContextDAO', array('getSomeContextByPath'));
		DAORegistry::registerDAO('SomeContextDAO', $mockDAO);

	    // Set up the mock DAO get-by-path method which
	    // should be called with the context path from
	    // the path info.
		$expectedResult = $this->getMock('SomeContext');
		$mockDAO->expects($this->once())
		        ->method('getSomeContextByPath')
		        ->with('contextPath')
		        ->will($this->returnValue($expectedResult));
		$result = $this->router->getContext($this->request, 1);
		self::assertType('SomeContext', $result);
		self::assertEquals($expectedResult, $result);

		$resultByName = $this->router->getContextByName($this->request, 'someContext');
		self::assertType('SomeContext', $resultByName);
		self::assertEquals($expectedResult, $resultByName);
	}

	/**
	 * @covers PKPRouter::getContext
	 * @covers PKPRouter::getContextByName
	 */
	public function testGetContextForIndex() {
		// We use a 1-level context
		$this->_setUpMockEnvironment(true, 1, array('someContext'));
		$_SERVER['PATH_INFO'] = '/';

		$result = $this->router->getContext($this->request, 1);
		self::assertNull($result);

		$resultByName = $this->router->getContextByName($this->request, 'someContext');
		self::assertNull($resultByName);
	}

	/**
	 * @covers PKPRouter::getIndexUrl
	 */
	public function testGetIndexUrl() {
		$this->_setUpMockEnvironment();
		$this->setTestConfiguration('request1', 'classes/core/config', false); // no restful URLs
		$_SERVER = array(
			'HOSTNAME' => 'mydomain.org',
			'SCRIPT_NAME' => '/base/index.php'
		);
		HookRegistry::resetCalledHooks();

		self::assertEquals('http://mydomain.org/base/index.php', $this->router->getIndexUrl($this->request));

		// Several hooks should have been triggered.
		self::assertEquals(
			array(
				array('Request::getServerHost', array('mydomain.org')),
				array('Request::getProtocol', array('http')),
				array('Request::getBasePath', array('/base')),
				array('Request::getBaseUrl', array('http://mydomain.org/base')),
				array('Router::getIndexUrl' , array('http://mydomain.org/base/index.php'))
			),
			HookRegistry::getCalledHooks()
		);

		// Calling getIndexUrl() twice should return the same
		// result without triggering the hooks again.
		HookRegistry::resetCalledHooks();
		self::assertEquals('http://mydomain.org/base/index.php', $this->router->getIndexUrl($this->request));
		self::assertEquals(
			array(),
			HookRegistry::getCalledHooks()
		);
	}

	/**
	 * @covers PKPRouter::getIndexUrl
	 */
	public function testGetIndexUrlRestful() {
		$this->_setUpMockEnvironment();
		$this->setTestConfiguration('request2', 'classes/core/config', false); // restful URLs
		$_SERVER = array(
			'HOSTNAME' => 'mydomain.org',
			'SCRIPT_NAME' => '/base/index.php'
		);

		self::assertEquals('http://mydomain.org/base', $this->router->getIndexUrl($this->request));
	}

	/**
	 * Set's up a mock environment for router tests (PKPApplication,
	 * PKPRequest) with customizable contexts and path info flag.
	 * @param $pathInfoEnabled boolean
	 * @param $contextDepth integer
	 * @param $contextList array
	 * @return unknown
	 */
	protected function _setUpMockEnvironment($pathInfoEnabled = self::PATHINFO_ENABLED,
			$contextDepth = 2, $contextList = array('firstContext', 'secondContext')) {
		// Mock application object without calling its constructor.
		$mockApplication =
				$this->getMock('PKPApplication', array('getContextDepth', 'getContextList'),
				array(), '', false);

		// Set up the getContextDepth() method
		$mockApplication->expects($this->any())
		                ->method('getContextDepth')
		                ->will($this->returnValue($contextDepth));

		// Set up the getContextList() method
		$mockApplication->expects($this->any())
		                ->method('getContextList')
		                ->will($this->returnValue($contextList));

		$this->router->setApplication($mockApplication);

		// Dispatcher
		$dispatcher =& $mockApplication->getDispatcher();
		$this->router->setDispatcher($dispatcher);

		// Mock request
		$this->request = $this->getMock('PKPRequest', array('isPathInfoEnabled'));
		$this->request->setRouter($this->router);
		$this->request->expects($this->any())
		              ->method('isPathInfoEnabled')
		              ->will($this->returnValue($pathInfoEnabled));

		return $mockApplication;
	}

	/**
	 * Create two mock DAOs "FirstContextDAO" and "SecondContextDAO" that can be
	 * used with the standard environment set up when calling self::_setUpMockEnvironment().
	 * Both DAOs will be registered with the DAORegistry and thereby be made available
	 * to the router.
	 * @param $firstContextPath string
	 * @param $secondContextPath string
	 * @param $firstContextIsNull boolean
	 * @param $secondContextIsNull boolean
	 */
	protected function _setUpMockDAOs($firstContextPath = 'current-context1', $secondContextPath = 'current-context2', $firstContextIsNull = false, $secondContextIsNull = false) {
		$mockFirstContextDAO = $this->getMock('FirstContextDAO', array('getFirstContextByPath'));
		if (!$firstContextIsNull) {
			$firstContextInstance = $this->getMock('FirstContext', array('getPath'));
			$firstContextInstance->expects($this->any())
			                     ->method('getPath')
			                     ->will($this->returnValue($firstContextPath));
			$mockFirstContextDAO->expects($this->any())
			                    ->method('getFirstContextByPath')
			                    ->with($firstContextPath)
			                    ->will($this->returnValue($firstContextInstance));
		}
		DAORegistry::registerDAO('FirstContextDAO', $mockFirstContextDAO);

		$mockSecondContextDAO = $this->getMock('SecondContextDAO', array('getSecondContextByPath'));
		if (!$secondContextIsNull) {
			$secondContextInstance = $this->getMock('SecondContext', array('getPath'));
			$secondContextInstance->expects($this->any())
			                      ->method('getPath')
			                      ->will($this->returnValue($secondContextPath));
			$mockSecondContextDAO->expects($this->any())
			                     ->method('getSecondContextByPath')
			                     ->with($secondContextPath)
			                     ->will($this->returnValue($secondContextInstance));
		}
		DAORegistry::registerDAO('SecondContextDAO', $mockSecondContextDAO);
	}
}
?>
