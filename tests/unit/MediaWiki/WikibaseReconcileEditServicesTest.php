<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\MediaWiki;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\WikibaseReconcileEditServices;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\WikibaseReconcileEditServices
 */
class WikibaseReconcileEditServicesTest extends TestCase {

	public function testServiceAccessorsExistInRightOrder() {
		$serviceInstantiators = require __DIR__ . '/../../../includes/ServiceWiring.php';
		$serviceWiringServiceNames = [];
		foreach ( $serviceInstantiators as $serviceName => $serviceInstantiator ) {
			$prefix = 'WikibaseReconcileEdit.';
			$this->assertStringStartsWith( $prefix, $serviceName );
			$serviceWiringServiceNames[] = substr( $serviceName, strlen( $prefix ) );
		}

		$reflectionClass = new ReflectionClass( WikibaseReconcileEditServices::class );
		$accessorServiceNames = [];
		foreach ( $reflectionClass->getMethods() as $method ) {
			if ( $method->isConstructor() ) {
				continue;
			}
			$methodName = $method->getName();
			$prefix = 'get';
			$this->assertStringStartsWith( $prefix, $methodName );
			$accessorServiceNames[] = substr( $methodName, strlen( $prefix ) );
		}

		$this->assertSame( $serviceWiringServiceNames, $accessorServiceNames );
	}

}
