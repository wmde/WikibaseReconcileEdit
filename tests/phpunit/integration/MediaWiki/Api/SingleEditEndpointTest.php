<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Integration\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\SingleEditEndpoint;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\WikibaseReconcileEditServices;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ItemReconciler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Session\SessionProviderInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use User;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\SingleEditEndpoint
 * @group Database
 * @license GPL-2.0-or-later
 */
class SingleEditEndpointTest extends \MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private const STRING_PROPERTY_LABEL = 'stringProperty';
	private const ITEM_PROPERTY_LABEL = 'itemProperty';

	private const URL_PROPERTY = 'P1';
	private const STRING_PROPERTY = 'P2';
	private const ITEM_PROPERTY = 'P3';
	private const URL_PROPERTY_NOT_RECONCILED = 'P15';
	private const MISSING_PROPERTY = 'P1000';
	private $defaultTestUser;

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'externallinks';
		$this->tablesUsed[] = 'wb_property_info';
		$this->tablesUsed[] = 'wb_id_counters';

		$this->setupProperty( self::URL_PROPERTY, 'url', 'identifier' );
		$this->setupProperty( self::STRING_PROPERTY, 'string', self::STRING_PROPERTY_LABEL );
		$this->setupProperty( self::ITEM_PROPERTY, 'wikibase-item', self::ITEM_PROPERTY_LABEL );
		$this->setupProperty( self::URL_PROPERTY_NOT_RECONCILED, 'url', 'urlPropertyNotReconciled' );

		$this->defaultTestUser = $this->getTestSysop()->getUser();
		$this->setMwGlobals( 'wgLanguageCode', 'en' );
	}

	private function setupProperty( string $propertyId, string $propertyType, string $label ): void {
		$prop = new Property( new PropertyId( $propertyId ), null, $propertyType );
		$prop->setLabel( 'en', $label );
		WikibaseRepo::getDefaultInstance()->getEntityStore()->saveEntity(
			$prop,
			__METHOD__,
			$this->getTestUser()->getUser(),
			EDIT_NEW
		)->getEntity()->getId();
	}

	private function newHandler( User $user = null, SessionProviderInterface $sessionProvider = null ) {
		$repo = WikibaseRepo::getDefaultInstance();
		$reconciliationService = WikibaseReconcileEditServices::getReconciliationService();
		$propertyDataTypeLookup = WikibaseRepo::getDefaultInstance()->getPropertyDataTypeLookup();
		$editEntityFactory = method_exists( $repo, 'getEditEntityFactory' )
			? $repo->getEditEntityFactory() // 1.36+
			: $repo->newEditEntityFactory(); // 1.35
		return new SingleEditEndpoint(
			$editEntityFactory,
			new EditRequestParser(
				$propertyDataTypeLookup,
				WikibaseReconcileEditServices::getFullWikibaseItemInput(),
				new MinimalItemInput(
					$propertyDataTypeLookup,
					$repo->getValueParserFactory(),
					$reconciliationService,
					WikibaseReconcileEditServices::getPropertyLabelResolver()
				)
			),
			new ItemReconciler(
				$reconciliationService,
				WikibaseReconcileEditServices::getSimplePutStrategy()
			),
			$user !== null ? $user : $this->defaultTestUser,
			$sessionProvider !== null ? $sessionProvider : $this->getMockSessionProvider( false )
		);
	}

	private function newRequest( array $params, User $user = null ): RequestInterface {
		$params['token'] = $user !== null ? $user->getEditToken() : $this->defaultTestUser->getEditToken();

		return new RequestData( [
			'bodyContents' => json_encode( $params ),
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'method' => 'POST',
		] );
	}

	public function testCreateNewItem(): void {
		$response = $this->executeHandlerAndGetBodyData(
			$this->newHandler(),
			$this->newRequest( [
				'entity' => [
					EditRequestParser::VERSION_KEY => '0.0.1/minimal',
					'statements' => [
						[
							'property' => self::URL_PROPERTY,
							'value' => 'http://example.com/',
						],
					],
				],
				'reconcile' => [
					EditRequestParser::VERSION_KEY => '0.0.1',
					'urlReconcile' => self::URL_PROPERTY,
				],
			] )
		);
		$this->assertTrue( $response['success'] );

		$itemId = new ItemId( $response['entityId'] );
		/** @var Item $item */
		$item = WikibaseRepo::getDefaultInstance()->getEntityLookup()->getEntity( $itemId );
		$snaks = $item->getStatements()
			->getByPropertyId( new PropertyId( self::URL_PROPERTY ) )
			->getMainSnaks();
		$this->assertCount( 1, $snaks );
		/** @var PropertyValueSnak $snak */
		$snak = $snaks[0];
		$this->assertInstanceOf( PropertyValueSnak::class, $snak );
		$this->assertSame( 'http://example.com/', $snak->getDataValue()->getValue() );
	}

	public function getRequestByStatements( array $statements ): RequestInterface {
		return $this->newRequest( [
			'entity' => [
				EditRequestParser::VERSION_KEY => '0.0.1/minimal',
				'statements' => $statements,
			],
			'reconcile' => [
				EditRequestParser::VERSION_KEY => '0.0.1',
				'urlReconcile' => self::URL_PROPERTY,
			],
		] );
	}

	public function provideStatementReconciliation(): iterable {
		$url = "http://some-url/";

		yield 'reconcile existing Q1' => [
			// multiple requests
			[
				[
					// first request creates one item
					[ 'property' => self::URL_PROPERTY, 'value' => $url . '1', ],
				],
				[
					// second request creates another item and links first
					[ 'property' => self::URL_PROPERTY, 'value' => $url . '2', ],
					[ 'property' => self::ITEM_PROPERTY, 'value' => $url . '1', ],
				]
			],
			// expected results after each request
			[
				// After first should only be one
				[ 'Q1' => [ self::URL_PROPERTY => $url . '1' ] ],

				// After second request should be two items where the second one points to the first
				[
					'Q1' => [ self::URL_PROPERTY => $url . '1' ],
					'Q2' => [
						self::URL_PROPERTY => $url . '2',
						self::ITEM_PROPERTY => new EntityIdValue( new ItemId( 'Q1' ) )
					]
				]
			]
		];

		yield 'reconcile non-existing Q3' => [
			[

				[
					// first request creates a single item
					[ 'property' => self::URL_PROPERTY, 'value' => $url . '1', ],
				],
				[
					// Second request creates a new item and tries to look up non existing third one
					[ 'property' => self::URL_PROPERTY, 'value' => $url . '2', ],
					[ 'property' => self::ITEM_PROPERTY, 'value' => $url . '3', ],
				]
			],
			// expected results after each request
			[
				// Only one item after first request
				[ 'Q1' => [ self::URL_PROPERTY => $url . '1' ] ],

				// After second request we should have three items.
				// Q3 has been assigned to the "second" item but links to the "third"
				// Q2 has been assigned to the "third" item but with the correct url.
				[
					'Q1' => [ self::URL_PROPERTY => $url . '1' ],
					'Q3' => [
						self::URL_PROPERTY => $url . '2',
						self::ITEM_PROPERTY => new EntityIdValue( new ItemId( 'Q2' ) )
					],
					'Q2' => [ self::URL_PROPERTY => $url . '3' ],
				]
			]
		];

		yield 'reconcile the same item to itself and create another item' => [
			[
				[
					// request that should create one item that links to itself and a second item
					[ 'property' => self::URL_PROPERTY, 'value' => $url . '1', ],
					[ 'property' => self::ITEM_PROPERTY, 'value' => $url . '1', ],
					[ 'property' => self::ITEM_PROPERTY, 'value' => $url . '2', ],
				],
			],
			// expected results after each request
			[
				[
					'Q1' => [
						self::URL_PROPERTY => $url . '1',
						self::ITEM_PROPERTY => [
							new EntityIdValue( new ItemId( 'Q1' ) ),
							new EntityIdValue( new ItemId( 'Q2' ) )
						],

					],
					'Q2' => [
						self::URL_PROPERTY => $url . '2',
					]
				],
			]
		];

		yield 'lookup property names using labels' => [
			[
				[
					// request that should create one item that links to itself and a second item
					[ 'property' => self::URL_PROPERTY, 'value' => $url . '1', ],
					[ 'property' => self::STRING_PROPERTY_LABEL, 'value' => 'hello', ],
					[ 'property' => self::ITEM_PROPERTY_LABEL, 'value' => $url . '2', ],
				],
			],
			// expected results after each request
			[
				[
					'Q2' => [
						self::URL_PROPERTY => $url . '1',
						self::STRING_PROPERTY => 'hello',
						self::ITEM_PROPERTY => new EntityIdValue( new ItemId( 'Q1' ) ),
					],
					'Q1' => [
						self::URL_PROPERTY => $url . '2',
					]
				],
			]
		];
	}

	/**
	 * Tests multiple requests and the expected database state after each request
	 *
	 * @dataProvider provideStatementReconciliation
	 */
	public function testItemStatementReconciliation( array $requests, array $expectedItems ): void {
		for ( $requestIndex = 0; $requestIndex < count( $requests ); ++$requestIndex ) {
			$request = $this->getRequestByStatements( $requests[$requestIndex] );
			$response = $this->executeHandlerAndGetBodyData( $this->newHandler(), $request );
			$this->assertTrue( $response['success'] );
			$itemId = new ItemId( $response['entityId'] );

			/** @var Item $item */
			$item = WikibaseRepo::getDefaultInstance()->getEntityLookup()->getEntity( $itemId );
			$this->assertInstanceOf( Item::class, $item );

			// this needs to be reset in-order for the per-request "cache" to clear
			// @see ReconciliationService::$items
			MediaWikiServices::getInstance()->resetServiceForTesting( 'WikibaseReconcileEdit.ReconciliationService' );

			$expectedRequestItems = $expectedItems[$requestIndex];

			$this->assertEquals( $this->countItemsInDatabase(), count( $expectedRequestItems ) );

			foreach ( $expectedRequestItems as $itemId => $properties ) {
				$item = WikibaseRepo::getDefaultInstance()->getEntityLookup()->getEntity( new ItemId( $itemId ) );
				$this->assertInstanceOf( Item::class, $item );

				foreach ( $properties as $propertyId => $snakDataValue ) {

					$snaks = $item->getStatements()
						->getByPropertyId( new PropertyId( $propertyId ) )
						->getMainSnaks();

					// is not arrays by default
					if ( !is_array( $snakDataValue ) ) {
						$snakDataValue = [ $snakDataValue ];
					}

					$this->assertSameSize( $snaks,  $snakDataValue );

					for ( $snakIndex = 0; $snakIndex < count( $snakDataValue ); ++$snakIndex ) {

						/** @var PropertyValueSnak $snak */
						$snak = $snaks[$snakIndex];
						$this->assertInstanceOf( PropertyValueSnak::class, $snak );
						$this->assertEquals( $snakDataValue[$snakIndex], $snak->getDataValue()->getValue() );
					}

				}
			}

		}
	}

	/** @dataProvider provideTokenParameters */
	public function testTokenParameter( ?string $token, bool $safeAgainstCsrf ): void {
		$params = [
			'entity' => 1,
			'reconcile' => 1,
		];
		if ( $token !== null ) {
			$params['token'] = str_replace(
				'REAL_TOKEN',
				$this->defaultTestUser->getEditToken(),
				$token
			);
		}
		$request = new RequestData( [
			'bodyContents' => json_encode( $params ),
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'method' => 'POST',
		] );
		$sessionProvider = $this->getMockSessionProvider( $safeAgainstCsrf );

		$handler = $this->newHandler( $this->defaultTestUser, $sessionProvider );
		/** @var LocalizedHttpException $exception */
		$exception = $this->executeHandlerAndGetHttpException( $handler, $request );

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( 'wikibasereconcileedit-invalid-reconcile-parameter',
			$exception->getMessageValue()->getKey() );
	}

	public function provideTokenParameters(): iterable {
		// REAL_TOKEN will be replaced in the test function
		// (here in the data provider, $this->defaultTestUser isn’t set yet)
		yield 'valid token needed' => [ 'REAL_TOKEN', false ];
		yield 'token not needed' => [ null, true ];
		yield 'unneeded token ignored' => [ 'FAKE_TOKEN', true ];
	}

	public function testInvalidTokenParameter(): void {
		$params = [
			'entity' => 1,
			'reconcile' => 1,
			'token' => 'FAKE_TOKEN',
		];
		$request = new RequestData( [
			'bodyContents' => json_encode( $params ),
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'method' => 'POST',
		] );

		/** @var LocalizedHttpException $exception */
		$exception = $this->executeHandlerAndGetHttpException( $this->newHandler(), $request );

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( 'wikibasereconcileedit-unauthorized-access',
			$exception->getMessageValue()->getKey() );
	}

	private function countItemsInDatabase(): int {
		$itemNamespace = WikibaseRepo::getDefaultInstance()
			->getEntityNamespaceLookup()
			->getEntityNamespace( 'item' );

		return $this->db->selectRowCount(
			'page',
			'*',
			[ 'page_namespace' => $itemNamespace ],
			__METHOD__
		);
	}

	private function getMockSessionProvider( bool $safeAgainstCSRF ): SessionProviderInterface {
		$provider = $this->createMock( SessionProviderInterface::class );
		$provider->method( 'safeAgainstCsrf' )->willReturn( $safeAgainstCSRF );
		return $provider;
	}

}
