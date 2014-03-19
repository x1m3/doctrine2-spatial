<?php
/**
 * Copyright (C) 2012, 2014 Derek J. Lambert
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace CrEOF\Spatial\Tests;

use CrEOF\Spatial\Exception\UnsupportedPlatformException;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Abstract ORM test class
 *
 * @author  Derek J. Lambert <dlambert@dereklambert.com>
 * @license http://dlambert.mit-license.org MIT
 */
abstract class OrmTest extends \PHPUnit_Framework_TestCase
{
    const GEOMETRY_ENTITY         = 'CrEOF\Spatial\Tests\Fixtures\GeometryEntity';
    const NO_HINT_GEOMETRY_ENTITY = 'CrEOF\Spatial\Tests\Fixtures\NoHintGeometryEntity';
    const POINT_ENTITY            = 'CrEOF\Spatial\Tests\Fixtures\PointEntity';
    const LINESTRING_ENTITY       = 'CrEOF\Spatial\Tests\Fixtures\LineStringEntity';
    const POLYGON_ENTITY          = 'CrEOF\Spatial\Tests\Fixtures\PolygonEntity';
    const GEOGRAPHY_ENTITY        = 'CrEOF\Spatial\Tests\Fixtures\GeographyEntity';

    /**
     * @var bool
     */
    protected static $platformSetup;

    /**
     * @var Connection
     */
    protected static $sharedConnection;

    /**
     * @var array
     */
    protected static $entitiesCreated = array();

    /**
     * @var array
     */
    protected static $typesAdded = array();

    /**
     * @var string[]
     */
    protected static $types = array(
        'geometry'      => 'CrEOF\Spatial\DBAL\Types\GeometryType',
        'point'         => 'CrEOF\Spatial\DBAL\Types\Geometry\PointType',
        'linestring'    => 'CrEOF\Spatial\DBAL\Types\Geometry\LineStringType',
        'polygon'       => 'CrEOF\Spatial\DBAL\Types\Geometry\PolygonType',
        'geography'     => 'CrEOF\Spatial\DBAL\Types\GeographyType',
        'geopoint'      => 'CrEOF\Spatial\DBAL\Types\Geography\PointType',
        'geolinestring' => 'CrEOF\Spatial\DBAL\Types\Geography\LineStringType',
        'geopolygon'    => 'CrEOF\Spatial\DBAL\Types\Geography\PolygonType'
    );

    /**
     * @var array[]
     */
    protected static $entities = array(
        'geometry' => array(
            'class' => self::GEOMETRY_ENTITY,
            'types' => array('geometry'),
            'table' => 'GeometryEntity'
        ),
        'no_hint_geometry' => array(
            'class' => self::NO_HINT_GEOMETRY_ENTITY,
            'types' => array('geometry'),
            'table' => 'NoHintGeometryEntity'
        ),
        'point' => array(
            'class' => self::POINT_ENTITY,
            'types' => array('point'),
            'table' => 'PointEntity'
        ),
        'linestring' => array(
            'class' => self::LINESTRING_ENTITY,
            'types' => array('linestring'),
            'table' => 'LineStringEntity'
        ),
        'polygon' => array(
            'class' => self::POLYGON_ENTITY,
            'types' => array('polygon'),
            'table' => 'PolygonEntity'
        ),
        'geography' => array(
            'class' => self::GEOGRAPHY_ENTITY,
            'types' => array('geography'),
            'table' => 'GeographyEntity'
        )
    );

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var SchemaTool
     */
    protected $schemaTool;

    /**
     * @var string[]
     */
    protected $usedEntities;

    /**
     * @var DebugStack
     */
    protected $sqlLoggerStack;

    /**
     * @var array
     */
    protected $usedTypes;

    /**
     * @var Cache
     */
    private static $metadataCache;

    /**
     * @var Cache
     */
    private static $queryCache;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->usedTypes    = array();
        $this->usedEntities = array();
    }

    /**
     * @return Connection
     * @throws UnsupportedPlatformException
     */
    public static function getConnection()
    {
        $parameters   = array(
            'driver'   => $GLOBALS['db_type'],
            'host'     => $GLOBALS['db_host'],
            'port'     => $GLOBALS['db_port'],
            'user'     => $GLOBALS['db_username'],
            'password' => $GLOBALS['db_password']
        );
        $databaseName = $GLOBALS['db_name'];
        $connection   = DriverManager::getConnection($parameters);

        try {
            $connection->getSchemaManager()->dropDatabase($databaseName);
        } catch (\Doctrine\DBAL\DBALException $e) {
            // We don't care if database didn't exist
        }

        $connection->getSchemaManager()->createDatabase($databaseName);
        $connection->close();

        $parameters['dbname'] = $databaseName;
        $connection           = DriverManager::getConnection($parameters, new Configuration());
        $platform             = $connection->getDatabasePlatform()->getName();

        switch ($platform) {
            case 'postgresql':
                $connection->exec('CREATE EXTENSION postgis');
                break;
            case 'mysql':
                break;
            default:
                throw UnsupportedPlatformException::unsupportedPlatform($platform);
                break;
        }

        return $connection;
    }

    /**
     * @throws UnsupportedPlatformException
     */
    protected function setUp()
    {
        if ( ! isset(static::$sharedConnection)) {
            static::$sharedConnection = static::getConnection();
        }

        if (null === $this->entityManager) {
            $this->entityManager = $this->getEntityManager();
            $this->schemaTool    = new SchemaTool($this->entityManager);
        }

        $this->sqlLoggerStack->enabled = true;

        $this->setUpTypes();
        $this->setUpEntities();
        $this->setupFunctions();
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        if (null === self::$metadataCache) {
            if (isset($GLOBALS['DOCTRINE_CACHE_IMPL'])) {
                self::$metadataCache = new $GLOBALS['DOCTRINE_CACHE_IMPL'];
            } else {
                self::$metadataCache = new ArrayCache();
            }
        }

        if (null === self::$queryCache) {
            self::$queryCache = new ArrayCache();
        }

        $this->sqlLoggerStack          = new DebugStack();
        $this->sqlLoggerStack->enabled = false;
        $connection                    = static::$sharedConnection;

        /** @var Configuration $configuration */
        $configuration = $connection->getConfiguration();

        $configuration->setMetadataCacheImpl(self::$metadataCache);
        $configuration->setQueryCacheImpl(self::$queryCache);
        $configuration->setProxyDir(__DIR__ . '/Proxies');
        $configuration->setProxyNamespace(__NAMESPACE__ . '\Proxies');
        $configuration->setMetadataDriverImpl($configuration->newDefaultAnnotationDriver(array(), true));
        $configuration->setSQLLogger($this->sqlLoggerStack);

//        $eventManager = $connection->getEventManager();
//
//        foreach ($eventManager->getListeners() as $event => $listeners) {
//            foreach ($listeners as $listener) {
//                $eventManager->removeEventListener(array($event), $listener);
//            }
//        }
//
//        if (isset($GLOBALS['db_event_subscribers'])) {
//            foreach (explode(",", $GLOBALS['db_event_subscribers']) as $subscriberClass) {
//                $subscriberInstance = new $subscriberClass();
//                $eventManager->addEventSubscriber($subscriberInstance);
//            }
//        }
//
//        if (isset($GLOBALS['debug_uow_listener'])) {
//            $eventManager->addEventListener(array('onFlush'), new \Doctrine\ORM\Tools\DebugUnitOfWorkListener());
//        }

        return EntityManager::create($connection, $configuration);
    }

    /**
     * Create entities used by tests
     */
    protected function setUpEntities()
    {
        $classes = array();

        foreach ($this->usedEntities as $entityName => $bool) {
            if ( ! isset(static::$entitiesCreated[$entityName])) {
                $classes[] = $this->entityManager->getClassMetadata(static::$entities[$entityName]['class']);

                static::$entitiesCreated[$entityName] = true;
            }
        }

        if (0 !== count($classes)) {
            $this->schemaTool->createSchema($classes);
        }
    }

    /**
     * @param string $typeName
     */
    protected function useType($typeName)
    {
        $this->usedTypes[$typeName] = true;
    }

    /**
     * @param string $entityName
     */
    protected function useEntity($entityName)
    {
        $this->usedEntities[$entityName] = true;

        foreach (static::$entities[$entityName]['types'] as $type) {
            $this->useType($type);
        }
    }

    /**
     * @return array
     */
    protected function getEntityClasses()
    {
        return array_column(array_intersect_key(static::$entities, static::$entitiesCreated), 'class');
    }

    /**
     * Add types used by test to DBAL
     */
    protected function setUpTypes()
    {
        foreach ($this->usedTypes as $typeName => $bool) {
            if ( ! isset(static::$typesAdded[$typeName])) {
                Type::addType($typeName, static::$types[$typeName]);

                // Since doctrineTypeComments may already be initialized check if added type requires comment
                if (Type::getType($typeName)->requiresSQLCommentHint($this->getPlatform())) {
                    $this->getPlatform()->markDoctrineTypeCommented($typeName);
                }

                static::$typesAdded[$typeName] = true;
            }
        }
    }

    /**
     * Setup DQL functions
     */
    protected function setUpFunctions()
    {
        if ($this->getPlatform()->getName() == 'postgresql') {
            $this->entityManager->getConfiguration()->addCustomStringFunction('st_asbinary', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STAsBinary');
            $this->entityManager->getConfiguration()->addCustomStringFunction('st_astext', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STAsText');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('st_area', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STArea');
            $this->entityManager->getConfiguration()->addCustomStringFunction('st_centroid', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCentroid');
            $this->entityManager->getConfiguration()->addCustomStringFunction('st_closestpoint', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STClosestPoint');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('st_contains', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STContains');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('st_containsproperly', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STContainsProperly');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('st_covers', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCovers');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('st_coveredby', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCoveredBy');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('st_crosses', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCrosses');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('st_disjoint', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STDisjoint');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('st_distance', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STDistance');
            $this->entityManager->getConfiguration()->addCustomStringFunction('st_envelope', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STEnvelope');
            $this->entityManager->getConfiguration()->addCustomStringFunction('st_geomfromtext', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STGeomFromText');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('st_length', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STLength');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('st_linecrossingdirection', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STLineCrossingDirection');
            $this->entityManager->getConfiguration()->addCustomStringFunction('st_startpoint', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STStartPoint');
            $this->entityManager->getConfiguration()->addCustomStringFunction('st_summary', 'CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STSummary');
        }

        if ($this->getPlatform()->getName() == 'mysql') {
            $this->entityManager->getConfiguration()->addCustomNumericFunction('area', 'CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Area');
            $this->entityManager->getConfiguration()->addCustomStringFunction('asbinary', 'CrEOF\Spatial\ORM\Query\AST\Functions\MySql\AsBinary');
            $this->entityManager->getConfiguration()->addCustomStringFunction('astext', 'CrEOF\Spatial\ORM\Query\AST\Functions\MySql\AsText');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('contains', 'CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Contains');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('disjoint', 'CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Disjoint');
            $this->entityManager->getConfiguration()->addCustomStringFunction('envelope', 'CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Envelope');
            $this->entityManager->getConfiguration()->addCustomStringFunction('geomfromtext', 'CrEOF\Spatial\ORM\Query\AST\Functions\MySql\GeomFromText');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('glength', 'CrEOF\Spatial\ORM\Query\AST\Functions\MySql\GLength');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('mbrcontains', 'CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBRContains');
            $this->entityManager->getConfiguration()->addCustomNumericFunction('mbrdisjoint', 'CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBRDisjoint');
            $this->entityManager->getConfiguration()->addCustomStringFunction('startpoint', 'CrEOF\Spatial\ORM\Query\AST\Functions\MySql\StartPoint');
        }
    }

    /**
     * Teardown fixtures
     */
    protected function tearDown()
    {
        $conn = static::$sharedConnection;

        $this->sqlLoggerStack->enabled = false;

        foreach ($this->usedEntities as $entityName => $bool) {
            $conn->executeUpdate(sprintf('DELETE FROM %s', static::$entities[$entityName]['table']));
        }

        $this->entityManager->clear();
    }

    /**
     * @return AbstractPlatform
     */
    protected function getPlatform()
    {
        return static::$sharedConnection->getDatabasePlatform();
    }
}
