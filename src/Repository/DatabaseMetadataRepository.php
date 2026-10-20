<?php

namespace App\Repository;

use App\Auth\Entity\OrganizationEntity;
use App\Config\Settings;
use App\Dto\DatabaseMetadataListItem;
use App\Entity\DatabaseMetadata;
use App\Entity\MetagenomeMetadata;
use App\Entity\SequenceDatabaseMetadata;
use App\Enum\DatabaseMetadataType;
use App\Service\ConfigurationManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @extends ServiceEntityRepository<DatabaseMetadata>
 */
class DatabaseMetadataRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(lazy: true)]
        private readonly ConfigurationManager $config,
    )
    {
        parent::__construct($registry, DatabaseMetadata::class);
    }
 
    /**
     * Find all databases, active and inactive, ordered by id.
     * @return DatabaseMetadataListItem[] - array of database metadata entity objects, DTO wrapper
     *     to include extra metadata
     */
    public function findAllWithDefaultFlag(): array
    {
        $results = $this->createQueryBuilderWithDatabaseType(null)
            ->select('d as database')
            ->addSelect('CASE WHEN d.id = o.defaultDatabase THEN 1 ELSE 0 END as isDefault')
            ->leftJoin('d.organization', 'o')
            ->getQuery()
            ->getResult();

        return array_map(function (array $row) {
            return new DatabaseMetadataListItem(
                database: $row['database'],
                isOrgDefault: (bool)$row['isDefault'],
            );
        }, $results);
    }

    /**
     * Find all active databases, public and org, ordered by default status, then name.
     * Should only be used for superadmins.
     * @param {DatabaseMetadataType} $dbType - set to specify what type of databases to retrieve
     *     (e.g. EST or Metagenome); default EST
     * @return DatabaseMetadata[] - array of database metadata entity objects
     */
    public function findAllActiveOrderedByName(DatabaseMetadataType $dbType = DatabaseMetadataType::EST): array
    {
        return
            $this->prepareFindAllActiveByNameQuery($dbType)
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds a database.  Should only be used for superadmins.
     * @param {int} $databaseId - database ID
     * @return DatabaseMetadata - database metadata entity object
     */
    public function findActiveById(int $databaseId): ?DatabaseMetadata
    {
        $qb = $this->createQueryBuilderWithDatabaseType(null)
            ->andWhere('d.isActive = :active')
            ->andWhere('d.id = :id')
            ->setParameter('id', $databaseId)
            ->setParameter('active', true);

        return $qb
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all active databases for the given org, as well as all public databases,
     * ordered by default status, then name.
     * @param {OrganizationEntity} $organization - organization entity
     * @param {DatabaseMetadataType} $dbType - set to specify what type of databases to retrieve
     *     (e.g. EST or Metagenome); default EST
     * @return DatabaseMetadata[] - array of database metadata entity objects
     */
    public function findActiveByOrganizationAndPublic(OrganizationEntity $organization, DatabaseMetadataType $dbType = DatabaseMetadataType::EST): array
    {
        return
            $this->prepareFindActiveByOrganizationAndPublicQuery($organization, $dbType)
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds a database; takes authorization level into account.
     * @param {OrganizationEntity} $organization - organization entity that the given user belongs to
     * @param {int} $databaseId - database ID
     * @return DatabaseMetadata - database metadata entity object
     */
    public function findActiveByOrganizationAndPublicById(OrganizationEntity $organization, int $databaseId): ?DatabaseMetadata
    {
        $qb = $this->createQueryBuilderWithDatabaseType(null)
            ->leftJoin('d.organization', 'o');

        $qb = $this->addOrganizationOrPublicClause($qb)
            ->andWhere('d.isActive = :active')
            ->andWhere('d.id = :id')
            ->setParameter('id', $databaseId)
            ->setParameter('org', $organization)
            ->setParameter('public', true)
            ->setParameter('active', true);

        return $qb
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all databases for the given org, as well as all public databases, by default status,
     * then name.  Use in admin pages.
     * @param {OrganizationEntity} $organization - organization entity
     * @return DatabaseMetadataListItem[] - array of database metadata entity objects, DTO wrapper
     *     to include extra metadata
     */
    public function findAllByOrganizationAndPublic(OrganizationEntity $organization): array
    {
        $qb = $this->createQueryBuilderWithDatabaseType(null)
            ->leftJoin('d.organization', 'o')
            ->select('d as database')
            ->addSelect('CASE WHEN d.id = o.defaultDatabase THEN 1 ELSE 0 END as isDefault')
            ->where('d.organization = :org');

        $results =
            $this->addOrganizationOrPublicClause($qb)
            ->setParameter('public', true)
            ->setParameter('org', $organization)
            ->getQuery()
            ->getResult();

        return array_map(function (array $row) {
            return new DatabaseMetadataListItem(
                database: $row['database'],
                isOrgDefault: (bool)$row['isDefault'],
            );
        }, $results);
    }

    /**
     * Find all active public databases (no org databases), ordered by default status, then name.
     * @param {DatabaseMetadataType} $dbType - set to specify what type of databases to retrieve
     *     (e.g. EST or Metagenome); default EST
     * @return DatabaseMetadata[] - array of database metadata entity objects
     */
    public function findActivePublicDatabases(DatabaseMetadataType $dbType = DatabaseMetadataType::EST): array
    {
        return
            $this->prepareFindActivePublicDatabasesQuery($dbType)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find an active public database.
     * @param {int} $databaseId - database ID
     * @return DatabaseMetadata - database metadata entity object
     */
    public function findActivePublicDatabaseById(int $databaseId): ?DatabaseMetadata
    {
        $qb = $this->createQueryBuilderWithDatabaseType(null)
            ->andWhere('d.isActive = :active')
            ->andWhere('d.isPublic = :public')   // Publicly accessible databases
            ->andWhere('d.organization IS NULL') // Publicly accessible databases
            ->andWhere('d.id = :id')
            ->setParameter('id', $databaseId)
            ->setParameter('active', true)
            ->setParameter('public', true);

        return $qb
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Finds the default database for the given org, also condiering public databases.
     * @param {OrganizationEntity} $organization - organization entity
     * @param {DatabaseMetadataType} $dbType - set to specify what type of databases to retrieve
     *     (e.g. EST or Metagenome); default EST
     * @return DatabaseMetadata - database metadata entity object
     */
    public function findDefaultOrganizationDatabase(OrganizationEntity $organization, DatabaseMetadataType $dbType = DatabaseMetadataType::EST): ?DatabaseMetadata
    {
        return
            $this->prepareFindActiveByOrganizationAndPublicQuery($organization, $dbType)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Finds the default database for all orgs, also condiering public databases.
     * Use for superadmins only.
     * @param {DatabaseMetadataType} $dbType - set to specify what type of databases to retrieve
     *     (e.g. EST or Metagenome); default EST
     * @return DatabaseMetadata - database metadata entity object
     */
    public function findDefaultDatabase(DatabaseMetadataType $dbType = DatabaseMetadataType::EST): ?DatabaseMetadata
    {
        return
            $this->prepareFindAllActiveByNameQuery($dbType)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Finds the default database for a public user.
     * @param {DatabaseMetadataType} $dbType - set to specify what type of databases to retrieve
     *     (e.g. EST or Metagenome); default EST
     * @return DatabaseMetadata - database metadata entity object
     */
    public function findDefaultPublicDatabase(DatabaseMetadataType $dbType = DatabaseMetadataType::EST): ?DatabaseMetadata
    {
        return
            $this->prepareFindActivePublicDatabasesQuery($dbType)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }




    private function prepareFindAllActiveByNameQuery(DatabaseMetadataType $dbType): QueryBuilder
    {
        $qb = $this->createQueryBuilderWithDatabaseType($dbType)
            ->leftJoin('d.organization', 'o')
            ->andWhere('d.isActive = :active')
            ->setParameter('active', true);

        return $this->addOrderByDefaultDatabase(qb: $qb);
    }

    private function prepareFindActivePublicDatabasesQuery(DatabaseMetadataType $dbType): QueryBuilder
    {
        $qb = $this->createQueryBuilderWithDatabaseType($dbType)
            ->andWhere('d.isActive = :active')
            ->andWhere('d.isPublic = :public')   // Publicly accessible databases
            ->andWhere('d.organization IS NULL') // Publicly accessible databases
            ->setParameter('active', true)
            ->setParameter('public', true);

        return $this->addOrderByDefaultDatabase(qb: $qb, excludeOrg: true);
    }

    private function prepareFindActiveByOrganizationAndPublicQuery(OrganizationEntity $organization, DatabaseMetadataType $dbType): QueryBuilder
    {
        $qb = $this->createQueryBuilderWithDatabaseType($dbType)
            ->leftJoin('d.organization', 'o');

        $qb = $this->addOrganizationOrPublicClause($qb)
            ->andWhere('d.isActive = :active')
            ->setParameter('org', $organization)
            ->setParameter('public', true)
            ->setParameter('active', true);

        return $this->addOrderByDefaultDatabase(qb: $qb);
    }

    private function createQueryBuilderWithDatabaseType(?DatabaseMetadataType $dbType): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d');

        if ($dbType === DatabaseMetadataType::EST) {
            $qb->andWhere('d INSTANCE OF App\Entity\SequenceDatabaseMetadata');
        } else if ($dbType === DatabaseMetadataType::Metagenome) {
            $qb->andWhere('d INSTANCE OF App\Entity\MetagenomeMetadata');
        }

        return $qb;
    }

    private function addOrganizationOrPublicClause(QueryBuilder $qb): QueryBuilder
    {
        return $qb
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->eq('d.organization', ':org'),
                    $qb->expr()->andX(
                        $qb->expr()->isNull('d.organization'),
                        $qb->expr()->eq('d.isPublic', ':public')
                    )
                )
            );
    }

    /**
     * Add ORDER BY clauses to the QueryBuilder.
     * @param {QueryBuilder} $qb - query builder
     * @param {bool} $excludeOrg - set to true to not include the clause for joining to
     *     organization (for ordering by organization default database)
     * @return {QueryBuilder} query builder
     */
    private function addOrderByDefaultDatabase(QueryBuilder $qb, bool $excludeOrg = false): QueryBuilder
    {
        $publicDefaultDb = $this->config->get(Settings::DEFAULT_DATABASE_VERSION);

        $qb->orderBy(
                $qb->expr()->asc(
                    'CASE WHEN d.dbName = :globalDefault THEN 0 ELSE 1 END'
                )
            )
            ->setParameter('globalDefault', $publicDefaultDb);

        if (!$excludeOrg) {
            $qb->addOrderBy(
                    $qb->expr()->asc(
                        'CASE WHEN d.id = o.defaultDatabase THEN 0 ELSE 1 END'
                    )
                );
        }

        $qb->addOrderBy('d.publicName', 'ASC');

        return $qb;
    }
}
