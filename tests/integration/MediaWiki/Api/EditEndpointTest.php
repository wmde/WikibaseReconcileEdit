<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Test\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\WikibaseReconcileEditServices;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\InMemoryDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint
 * @group Database
 */
class EditEndpointTest extends \MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private const URL_PROPERTY = 'P1';
	private const URL_PROPERTY_NOT_RECONCILED = 'P15';
	private const STRING_PROPERTY = 'P2';
	private const MISSING_PROPERTY = 'P1000';

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'wb_property_info';
	}

	private function newHandler() {
		$propertyDataTypeLookup = new InMemoryDataTypeLookup();

		$propertyDataTypeLookup->setDataTypeForProperty(
			new PropertyId( self::URL_PROPERTY ),
			'url'
		);

		$propertyDataTypeLookup->setDataTypeForProperty(
			new PropertyId( self::URL_PROPERTY_NOT_RECONCILED ),
			'url'
		);

		$propertyDataTypeLookup->setDataTypeForProperty(
			new PropertyId( self::STRING_PROPERTY ),
			'string'
		);

		$repo = WikibaseRepo::getDefaultInstance();

		return new EditEndpoint(
			$repo->newEditEntityFactory(),
			$propertyDataTypeLookup,
			WikibaseReconcileEditServices::getFullWikibaseItemInput(),
			new MinimalItemInput(
				$propertyDataTypeLookup,
				$repo->getValueParserFactory()
			),
			WikibaseReconcileEditServices::getReconciliationService(),
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
							'property' => self::URL_PROPERTY,
							'value' => 'http://example.com/',
						],
					],
				],
				'reconcile' => [
					EditEndpoint::VERSION_KEY => '0.0.1',
					'urlReconcile' => self::URL_PROPERTY,
				],
			] )
		);
		$response = json_decode( $response['value'], true );
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

	public function testPropertyTypeMustBeURL(): void {
		/** @var LocalizedHttpException $exception */
		$exception = $this->executeHandlerAndGetHttpException(
			$this->newHandler(),
			$this->newRequest( [
				'entity' => [
					EditEndpoint::VERSION_KEY => '0.0.1/minimal',
					'statements' => [
						[
							'property' => self::STRING_PROPERTY,
							'value' => 'http://example.com/',
						],
					],
				],
				'reconcile' => [
					EditEndpoint::VERSION_KEY => '0.0.1',
					'urlReconcile' => self::STRING_PROPERTY,
				],
			] )
		);

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( 'wikibasereconcileedit-editendpoint-invalid-type-property-must-be-url',
			$exception->getMessageValue()->getKey() );
	}

	public function testUnspecifiedEntityInputVersion(): void {
		/** @var LocalizedHttpException $exception */
		$exception = $this->executeHandlerAndGetHttpException(
			$this->newHandler(),
			$this->newRequest( [
				'entity' => [
					'statements' => [
						[
							'property' => self::URL_PROPERTY,
							'value' => 'http://example.com/',
						],
					],
				],
				'reconcile' => [
					EditEndpoint::VERSION_KEY => '0.0.1',
					'urlReconcile' => self::URL_PROPERTY,
				],
			] )
		);

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( 'wikibasereconcileedit-editendpoint-unspecified-entity-input-version',
			$exception->getMessageValue()->getKey() );
	}

	public function testUnsupportedEntityVersion(): void {
		/** @var LocalizedHttpException $exception */
		$exception = $this->executeHandlerAndGetHttpException(
			$this->newHandler(),
			$this->newRequest( [
				'entity' => [
					EditEndpoint::VERSION_KEY => '0.0.1/potato',
					'statements' => [
						[
							'property' => self::URL_PROPERTY,
							'value' => 'http://example.com/',
						],
					],
				],
				'reconcile' => [
					EditEndpoint::VERSION_KEY => '0.0.1',
					'urlReconcile' => self::URL_PROPERTY,
				],
			] )
		);

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( 'wikibasereconcileedit-editendpoint-invalid-entity-input-version',
			$exception->getMessageValue()->getKey() );
	}

	public function testUnsupportedQualifiers(): void {
		/** @var LocalizedHttpException $exception */
		$exception = $this->executeHandlerAndGetHttpException(
			$this->newHandler(),
			$this->newRequest( [
				'entity' => [
					EditEndpoint::VERSION_KEY => '0.0.1/full',
					'type' => 'item',
					'claims' => [
						self::URL_PROPERTY => [
							[
								'mainsnak' => [
									'snaktype' => 'value',
									'property' => self::URL_PROPERTY,
									'datavalue' => [
										'value' => 'http://example.com/',
										'type' => 'string'
									],
									'datatype' => 'url'
								],
								'type' => 'statement',
								'rank' => 'normal',
								'qualifiers' => [
									self::STRING_PROPERTY => [
										[
											'snaktype' => 'value',
											'property' => self::STRING_PROPERTY,
											'datavalue' => [
												'value' => 'potato',
												'type' => 'string'
											],
											'datatype' => 'string'
										]
									]
								]
							]
						],
					],
				],
				'reconcile' => [
					EditEndpoint::VERSION_KEY => '0.0.1',
					'urlReconcile' => self::URL_PROPERTY,
				],
			] )
		);

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( 'wikibasereconcileedit-editendpoint-qualifiers-references-not-supported',
			$exception->getMessageValue()->getKey() );
	}

	public function testUnsupportedReferences(): void {
		/** @var LocalizedHttpException $exception */
		$exception = $this->executeHandlerAndGetHttpException(
			$this->newHandler(),
			$this->newRequest( [
				'entity' => [
					EditEndpoint::VERSION_KEY => '0.0.1/full',
					'type' => 'item',
					'claims' => [
						self::URL_PROPERTY => [
							[
								'mainsnak' => [
									'snaktype' => 'value',
									'property' => self::URL_PROPERTY,
									'datavalue' => [
										'value' => 'http://example.com/',
										'type' => 'string'
									],
									'datatype' => 'url'
								],
								'type' => 'statement',
								'rank' => 'normal',
								'references' => [
									[
										'snaks' => [
											self::STRING_PROPERTY => [
												[
													'snaktype' => 'value',
													'property' => self::STRING_PROPERTY,
													'datavalue' => [
														'value' => 'potato',
														'type' => 'string'
													],
													'datatype' => 'external-id'
												]
											]
										]
									]
								]
							]
						],
					],
				],
				'reconcile' => [
					EditEndpoint::VERSION_KEY => '0.0.1',
					'urlReconcile' => self::URL_PROPERTY,
				],
			] )
		);

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( 'wikibasereconcileedit-editendpoint-qualifiers-references-not-supported',
			$exception->getMessageValue()->getKey() );
	}

	public function testReconciliationPropertyMissingInStatements(): void {
		/** @var LocalizedHttpException $exception */
		$exception = $this->executeHandlerAndGetHttpException(
			$this->newHandler(),
			$this->newRequest( [
				'entity' => [
					EditEndpoint::VERSION_KEY => '0.0.1/minimal',
					'statements' => [
						[
							'property' => self::URL_PROPERTY_NOT_RECONCILED,
							'value' => 'http://example.com/',
						],
					],
				],
				'reconcile' => [
					EditEndpoint::VERSION_KEY => '0.0.1',
					'urlReconcile' => self::URL_PROPERTY,
				],
			] )
		);

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( 'wikibasereconcileedit-editendpoint-reconciliation-property-missing-in-statements',
			$exception->getMessageValue()->getKey() );
	}

	public function testInvalidSnakTypeValue(): void {
		/** @var LocalizedHttpException $exception */
		$exception = $this->executeHandlerAndGetHttpException(
			$this->newHandler(),
			$this->newRequest( [
				'entity' => [
					EditEndpoint::VERSION_KEY => '0.0.1/full',
					'type' => 'item',
					'claims' => [
						self::URL_PROPERTY => [
							[
								'mainsnak' => [
									'snaktype' => 'somevalue',
									'property' => self::URL_PROPERTY,
									'datatype' => 'url'
								],
								'type' => 'statement',
								'rank' => 'normal',
							]
						],
					],
				],
				'reconcile' => [
					EditEndpoint::VERSION_KEY => '0.0.1',
					'urlReconcile' => self::URL_PROPERTY,
				],
			] )
		);

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( 'wikibasereconcileedit-editendpoint-invalid-reconciliation-statement-type',
			$exception->getMessageValue()->getKey() );
	}

	public function provideInvalidUrlReconcile(): iterable {
		yield 'missing' => [ null ];
		yield 'empty' => [ '' ];
		yield 'item ID' => [ 'Q123' ];
		yield 'statement ID' => [ 'P40$ea25003c-4c23-63fa-86d9-62bfcd2b05a4' ];
		yield 'numeric part missing' => [ 'P' ];
	}

}
