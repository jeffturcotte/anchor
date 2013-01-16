<?php

class AnchorTest extends PHPUnit_Framework_TestCase {
	public static function setUpBeforeClass() {
		require 'Anchor.php';

		Anchor::authorize('AuthorizedController');
		Anchor::authorize('Controller');

		Anchor::hook('init',            '*', 'TestController::init');
		Anchor::hook('before',          '*', 'TestController::before');
		Anchor::hook('after',           '*', 'TestController::after');
		Anchor::hook('catch Exception', '*', 'TestController::catchException');
		Anchor::hook('finish',          '*', 'TestController::finish');

		Anchor::add('/', 'TestController::home');
		Anchor::add('/:class/^id/:method', '*::*');
		Anchor::add('/:class/^id-:slug/:method', '*::*');
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

	public function testLink() {
		$this->assertEquals(
			'/',
			Anchor::link('TestController::home')
		);
		$this->assertEquals(
			'/test_controller/5/dynamic',
			Anchor::link('TestController::dynamic id', 5)
		);
		$this->assertEquals(
			'/test_controller/5-this_is_the_slug/dynamic',
			Anchor::link('TestController::dynamic id slug', 5, 'this_is_the_slug')
		);
	}

	/*public function testHooks() {
		$data = Anchor::call('AuthorizedController::index');
		print_r($data);
		$this->assertEquals('ibaf', $data->test);
	}*/

	public function testFormat() {
		$data = Anchor::call('AuthorizedController::index');
		$this->assertEquals('AuthorizedController', $data->active_class);
		$this->assertEquals('AuthorizedController', $data->active_short_class);
		$this->assertEquals('AuthorizedController::index', $data->active_method);
		$this->assertEquals('index', $data->active_short_method);
		$this->assertEquals('authorized_controller/index', $data->active_full_path);
		$this->assertEquals('authorized_controller', $data->active_class_path);

	}
}

class UnauthorizedController {
	public function index() {}
}

class AuthorizedController {
	public function index($data) {
		$data->active_class = Anchor::format('%c');
		$data->active_short_class = Anchor::format('%C');
		$data->active_method = Anchor::format('%m');
		$data->active_short_method = Anchor::format('%M');
		$data->active_full_path = Anchor::format('%p');
		$data->active_class_path = Anchor::format('%P');
	}

	public function throwException() {
		throw new Exception();
	}

	public function throwExtendingException() {
		throw new ExtendingException();
	}

	public static function catchException() {
		$data->test = 'e';
	}

	public static function init($data) {
		$data->test = 'i';
	}

	public static function before($data) {
		$data->test .= 'b';
	}

	public static function after($data) {
		$data->test .= 'a';
	}

	public static function finish($data) {
		print_r("data is now $data\n");
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

class ExtendingException extends Exception {}
