<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\PHPUnit;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Idea from DoctrineTestBundle & hautelook/AliceBundle:
 * We want to force the test DB to have the current schema and load all test
 * fixtures (group=test) so the DB is in a known state for each test.
 *
 * static::$fixtureGroups can be customized in setUpBeforeClass()
 */
trait RefreshDatabaseTrait
{
    /**
     * @var array fixture group(s) to apply
     */
    protected static $fixtureGroups = ['test'];

    /**
     * @var bool Flag whether db schema was updated/checked or not
     */
    protected static $schemaUpdated = false;

    /**
     * @var array
     */
    protected static $fixtures;

    /**
     * Called on each test that calls bootKernel() or uses createClient().
     */
    protected static function bootKernel(array $options = []): KernelInterface
    {
        static::ensureKernelTestCase();

        $kernel = parent::bootKernel($options);
        $container = static::$container ?? static::$kernel->getContainer();

        // only required on the first test: make sure the db schema is up to date
        if (!static::$schemaUpdated) {
            static::updateSchema($container);
            static::$schemaUpdated = true;
        }

        // now load any fixtures configured for "test" (or overwritten groups)
        $fixtures = static::getFixtures($container);

        $executor = static::getExecutor($container);

        // Purge even when no fixtures are defined, e.g. for tests that require
        // an empty database, like import tests.
        // fix for PHP8: purge separately as execute() would wrap the TRUNCATE
        // in a transaction which is auto-committed by MySQL when DDL queries
        // are executed which throws an exception in the entitymanager
        // ("There is no active transaction", @see https://github.com/doctrine/migrations/issues/1104)
        // because he does not check if a transaction is still open before
        // calling commit().
        $executor->purge();

        if (count($fixtures)) {
            $executor->execute($fixtures, true);
        }

        return $kernel;
    }

    protected static function ensureKernelTestCase(): void
    {
        if (!is_a(static::class, KernelTestCase::class, true)) {
            throw new LogicException(sprintf('The test class must extend "%s" to use "%s".', KernelTestCase::class, static::class));
        }
    }

    /**
     * Brings the db schema to the newest version.
     */
    protected static function updateSchema(ContainerInterface $container): void
    {
        $em = $container->get('doctrine.orm.entity_manager');
        /* @var $em EntityManagerInterface */

        $metadatas = $em->getMetadataFactory()->getAllMetadata();
        if (empty($metadatas)) {
            return;
        }

        $schemaTool = new SchemaTool($em);
        $schemaTool->updateSchema($metadatas, false);
    }

    /**
     * Use a static fixture cache as we need them before each test.
     */
    protected static function getFixtures(ContainerInterface $container): array
    {
        if (empty(static::$fixtureGroups)) {
            // the fixture loader returns all possible fixtures if called
            // with an empty array -> catch here
            return [];
        }

        if (is_array(static::$fixtures)) {
            return static::$fixtures;
        }

        $fixturesLoader = $container->get('doctrine.fixtures.loader');
        static::$fixtures = $fixturesLoader->getFixtures(static::$fixtureGroups);

        return static::$fixtures;
    }

    /**
     * Returns a new executor instance, we need it before each test execution.
     */
    protected static function getExecutor(ContainerInterface $container): ORMExecutor
    {
        $em = $container->get('doctrine.orm.entity_manager');
        /* @var $em EntityManagerInterface */

        $purger = new ORMPurger($em);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);

        // don't use a static Executor, it contains the EM which could be closed
        // through (expected) exceptions and would not work
        return new ORMExecutor($em, $purger);
    }

    protected static function fixtureCleanup(): void
    {
        static::$fixtures = null;
    }
}
