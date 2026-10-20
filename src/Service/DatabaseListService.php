<?php

namespace App\Service;

use App\Auth\Entity\UserEntity;
use App\Auth\Roles;
use App\Entity\DatabaseMetadata;
use App\Enum\DatabaseMetadataType;
use App\Repository\DatabaseMetadataRepository;
use Symfony\Bundle\SecurityBundle\Security;

class DatabaseListService
{
    public function __construct(
        private readonly DatabaseMetadataRepository $dbRepo,
        private readonly Security $security,
    )
    {
    }

    /**
     * Gets the default database entity.  Authorization checks are
     * performed to ensure the proper default database is returned.
     * @return {DatabaseMetadata} database object, or null if no databases found
     */
    public function getDefaultDatabase(): ?DatabaseMetadata
    {
        $user = $this->security->getUser();

        // If the user is logged in fetch the list of databases available to the user
        if ($user) {
            $org = $user->getPrimaryOrganization();
            // If the user is part of an organization, fetch the list of databases available
            // to that organization
            if ($org) {
                $database = $this->dbRepo->findDefaultOrganizationDatabase(organization: $org);

            // Super admins get all databases
            } else if ($this->security->isGranted(Roles::ROLE_SUPER_ADMIN)) {
                $database = $this->dbRepo->findDefaultDatabase();

            // If there is no org, then display all public databases
            } else {
                $database = $this->dbRepo->findDefaultPublicDatabase();
            }
        } else {
            $database = $this->dbRepo->findDefaultPublicDatabase();
        }

        return $database;
    }

    /**
     * Gets the database entity corresponding to the provided ID.  Authorization checks are
     * performed to ensure the active user has access to the requested database.
     * @param {int} $databaseId - database ID
     * @return {DatabaseMetadata} database object, or null if invalid or unauthorized
     */
    public function getDatabase(int $databaseId): ?DatabaseMetadata
    {
        $user = $this->security->getUser();

        // If the user is logged in fetch the list of databases available to the user
        if ($user) {
            $org = $user->getPrimaryOrganization();
            // If the user is part of an organization, fetch the list of databases available
            // to that organization
            if ($org) {
                $database = $this->dbRepo->findActiveByOrganizationAndPublicById($org, $databaseId);

            // Super admins get all databases
            } else if ($this->security->isGranted(Roles::ROLE_SUPER_ADMIN)) {
                $database = $this->dbRepo->findActiveById($databaseId);

            // If there is no org, then display all public databases
            } else {
                $database = $this->dbRepo->findActivePublicDatabaseById($databaseId);
            }
        } else {
            $database = $this->dbRepo->findActivePublicDatabaseById($databaseId);
        }

        return $database;
    }

    /**
     * Retrieves a list of active databases based on the user role and login state.
     * @param {DatabaseMetadataType} $dbType - type of the database (e.g. EST or Metadata,
     *     default DatabaseMetadataType::Metadata)
     * @param {UserEntity} $user - user to query for list of active databases; if null,
     *     user is determined from security
     * @return {DatabaseMetadata[]} array of database objects
     */
    public function getActiveDatabases(
        ?DatabaseMetadataType $dbType = DatabaseMetadataType::EST,
        ?UserEntity $user = null,
    ): array
    {
        if ($user === null) {
            $user = $this->security->getUser();
        }

        // If the user is logged in fetch the list of databases available to the user
        if ($user) {
            $org = $user->getPrimaryOrganization();
            // If the user is part of an organization, fetch the list of databases available
            // to that organization
            if ($org) {
                $activeDatabases = $this->dbRepo->findActiveByOrganizationAndPublic($org, dbType: $dbType);

            // Super admins get all databases
            } else if ($this->security->isGranted(Roles::ROLE_SUPER_ADMIN)) {
                $activeDatabases = $this->dbRepo->findAllActiveOrderedByName(dbType: $dbType);

            // If there is no org, then display all public databases
            } else {
                $activeDatabases = $this->dbRepo->findActivePublicDatabases(dbType: $dbType);
            }
        } else {
            $activeDatabases = $this->dbRepo->findActivePublicDatabases(dbType: $dbType);
        }

        return $activeDatabases;
    }

    /**
     * Retrieves a list of database IDs that a user has access to.  This is cached for typeahead
     * performance.
     * @param {UserEntity} $user - user
     * @return {array} array of integer database IDs
     */
    public function getAllowedDatabaseIdsForUser(
        UserEntity $user,
    ): array
    {
        return array_map(fn(DatabaseMetadata $db) => $db->getId(), $this->getActiveDatabases(user: $user));
    }
}
