<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Integration\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\WikibaseReconcileEditServices;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ItemReconciler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\RequestInterface;
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
 * @covers MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint
 * @group Database
 * @license GPL-2.0-or-later
 */
class EditEndpointTest extends \MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

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

		$this->setupProperty( self::URL_PROPERTY, 'url' );
		$this->setupProperty( self::STRING_PROPERTY, 'string' );
		$this->setupProperty( self::ITEM_PROPERTY, 'wikibase-item' );
		$this->setupProperty( self::URL_PROPERTY_NOT_RECONCILED, 'url' );

		$this->defaultTestUser = $this->getTestSysop()->getUser();
	}

	private function setupProperty( string $propertyId, $propertyType ): void {
		WikibaseRepo::getDefaultInstance()->getEntityStore()->saveEntity(
			new Property( new PropertyId( $propertyId ), null, $propertyType ),
			__METHOD__,
			$this->getTestUser()->getUser(),
			EDIT_NEW
		)->getEntity()->getId();
	}

	private function newHandler( User $user = null ) {
		$repo = WikibaseRepo::getDefaultInstance();
		$reconciliationService = WikibaseReconcileEditServices::getReconciliationService();
		$propertyDataTypeLookup = WikibaseRepo::getDefaultInstance()->getPropertyDataTypeLookup();
		return new EditEndpoint(
			$repo->newEditEntityFactory(),
			new EditRequestParser(
				$propertyDataTypeLookup,
				WikibaseReconcileEditServices::getFullWikibaseItemInput(),
				new MinimalItemInput(
					$propertyDataTypeLookup,
					$repo->getValueParserFactory(),
					$reconciliationService
				)
			),
			new ItemReconciler(
				$reconciliationService,
				WikibaseReconcileEditServices::getSimplePutStrategy()
			),
			$user !== null ? $user : $this->defaultTestUser
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
