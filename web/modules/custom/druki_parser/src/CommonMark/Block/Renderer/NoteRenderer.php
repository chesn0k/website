<?php

namespace Drupal\druki_parser\CommonMark\Block\Renderer;

use League\CommonMark\Block\Element\AbstractBlock;
use League\CommonMark\Block\Renderer\BlockRendererInterface;
use League\CommonMark\ElementRendererInterface;
use League\CommonMark\HtmlElement;

/**
 * Class NoteRenderer
 *
 * @package Drupal\druki_parser\CommonMark\Renderer
 */
class NoteRenderer implements BlockRendererInterface {

  /**
   * {@inheritdoc}
   */
  public function render(AbstractBlock $block, ElementRendererInterface $htmlRenderer, $inTightList = FALSE): HtmlElement {
    $childContent = $htmlRenderer->renderBlocks($block->children());
    $note_type = $block->getType();

    return new HtmlElement('div', ['data-druki-note' => $note_type], $childContent);
  }

}