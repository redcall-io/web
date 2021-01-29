<?php

namespace App\Repository;

use App\Entity\User;
use Bundles\PasswordLoginBundle\Entity\AbstractUser;
use Bundles\PasswordLoginBundle\Repository\AbstractUserRepository;
use Bundles\PasswordLoginBundle\Repository\UserRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\QueryBuilder;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends AbstractUserRepository implements UserRepositoryInterface
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(AbstractUser $user)
    {
        $this->_em->persist($user);
        $this->_em->flush($user);
    }

    public function remove(AbstractUser $user)
    {
        $this->_em->remove($user);
        $this->_em->flush($user);
    }

    public function searchQueryBuilder(?string $criteria, ?bool $onlyAdmins, ?bool $onlyDevelopers) : QueryBuilder
    {
        $qb = $this->createQueryBuilder('u');

        $qb
            ->leftJoin('u.volunteer', 'v')
            ->leftJoin('v.phones', 'p')
            ->where(
                $qb->expr()->orX(
                    'u.username LIKE :criteria',
                    'v.nivol LIKE :criteria',
                    'v.firstName LIKE :criteria',
                    'v.lastName LIKE :criteria',
                    'v.email LIKE :criteria',
                    'p.e164 LIKE :criteria',
                    'p.national LIKE :criteria',
                    'p.international LIKE :criteria',
                    'CONCAT(v.firstName, \' \', v.lastName) LIKE :criteria',
                    'CONCAT(v.lastName, \' \', v.firstName) LIKE :criteria'
                )
            )
            ->setParameter('criteria', sprintf('%%%s%%', $criteria))
            ->addOrderBy('u.registeredAt', 'DESC')
            ->addOrderBy('u.username', 'ASC');

        if ($onlyAdmins) {
            $qb->andWhere('u.isAdmin = true');
        }

        if ($onlyDevelopers) {
            $qb->andWhere('u.isDeveloper = true');
        }

        return $qb;
    }
}

