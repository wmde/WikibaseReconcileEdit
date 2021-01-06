<?PHP

namespace MediaWiki\Extension\WikibaseReconcileEdit\Maintenance;

use DataValues\StringValue;
use User;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementGuid;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Repo\WikibaseRepo;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	require_once getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php';
} else {
	require_once __DIR__ . '/../../../maintenance/Maintenance.php';
}

class Seed extends \Maintenance {
	public function execute() {
		$store = WikibaseRepo::getDefaultInstance()->getEntityStore();
		$user = User::newSystemUser( 'WikibaseReconcileEditSeeder' );
		$time = time();

		// Create the property
		$propertyRevision = $store->saveEntity(
			new Property(
				null,
				new Fingerprint( new TermList( [ new Term( 'en', 'ON Source URL Property - ' . $time ) ] ) ),
				'url'
			),
			'Seeding data', $user, EDIT_NEW
		);
		$reconciliationPropertyId = $propertyRevision->getEntity()->getId();

		// Create base item
		$itemRevision = $store->saveEntity(
			new Item(
				null,
				new Fingerprint( new TermList( [ new Term( 'en', 'ON Test Item ' . $time ) ] ) )
			),
			'Seeding data', $user, EDIT_NEW
		);
		$itemId = $itemRevision->getEntity()->getId();

		// Add statement
		$store->saveEntity(
			new Item(
				$itemId,
				new Fingerprint( new TermList( [ new Term( 'en', 'ON Test Item ' . $time ) ] ) ),
				null,
				new StatementList( [ new Statement(
					new PropertyValueSnak(
						$reconciliationPropertyId,
						new StringValue( 'https://github.com/addshore/test1' )
					),
					null, null,
					( new StatementGuid( $itemId, 'bf83777b-4572-1680-0177-0505789ec6ee' ) )->__toString()
					) ] )
			),
			'Seeding data', $user
		);

		echo "PropertyId: " . $reconciliationPropertyId->getSerialization() . PHP_EOL;
		echo "ItemId: " . $itemId->getSerialization() . PHP_EOL;
		echo "Done!" . PHP_EOL;
	}
}

$maintClass = Seed::class;
require_once RUN_MAINTENANCE_IF_MAIN;
