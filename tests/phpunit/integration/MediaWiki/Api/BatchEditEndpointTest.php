<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Integration\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\BatchEditEndpoint;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\WikibaseReconcileEditServices;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ItemReconciler;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Session\SessionProviderInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use User;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\BatchEditEndpoint
 * @group Database
 * @license GPL-2.0-or-later
 */
class BatchEditEndpointTest extends \MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private const STRING_PROPERTY_LABEL = 'stringProperty';
	private const ITEM_PROPERTY_LABEL = 'itemProperty';

	private const URL_PROPERTY = 'P1';
	private const ITEM_PROPERTY = 'P2';
	private $defaultTestUser;

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'externallinks';
		$this->tablesUsed[] = 'wb_property_info';
		$this->tablesUsed[] = 'wb_id_counters';

		$this->setupProperty( self::URL_PROPERTY, 'url', 'identifier' );
		$this->setupProperty( self::ITEM_PROPERTY, 'wikibase-item', self::ITEM_PROPERTY_LABEL );

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
		return new BatchEditEndpoint(
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
				'entities' => [ [
					EditRequestParser::VERSION_KEY => '0.0.1/minimal',
					'statements' => [
						[
							'property' => self::URL_PROPERTY,
							'value' => 'http://example.com/',
						],
					],
				] ],
				'reconcile' => [
					EditRequestParser::VERSION_KEY => '0.0.1',
					'urlReconcile' => self::URL_PROPERTY,
				],
			] )
		);
		$this->assertTrue( $response['success'] );

		$itemId = new ItemId( $response['results'][0]['entityId'] );
		/** @var Item $item */
		$item = WikibaseRepo::getDefaultInstance()->getEntityLookup()->getEntity( $itemId );
		$this->assertSame( 'http://example.com/',
			$this->singleStatementValue( $item, self::URL_PROPERTY ) );
	}

	public function testCreateItemsWithSameUrlItemStatement(): void {
		$response = $this->executeHandlerAndGetBodyData(
			$this->newHandler(),
			$this->newRequest( [
				'entities' => [
					[
						EditRequestParser::VERSION_KEY => '0.0.1/minimal',
						'statements' => [
							[
								'property' => self::URL_PROPERTY,
								'value' => 'http://example.com/1',
							],
							[
								'property' => self::ITEM_PROPERTY,
								'value' => 'http://example.com/3',
							],
						],
					],
					[
						EditRequestParser::VERSION_KEY => '0.0.1/minimal',
						'statements' => [
							[
								'property' => self::URL_PROPERTY,
								'value' => 'http://example.com/2',
							],
							[
								'property' => self::ITEM_PROPERTY,
								'value' => 'http://example.com/3',
							],
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
		$results = $response['results'];

		$this->assertCount( 2, $results );
		$entityLookup = WikibaseRepo::getDefaultInstance()->getEntityLookup();
		$item1 = $entityLookup->getEntity( new ItemId( $results[0]['entityId'] ) );
		$item2 = $entityLookup->getEntity( new ItemId( $results[1]['entityId'] ) );
		$this->assertSame( 'http://example.com/1',
			$this->singleStatementValue( $item1, self::URL_PROPERTY ) );
		$this->assertSame( 'http://example.com/2',
			$this->singleStatementValue( $item2, self::URL_PROPERTY ) );
		$this->assertEquals( $this->singleStatementValue( $item1, self::ITEM_PROPERTY ),
			$this->singleStatementValue( $item2, self::ITEM_PROPERTY ) );
	}

	private function singleStatementValue( Item $item, string $propertyId ) {
		$snaks = $item->getStatements()
			->getByPropertyId( new PropertyId( $propertyId ) )
			->getMainSnaks();
		$this->assertCount( 1, $snaks );
		/** @var PropertyValueSnak $snak */
		$snak = $snaks[0];
		$this->assertInstanceOf( PropertyValueSnak::class, $snak );
		return $snak->getDataValue()->getValue();
	}

	/** @dataProvider provideTokenParameters */
	public function testTokenParameter( ?string $token, bool $safeAgainstCsrf ): void {
		$params = [
			'entities' => 1,
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
		$this->assertSame( 'wikibasereconcileedit-editendpoint-invalid-reconcile-parameter',
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
			'entities' => 1,
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

	private function getMockSessionProvider( bool $safeAgainstCSRF ): SessionProviderInterface {
		$provider = $this->createMock( SessionProviderInterface::class );
		$provider->method( 'safeAgainstCsrf' )->willReturn( $safeAgainstCSRF );
		return $provider;
	}

}
