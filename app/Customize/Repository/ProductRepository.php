<?php

namespace Customize\Repository;

use Eccube\Entity\Product;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Repository\ProductRepository as BaseProductRepository;
use Eccube\Request\Context;
use Doctrine\Persistence\ManagerRegistry as RegistryInterface;
use Eccube\Common\EccubeConfig;
use Eccube\Doctrine\Query\Queries;
use Eccube\Entity\Category;
use Eccube\Entity\Master\ProductListMax;
use Eccube\Entity\Master\ProductListOrderBy;
use Eccube\Util\StringUtil;
use Eccube\Repository\QueryKey;

class ProductRepository extends BaseProductRepository
{
  /**
   * @var Queries
   */
  protected $queries;

  /**
   * @var EccubeConfig
   */
  protected $eccubeConfig;

  /**
   * @var Context
   */
  protected $requestContext;

  /**
   * ProductRepository constructor.
   *
   * @param Context $requestContext
   * @param RegistryInterface $registry
   * @param Queries $queries
   * @param EccubeConfig $eccubeConfig
   */
  public function __construct(
    RegistryInterface $registry,
    Queries $queries,
    EccubeConfig $eccubeConfig,
    Context $requestContext
  ) {
    $this->queries = $queries;
    $this->eccubeConfig = $eccubeConfig;
    $this->requestContext = $requestContext;
    parent::__construct($registry, $queries, $eccubeConfig);
  }

  /**
   * get query builder.
   *
   * @param array{
   *         category_id?:Category,
   *         name?:string,
   *         pageno?:string,
   *         disp_number?:ProductListMax,
   *         orderby?:ProductListOrderBy
   *     } $searchData
   *
   * @return \Doctrine\ORM\QueryBuilder
   */
  public function getQueryBuilderBySearchData($searchData)
  {
    $excludes = [];
    $excludes[] = OrderStatus::CANCEL;
    $user = $this->requestContext->getCurrentUser();
    $qb = $this->createQueryBuilder('p')
      ->leftJoin('p.OrderItems', 'oi')
      ->leftJoin('oi.Order', 'o')
      ->where('p.Status = 1')
      ->orWhere('p.Status = 2 AND o.Customer = :CustomerId AND o.OrderStatus NOT IN (:excludes)')
      ->setParameter('excludes', $excludes)
      ->setParameter('CustomerId', $user->getId());

    // category
    $categoryJoin = false;
    if (!empty($searchData['category_id']) && $searchData['category_id']) {
      $Categories = $searchData['category_id']->getSelfAndDescendants();
      if ($Categories) {
        $qb
          ->innerJoin('p.ProductCategories', 'pct')
          ->innerJoin('pct.Category', 'c')
          ->andWhere($qb->expr()->in('pct.Category', ':Categories'))
          ->setParameter('Categories', $Categories);
        $categoryJoin = true;
      }
    }

    // name
    if (isset($searchData['name']) && StringUtil::isNotBlank($searchData['name'])) {
      $keywords = preg_split('/[\s　]+/u', str_replace(['%', '_'], ['\\%', '\\_'], $searchData['name']), -1, PREG_SPLIT_NO_EMPTY);

      foreach ($keywords as $index => $keyword) {
        $key = sprintf('keyword%s', $index);
        $qb
          ->andWhere(sprintf('NORMALIZE(p.name) LIKE NORMALIZE(:%s) OR
                        NORMALIZE(p.search_word) LIKE NORMALIZE(:%s) OR
                        EXISTS (SELECT wpc%d FROM \Eccube\Entity\ProductClass wpc%d WHERE p = wpc%d.Product AND NORMALIZE(wpc%d.code) LIKE NORMALIZE(:%s))',
            $key,
            $key,
            $index,
            $index,
            $index,
            $index,
            $key
          ))
          ->setParameter($key, '%' . $keyword . '%');
      }
    }

    // Order By
    // 価格低い順
    $config = $this->eccubeConfig;
    if (!empty($searchData['orderby']) && $searchData['orderby']->getId() == $config['eccube_product_order_price_lower']) {
      // @see http://doctrine-orm.readthedocs.org/en/latest/reference/dql-doctrine-query-language.html
      $qb->addSelect('MIN(pc.price02) as HIDDEN price02_min');
      $qb->innerJoin('p.ProductClasses', 'pc');
      $qb->andWhere('pc.visible = true');
      $qb->groupBy('p.id');
      $qb->orderBy('price02_min', 'ASC');
      $qb->addOrderBy('p.id', 'DESC');
      // 価格高い順
    } elseif (!empty($searchData['orderby']) && $searchData['orderby']->getId() == $config['eccube_product_order_price_higher']) {
      $qb->addSelect('MAX(pc.price02) as HIDDEN price02_max');
      $qb->innerJoin('p.ProductClasses', 'pc');
      $qb->andWhere('pc.visible = true');
      $qb->groupBy('p.id');
      $qb->orderBy('price02_max', 'DESC');
      $qb->addOrderBy('p.id', 'DESC');
      // 新着順
    } elseif (!empty($searchData['orderby']) && $searchData['orderby']->getId() == $config['eccube_product_order_newer']) {
      // 在庫切れ商品非表示の設定が有効時対応
      // @see https://github.com/EC-CUBE/ec-cube/issues/1998
      if ($this->getEntityManager()->getFilters()->isEnabled('option_nostock_hidden') == true) {
        $qb->innerJoin('p.ProductClasses', 'pc');
        $qb->andWhere('pc.visible = true');
      }
      $qb->orderBy('p.create_date', 'DESC');
      $qb->addOrderBy('p.id', 'DESC');
    } else {
      if ($categoryJoin === false) {
        $qb
          ->leftJoin('p.ProductCategories', 'pct')
          ->leftJoin('pct.Category', 'c');
      }
      $qb
        ->addOrderBy('p.id', 'DESC');
    }

    return $this->queries->customize(QueryKey::PRODUCT_SEARCH, $qb, $searchData);
  }

  /**
   * Get the result for the product status not in the excludes list.
   *
   * @return \Eccube\Entity\Product|null
   */
  public function getProductStatusNotIn(Product $product)
  {
    $excludes = [OrderStatus::CANCEL];
    $user = $this->requestContext->getCurrentUser();

    $qb = $this->createQueryBuilder('p')
      ->leftJoin('p.OrderItems', 'oi')
      ->leftJoin('oi.Order', 'o')
      ->where('p.id = :ProductId')
      ->andWhere('o.Customer = :CustomerId AND o.OrderStatus NOT IN (:excludes)')
      ->setParameter('excludes', $excludes)
      ->setParameter('CustomerId', $user->getId())
      ->setParameter('ProductId', $product->getId());

    return $qb->getQuery()->getOneOrNullResult();
  }
}