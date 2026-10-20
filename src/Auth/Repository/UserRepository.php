<?php

/* This repository will help access information about users. */

namespace App\Auth\Repository;

use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\UserEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserRepository extends ServiceEntityRepository implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserEntity::class);
    }
    
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $identifier = mb_strtolower($identifier);

            $user = $this->createQueryBuilder('u')
                ->where('LOWER(u.email) = :email')
                ->andWhere('u.isDeleted = :isDeleted')
                ->setParameter('email', $identifier)
                ->setParameter('isDeleted', false)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException $ex) {
            //TODO: log this error message
            $exception = new UserNotFoundException("User not found.");
            $exception->setUserIdentifier($identifier);
            throw $exception;
        }

        if (!$user) {
            $exception = new UserNotFoundException("User not found.");
            $exception->setUserIdentifier($identifier);
            throw $exception;
        }

        return $user;
    }

    /**
     * Finds all users belonging to a specific organization.
     * @return UserEntity[]
     */
    public function findByOrganization(OrganizationEntity $organization): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.primaryOrganization = :org')
            ->setParameter('org', $organization)
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof UserEntity) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $refreshedUser = $this->find($user->getId());

        if (!$refreshedUser) {
            throw new UnsupportedUserException('Invalid user-database state.');
        }

        return $refreshedUser;
    }

    public function supportsClass(string $class): bool
    {
        // Check if $class is the UserEntity or a subclass (like a Doctrine proxy)
        return $class === UserEntity::class || is_subclass_of($class, UserEntity::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof UserEntity) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }
        $user->setPassword($newHashedPassword);
        $this->_em->persist($user);
        $this->_em->flush();
    }

}
