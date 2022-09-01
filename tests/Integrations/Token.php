<?php
/**
 * Token integrations test file.
 *
 * @package   Tests
 *
 * @copyright YetiForce S.A.
 * @license   YetiForce Public License 5.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */

namespace Tests\Integrations;

use FR3D\SwaggerAssertions\PhpUnit\AssertsTrait;
use FR3D\SwaggerAssertions\SchemaManager;

/**
 * @internal
 * @coversNothing
 */
final class Token extends \Tests\Base
{
	use AssertsTrait;

	/** @var SchemaManager */
	protected static $schemaManager;

	/** @var \GuzzleHttp\Client */
	protected $httpClient;

	/** @var int Api server id. */
	private static $serverId;

	/** @var string Token. */
	private static $token;

	/**
	 * This method is called before the first test of this test class is run.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::$schemaManager = new SchemaManager(json_decode(file_get_contents(ROOT_DIRECTORY . \App\Installer\Developer::PATH . '/Token.json')));
	}

	/**
	 * Before a test method is run, this method is invoked.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		$this->httpClient = new \GuzzleHttp\Client(\App\Utils::merge(\App\RequestHttp::getOptions(), [
			'base_uri' => \App\Config::main('site_URL') . 'webservice/Token/',
			'Content-Type' => 'application/json',
			'Accept' => 'application/json',
			'timeout' => 60,
			'connect_timeout' => 60,
			'http_errors' => false,
		]));
	}

	/**
	 * Testing add configuration.
	 *
	 * @return void
	 */
	public function testAddConfiguration(): void
	{
		$app = \Settings_WebserviceApps_Record_Model::getCleanInstance();
		$app->set('type', 'Token');
		$app->set('status', 1);
		$app->set('name', 'token');
		$app->set('url', '');
		$app->set('ips', '');
		$app->set('pass', 'token');
		$app->save();
		self::$serverId = (int) $app->getId();

		$row = \App\Fields\ServerAccess::get(self::$serverId);
		static::assertNotFalse($row, 'No record id: ' . self::$serverId);
		static::assertSame($row['type'], 'Token');
		static::assertSame($row['status'], 1);
		static::assertSame($row['name'], $app->get('name'));
		static::assertSame($row['pass'], $app->get('pass'));
	}

	/**
	 * Testing generate token.
	 *
	 * @return void
	 */
	public function testGenerateToken(): void
	{
		self::$token = $token = \App\Utils\Tokens::generate('Tests\Integrations\Token::action', [1], date('Y-m-d H:i:s', strtotime('+1 hour')), false);
		static::assertNotEmpty($token);
		static::assertSame(\strlen($token), 64);
		static::assertTrue(\App\Validator::alnum($token), "Incorrect token value: $token");
		static::assertTrue(\App\Utils\Tokens::get($token), "Token doesn't exist: $token");
	}

	/**
	 * Sample token mechanism method.
	 *
	 * @param array             $params
	 * @param \Api\Token\Action $self
	 *
	 * @return void
	 */
	public static function action(array $params, \Api\Token\Action $self): void
	{
		echo 'test ' . \App\Utils::varExport($params);
	}

	/**
	 * Testing run token action.
	 *
	 * @return void
	 */
	public function testRunTokenAction(): void
	{
		$request = $this->httpClient->get(self::$token);
		$this->logs = $body = $request->getBody()->getContents();

		static::assertSame(200, $request->getStatusCode(), 'API error: ' . PHP_EOL . $request->getReasonPhrase() . '|' . $body);
		static::assertSame('test [1]', $body, 'API error: ' . PHP_EOL . $request->getReasonPhrase() . '|' . $body);
		self::assertResponseBodyMatch($body, self::$schemaManager, '/webservice/Token/{token}', 'get', 200);
	}
}