<?php

namespace Customize\Service\PurchaseFlow\Processor;

use Eccube\Entity\Master\OrderItemType;
use Eccube\Entity\Master\TaxDisplayType;
use Eccube\Entity\Master\TaxType;
use Eccube\Service\PurchaseFlow\Processor\DeliveryFeePreprocessor as BaseDeliveryFeePreprocessor;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Entity\Order;
use Eccube\Entity\ItemHolderInterface;
use Eccube\Entity\OrderItem;
use Eccube\Entity\DeliveryFee;

class DeliveryFeePreprocessor extends BaseDeliveryFeePreprocessor
{
  /**
   * @param ItemHolderInterface $itemHolder
   * @param PurchaseContext $context
   *
   * @throws \Doctrine\ORM\NoResultException
   */
  public function process(ItemHolderInterface $itemHolder, PurchaseContext $context)
  {
    $this->removeDeliveryFeeItem($itemHolder);
    $this->saveDeliveryFeeItem($itemHolder);
  }

  private function removeDeliveryFeeItem(ItemHolderInterface $itemHolder)
  {
    foreach ($itemHolder->getShippings() as $Shipping) {
      foreach ($Shipping->getOrderItems() as $item) {
        if ($item->getProcessorName() == DeliveryFeePreprocessor::class) {
          $Shipping->removeOrderItem($item);
          $itemHolder->removeOrderItem($item);
          $this->entityManager->remove($item);
        }
      }
    }
  }

  /**
   * @param ItemHolderInterface $itemHolder
   *
   * @throws \Doctrine\ORM\NoResultException
   */
  private function saveDeliveryFeeItem(ItemHolderInterface $itemHolder)
  {
    $DeliveryFeeType = $this->entityManager
      ->find(OrderItemType::class, OrderItemType::DELIVERY_FEE);
    $TaxInclude = $this->entityManager
      ->find(TaxDisplayType::class, TaxDisplayType::INCLUDED);
    $Taxation = $this->entityManager
      ->find(TaxType::class, TaxType::TAXATION);

    /** @var Order $Order */
    $Order = $itemHolder;

    // Customize checking free_shipping
    $orderItems = $Order->getOrderItems();
    $allFreeShipping = true;

    foreach ($orderItems as $orderItem) {
      $product = $orderItem->getProduct();
      if ($product && !$product->getFreeShipping()) {
        $allFreeShipping = false;
        break;
      }
    }
    
    /* @var Shipping $Shipping */
    foreach ($Order->getShippings() as $Shipping) {
      // 送料の計算
      $deliveryFeeProduct = 0;
      if ($this->BaseInfo->isOptionProductDeliveryFee()) {
        /** @var OrderItem $item */
        foreach ($Shipping->getOrderItems() as $item) {
          if (!$item->isProduct()) {
            continue;
          }
          $deliveryFeeProduct += $item->getProductClass()->getDeliveryFee() * $item->getQuantity();
        }
      }

      /** @var DeliveryFee|null $DeliveryFee */
      $DeliveryFee = $this->deliveryFeeRepository->findOneBy([
        'Delivery' => $Shipping->getDelivery(),
        'Pref' => $Shipping->getPref(),
      ]);
      $fee = is_object($DeliveryFee) ? $DeliveryFee->getFee() : 0;

      $OrderItem = new OrderItem();
      $OrderItem->setProductName($DeliveryFeeType->getName())
        ->setPrice($allFreeShipping ? 0 : $fee + $deliveryFeeProduct)
        ->setQuantity(1)
        ->setOrderItemType($DeliveryFeeType)
        ->setShipping($Shipping)
        ->setOrder($itemHolder)
        ->setTaxDisplayType($TaxInclude)
        ->setTaxType($Taxation)
        ->setProcessorName(DeliveryFeePreprocessor::class);

      $itemHolder->addItem($OrderItem);
      $Shipping->addOrderItem($OrderItem);
    }
  }
}
