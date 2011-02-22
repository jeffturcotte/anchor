<?php
class AnchorTest extends PHPUnit_Framework_TestCase {
	public static function setUpBeforeClass() {		
		require 'Anchor.php';
		
		Anchor::authorize('AuthorizedController');
		Anchor::authorize('Controller');
		
		Anchor::hook('init',   '*::*', 'TestController::init');
		Anchor::hook('before', '*::*', 'TestController::before');
		Anchor::hook('after',  '*::*', 'TestController::after');
		Anchor::hook('final',  '*::*', 'TestController::_final');
		
		Anchor::add('/', 'TestController::home');
		Anchor::add('/:class/:id/:method', '*::*');
		Anchor::add('/:class/%id-:slug/:method', '*::*');
	}
	
	public function testAuthorization() {
		$this->assertInternalType('object', Anchor::call('AuthorizedController::index'));
		$this->assertInternalType('object', Anchor::call('TestController::index'));
		$this->assertInternalType('object', Anchor::call('TestController::allowed'));
		$this->assertFalse(Anchor::call('UnauthorizedController::index'));
	}
	
	public function testResolve() {
		$this->assertEquals(
			'TestController::home', 
			Anchor::resolve('/')
		);
		$this->assertEquals(
			'TestController::dynamic', 
			Anchor::resolve('/test_controller/5/dynamic')
		);
		$this->assertEquals(
			'TestController::dynamic', 
			Anchor::resolve('/test_controller/5-here_is_the_slug/dynamic')
		);
	}
	
	public function testCall() {
		$this->assertInternalType('object', Anchor::call('TestController::allowed'));
		$this->assertFalse(Anchor::call('TestController::notAllowedPrivate'));
		$this->assertFalse(Anchor::call('TestController::notAllowedStatic'));
		$this->assertFalse(Anchor::call('TestController::__notAllowedMagic'));
	}
	
	public function testFind() {
		$this->assertEquals(
			'/', 
			Anchor::find('TestController::home')
		);
		$this->assertEquals(
			'/test_controller/5/dynamic', 
			Anchor::find('TestController::dynamic id', 5)
		);
		$this->assertEquals(
			'/test_controller/5-this_is_the_slug/dynamic', 
			Anchor::find('TestController::dynamic id slug', 5, 'this_is_the_slug')
		);
	}
	
	public function testHooks() {
		$data = Anchor::call('AuthorizedController::index');
		$this->assertEquals('ibaf', $data->test);
	}
}

class UnauthorizedController {
	public function index() {}
}

class AuthorizedController {
	public function index() {}
	
	public static function init($data) {
		$data->test = 'i';
	}
	public static function before($data) {
		$data->test .= 'b';
	}
	public static function after($data) {
		$data->test .= 'a';
	}
	public static function _final($data) {
		$data->test .= 'f';
	}
}

class TestController extends AuthorizedController {
	public function allowed() {}
	private function notAllowedPrivate() {}
	public static function notAllowedStatic() {}
	public function __notAllowedMagic() {}
	
	public function home() {}
	public function dynamic() {}
}