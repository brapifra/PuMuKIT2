<?php

namespace Pumukit\SchemaBundle\Repository;

use Doctrine\ODM\MongoDB\DocumentRepository;

/**
 * BroadcastRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class BroadcastRepository extends DocumentRepository
{
  /**
   * Find default selected broadcast
   *
   * @return Broadcast
   */
  public function findDefaultSel()
  {
    return $this->createQueryBuilder()
      ->field('default_sel')->equals(true)
      ->getQuery()
      ->getSingleResult();
  }

 /**
   * Find public broadcast
   *
   * @return Broadcast
   */
  public function findPublicBroadcast()
  {
    return $this->createQueryBuilder()
      ->field('broadcast_type_id')->equals('public')
      ->getQuery()
      ->getSingleResult();
  }
}
