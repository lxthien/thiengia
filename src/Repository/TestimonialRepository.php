<?php

namespace App\Repository;

use App\Entity\Testimonial;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TestimonialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Testimonial::class);
    }

    /**
     * @return Testimonial[]
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC']);
    }

    /**
     * @return Testimonial[]
     */
    public function findActiveOrdered(): array
    {
        return $this->findBy(['enable' => true], ['position' => 'ASC']);
    }
}
