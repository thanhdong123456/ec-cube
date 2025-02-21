<?php

namespace Customize\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Product")
 */
trait ProductTrait
{
  /**
   * @var \Doctrine\Common\Collections\Collection
   *
   * @ORM\OneToMany(targetEntity="Eccube\Entity\OrderItem", mappedBy="Product", cascade={"persist"})
   */
  public $OrderItems;

  /**
   * @var boolean
   *
   * @ORM\Column(name="free_shipping", type="boolean", options={"default": false})
   */
  public $free_shipping;

  /**
   * Get the value of free_shipping
   *
   * @return bool
   */
  public function getFreeShipping(): bool
  {
    return $this->free_shipping;
  }

  /**
   * Set the value of free_shipping
   *
   * @param bool $free_shipping
   * @return self
   */
  public function setFreeShipping(bool $free_shipping): self
  {
    $this->free_shipping = $free_shipping;
    return $this;
  }
}