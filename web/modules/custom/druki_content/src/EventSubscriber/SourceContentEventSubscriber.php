<?php

declare(strict_types=1);

namespace Drupal\druki_content\EventSubscriber;

use Drupal\druki\Process\GitInterface;
use Drupal\druki_content\Builder\ContentSyncQueueBuilderInterface;
use Drupal\druki_content\Event\RequestSourceContentSyncEvent;
use Drupal\druki_content\Event\RequestSourceContentUpdateEvent;
use Drupal\druki_content\Repository\ContentSourceSettingsInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides event subscriber for source content events.
 */
final class SourceContentEventSubscriber implements EventSubscriberInterface {

  /**
   * The git process.
   */
  protected GitInterface $git;

  /**
   * The content source settings repository.
   */
  protected ContentSourceSettingsInterface $contentSourceSettings;

  /**
   * The content sync queue builder.
   */
  protected ContentSyncQueueBuilderInterface $queueBuilder;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs a new SourceContentEventSubscriber object.
   *
   * @param \Drupal\druki_content\Repository\ContentSourceSettingsInterface $content_source_settings
   *   The content source settings repository.
   * @param \Drupal\druki_content\Builder\ContentSyncQueueBuilderInterface $queue_builder
   *   The queue builder.
   * @param \Drupal\druki\Process\GitInterface $git
   *   The git process.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(ContentSourceSettingsInterface $content_source_settings, ContentSyncQueueBuilderInterface $queue_builder, GitInterface $git, EventDispatcherInterface $event_dispatcher) {
    $this->contentSourceSettings = $content_source_settings;
    $this->queueBuilder = $queue_builder;
    $this->git = $git;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RequestSourceContentUpdateEvent::class => ['onUpdateRequest'],
      RequestSourceContentSyncEvent::class => ['onSyncRequest'],
    ];
  }

  /**
   * Reacts on request for the source content update.
   *
   * @param \Drupal\druki_content\Event\RequestSourceContentUpdateEvent $event
   *   The event instance.
   */
  public function onUpdateRequest(RequestSourceContentUpdateEvent $event): void {
    $process = $this->git->pull($this->contentSourceSettings->getRepositoryUri());
    $process->run();
    if (!$process->isSuccessful()) {
      return;
    }
    // If pull successful, immediately request synchronization.
    $sync_event = new RequestSourceContentSyncEvent();
    $this->eventDispatcher->dispatch($sync_event);
  }

  /**
   * Reacts on request for the source content synchronization.
   *
   * @param \Drupal\druki_content\Event\RequestSourceContentSyncEvent $event
   *   The event instance.
   */
  public function onSyncRequest(RequestSourceContentSyncEvent $event): void {
    $repository_uri = $this->contentSourceSettings->getRepositoryUri();
    $this->queueBuilder->buildFromPath($repository_uri);
  }

}
