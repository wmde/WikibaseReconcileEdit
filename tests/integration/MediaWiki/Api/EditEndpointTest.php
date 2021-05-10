<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Test\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks;
use MediaWiki\MediaWikiServices;
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
use Wikimedia\Rdbms\LoadBalancerSingle;

/**
 * @covers MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint
 * @group Database
 */
class EditEndpointTest extends \MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private const URL_PROPERTY = 'P1';
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

		$repo = WikibaseRepo::getDefaultInstance();

		return new EditEndpoint(
			MediaWikiServices::getInstance()->getTitleFactory(),
			$repo->newEditEntityFactory(),
			$repo->getEntityIdLookup(),
			$repo->getEntityLookup(),
			$repo->getEntityRevisionLookup(),
			$repo->newIdGenerator(),
			$propertyDataTypeLookup,
			new ExternalLinks(
				LoadBalancerSingle::newFromConnection( $this->db )
			),
			WikibaseReconcileEditServices::getFullWikibaseItemInput(),
			new MinimalItemInput(
				$propertyDataTypeLookup
			)
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

	public function provideInvalidUrlReconcile(): iterable {
		yield 'missing' => [ null ];
		yield 'empty' => [ '' ];
		yield 'item ID' => [ 'Q123' ];
		yield 'statement ID' => [ 'P40$ea25003c-4c23-63fa-86d9-62bfcd2b05a4' ];
		yield 'numeric part missing' => [ 'P' ];
	}

}
