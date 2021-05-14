<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Test\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\WikibaseReconcileEditServices;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint
 * @group Database
 */
class EditEndpointTest extends \MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	/** @var PropertyId */
	private $urlProperty = 'P1';

	/** @var PropertyId */
	private $itemProperty = 'P2';

	private const MISSING_PROPERTY = 'P1000';

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'externallinks';
		$this->tablesUsed[] = 'wb_property_info';
		$this->tablesUsed[] = 'wb_id_counters';

		WikibaseRepo::getDefaultInstance()->getEntityStore()->saveEntity(
			new Property( new PropertyId( $this->urlProperty ), null, 'url' ),
			__METHOD__,
			$this->getTestUser()->getUser(),
			EDIT_NEW
		)->getEntity()->getId();

		WikibaseRepo::getDefaultInstance()->getEntityStore()->saveEntity(
			new Property( new PropertyId( $this->itemProperty ), null, 'wikibase-item' ),
			__METHOD__,
			$this->getTestUser()->getUser(),
			EDIT_NEW
		)->getEntity()->getId();
	}

	private function newHandler() {
		$repo = WikibaseRepo::getDefaultInstance();
		$reconciliationService = WikibaseReconcileEditServices::getReconciliationService();
		$propertyDataTypeLookup = WikibaseRepo::getDefaultInstance()->getPropertyDataTypeLookup();
		return new EditEndpoint(
			$repo->newEditEntityFactory(),
			$propertyDataTypeLookup,
			WikibaseReconcileEditServices::getFullWikibaseItemInput(),
			new MinimalItemInput(
				$propertyDataTypeLookup,
				$repo->getValueParserFactory(),
				$reconciliationService
			),
			$reconciliationService,
			WikibaseReconcileEditServices::getSimplePutStrategy()
		);
	}

	private function newRequest( array $params ): RequestInterface {
		return new RequestData( [
			'postParams' => array_map( 'json_encode', $params ),
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
					EditEndpoint::VERSION_KEY => '0.0.1/minimal',
					'statements' => [
						[
							'property' => $this->urlProperty,
							'value' => 'http://example.com/',
						],
					],
				],
				'reconcile' => [
					EditEndpoint::VERSION_KEY => '0.0.1',
					'urlReconcile' => $this->urlProperty,
				],
			] )
		);
		$response = json_decode( $response['value'], true );
		$this->assertTrue( $response['success'] );

		$itemId = new ItemId( $response['entityId'] );
		/** @var Item $item */
		$item = WikibaseRepo::getDefaultInstance()->getEntityLookup()->getEntity( $itemId );
		$snaks = $item->getStatements()
			->getByPropertyId( new PropertyId( $this->urlProperty ) )
			->getMainSnaks();
		$this->assertCount( 1, $snaks );
		/** @var PropertyValueSnak $snak */
		$snak = $snaks[0];
		$this->assertInstanceOf( PropertyValueSnak::class, $snak );
		$this->assertSame( 'http://example.com/', $snak->getDataValue()->getValue() );
	}

	public function testExecuteNoPropertyFound() {
		$reconcilePayload = [
			'urlReconcile' => self::MISSING_PROPERTY,
			EditEndpoint::VERSION_KEY => '0.0.1'
		];

		$request = $this->newRequest( [
			'entity' => 'Q1',
			'reconcile' => $reconcilePayload,
		] );

		$handler = $this->newHandler();

		$this->expectException( PropertyDataTypeLookupException::class );
		$this->executeHandlerAndGetBodyData( $handler, $request );
	}

	public function testInvalidReconcile(): void {
		/** @var LocalizedHttpException $exception */
		$exception = $this->executeHandlerAndGetHttpException(
			$this->newHandler(),
			// don’t use newRequest() since we want to pass invalid JSON
			new RequestData( [
				'postParams' => [
					'entity' => '',
					'reconcile' => '',
				],
				'headers' => [ 'Content-Type' => 'application/json' ],
				'method' => 'POST',
			] )
		);

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( 'wikibasereconcileedit-editendpoint-invalid-reconcile-json',
			$exception->getMessageValue()->getKey() );
	}

	public function testUnsupportedReconcileVersion(): void {
		/** @var LocalizedHttpException $exception */
		$exception = $this->executeHandlerAndGetHttpException(
			$this->newHandler(),
			$this->newRequest( [
				'entity' => '',
				'reconcile' => [
					EditEndpoint::VERSION_KEY => '0.0.0',
				],
			] )
		);

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( 'wikibasereconcileedit-editendpoint-unsupported-reconcile-version',
			$exception->getMessageValue()->getKey() );
	}

	public function getRequestByStatements( array $statements ): RequestInterface {
		return $this->newRequest( [
			'entity' => [
				EditEndpoint::VERSION_KEY => '0.0.1/minimal',
				'statements' => $statements,
			],
			'reconcile' => [
				EditEndpoint::VERSION_KEY => '0.0.1',
				'urlReconcile' => $this->urlProperty,
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
					[ 'property' => $this->urlProperty, 'value' => $url . '1', ],
				],
				[
					// second request creates another item and links first
					[ 'property' => $this->urlProperty, 'value' => $url . '2', ],
					[ 'property' => $this->itemProperty, 'value' => $url . '1', ],
				]
			],
			// expected results after each request
			[
				// After first should only be one
				[ 'Q1' => [ $this->urlProperty => $url . '1' ] ],

				// After second request should be two items where the second one points to the first
				[
					'Q1' => [ $this->urlProperty => $url . '1' ],
					'Q2' => [
						$this->urlProperty => $url . '2',
						$this->itemProperty => new EntityIdValue( new ItemId( 'Q1' ) )
					]
				]
			]
		];

		yield 'reconcile non-existing Q3' => [
			[

				[
					// first request creates a single item
					[ 'property' => $this->urlProperty, 'value' => $url . '1', ],
				],
				[
					// Second request creates a new item and tries to look up non existing third one
					[ 'property' => $this->urlProperty, 'value' => $url . '2', ],
					[ 'property' => $this->itemProperty, 'value' => $url . '3', ],
				]
			],
			// expected results after each request
			[
				// Only one item after first request
				[ 'Q1' => [ $this->urlProperty => $url . '1' ] ],

				// After second request we should have three items.
				// Q3 has been assigned to the "second" item but links to the "third"
				// Q2 has been assigned to the "third" item but with the correct url.
				[
					'Q1' => [ $this->urlProperty => $url . '1' ],
					'Q3' => [
						$this->urlProperty => $url . '2',
						$this->itemProperty => new EntityIdValue( new ItemId( 'Q2' ) )
					],
					'Q2' => [ $this->urlProperty => $url . '3' ],
				]
			]
		];

		yield 'reconcile the same item to itself and create another item' => [
			[
				[
					// request that should create one item that links to itself and a second item
					[ 'property' => $this->urlProperty, 'value' => $url . '1', ],
					[ 'property' => $this->itemProperty, 'value' => $url . '1', ],
					[ 'property' => $this->itemProperty, 'value' => $url . '2', ],
				],
			],
			// expected results after each request
			[
				[
					'Q1' => [
						$this->urlProperty => $url . '1',
						$this->itemProperty => [
							new EntityIdValue( new ItemId( 'Q1' ) ),
							new EntityIdValue( new ItemId( 'Q2' ) )
						],

					],
					'Q2' => [
						$this->urlProperty => $url . '2',
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
			$response = json_decode( $response['value'], true );
			$this->assertTrue( $response['success'] );
			$itemId = new ItemId( $response['entityId'] );

			/** @var Item $item */
			$item = WikibaseRepo::getDefaultInstance()->getEntityLookup()->getEntity( $itemId );
			$this->assertInstanceOf( Item::class, $item );

			// this needs to be reset in-order for the per-request "cache" to clear
			// @see ReconciliationService::$reconciliationItems
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

	/** @dataProvider provideInvalidUrlReconcile */
	public function testInvalidUrlReconcile( ?string $urlReconcile ): void {
		$params = [
			EditEndpoint::VERSION_KEY => '0.0.1',
		];
		if ( $urlReconcile !== null ) {
			$params['urlReconcile'] = $urlReconcile;
		}
		/** @var LocalizedHttpException $exception */
		$exception = $this->executeHandlerAndGetHttpException(
			$this->newHandler(),
			$this->newRequest( [
				'entity' => '',
				'reconcile' => $params,
			] )
		);

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( 'wikibasereconcileedit-editendpoint-invalid-reconcile-propertyid',
			$exception->getMessageValue()->getKey() );
	}

	public function provideInvalidUrlReconcile(): iterable {
		yield 'missing' => [ null ];
		yield 'empty' => [ '' ];
		yield 'item ID' => [ 'Q123' ];
		yield 'statement ID' => [ 'P40$ea25003c-4c23-63fa-86d9-62bfcd2b05a4' ];
		yield 'numeric part missing' => [ 'P' ];
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

}
