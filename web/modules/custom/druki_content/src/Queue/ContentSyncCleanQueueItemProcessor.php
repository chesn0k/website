<?php

namespace Drupal\druki_content\Queue;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\druki\Queue\EntitySyncQueueItemInterface;
use Drupal\druki\Queue\EntitySyncQueueItemProcessorInterface;
use Drupal\druki\Queue\EntitySyncQueueManagerInterface;
use Drupal\druki_content\Data\ContentSyncCleanQueueItem;

/**
 * Provides synchronization clean queue processor.
 *
 * Purpose of this processor remove all content that exists on site but was not
 * found during synchronization — content deleted from source.
 *
 * @see \Drupal\druki\Repository\EntitySyncQueueStateInterface
 */
final class ContentSyncCleanQueueItemProcessor implements EntitySyncQueueItemProcessorInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The queue manager.
   */
  protected EntitySyncQueueManagerInterface $queueManager;

  /**
   * ContentSyncCleanQueueItemProcessor constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\druki\Queue\EntitySyncQueueManagerInterface $queue_manager
   *   The queue manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntitySyncQueueManagerInterface $queue_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->queueManager = $queue_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function process(EntitySyncQueueItemInterface $item): array {
    /** @var \Drupal\druki_content\Repository\ContentStorage $druki_content_storage */
    $druki_content_storage = $this->entityTypeManager->getStorage('druki_content');
    $existing_ids = $druki_content_storage->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    $synced_ids = $this->queueManager->getState()->getEntityIds();
    $removed_content_ids = \array_diff($existing_ids, $synced_ids);
    if (!$removed_content_ids) {
      return [];
    }
    $content_entities = $druki_content_storage->loadMultiple($removed_content_ids);
    $druki_content_storage->delete($content_entities);
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(EntitySyncQueueItemInterface $item): bool {
    return $item instanceof ContentSyncCleanQueueItem;
  }

}
