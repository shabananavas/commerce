<?php

namespace Drupal\commerce_order\Controller;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\search_api\Entity\Index;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class OrderItemAutoComplete.
 *
 * @package Drupal\commerce_order\Controller
 */
class OrderItemAutoComplete extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new OrderItemAutoComplete object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer interface.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RendererInterface $renderer,
    MessengerInterface $messenger
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('messenger')
    );
  }

  /**
   * Order item auto complete handler.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $entity_type
   *   The entity type.
   * @param string $view_mode
   *   The view mode.
   * @param int $count
   *   The number of entities to list.
   *
   * @return object
   *   The Json object for auto complete suggestions.
   */
  public function orderItemAutoCompleteHandler(Request $request, $entity_type, $view_mode, $count) {

    $results = [];
    if ($input = $request->query->get('q')) {
      $suggestions = $this->searchQueryString($input, $count);
      $view_builder = $this->entityTypeManager->getViewBuilder($entity_type);
      foreach ($suggestions->getResultItems() as $item) {
        $data = explode(':', $item->getId());
        $data = explode('/', $data[1]);
        $product_variation = ProductVariation::load($data[1]);
        $product_render_array = $view_builder->view($product_variation, $view_mode, $product_variation->language()->getId());
        $results[] = [
          'value' => $product_variation->id(),
          'label' => '<div class="commerce-order-autocomplete-results">' . $this->renderer->renderPlain($product_render_array) . '</div>',
        ];
      }
    }

    return new JsonResponse($results);
  }

  /**
   * Helper function for searching the product.
   *
   * @param string $string
   *   The Query string.
   * @param int $count
   *   The count of items to return.
   *
   * @return mixed
   *   The query search result.
   */
  public function searchQueryString($string, $count) {
    $config = $this->config('commerce_order.settings');

    $index = Index::load($config->get('product_search_index'));

    if (!isset($index)) {
      $this
        ->messenger
        ->addMessage(
          $this->t('You must configure a Search API index to search against.')
      );
      return NULL;
    }

    $query = $index->query();

    $parse_mode = \Drupal::service('plugin.manager.search_api.parse_mode')
      ->createInstance('direct');
    $parse_mode->setConjunction('OR');
    $query->setParseMode($parse_mode);

    // Set fulltext search keywords and fields.
    $query->keys($string);

    $query->range(0, $count);

    $results = $query->execute();

    return $results;
  }

}
