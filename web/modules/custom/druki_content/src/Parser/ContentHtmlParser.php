<?php

namespace Drupal\druki_content\Parser;

use Drupal\druki_content\Data\Content;
use Drupal\druki_content\Data\ContentParserContext;
use Drupal\druki_content\Sync\ParsedContent\Content\ContentList;
use Drupal\druki_content\Sync\ParsedContent\Content\ParagraphCode;
use Drupal\druki_content\Sync\ParsedContent\Content\ParagraphImage;
use Drupal\druki_content\Sync\ParsedContent\Content\ParagraphNote;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parse HTML markup to structured value objects.
 */
final class ContentHtmlParser {

  /**
   * An array with element parsers.
   */
  protected array $elementParsers = [];

  /**
   * Adds element parser.
   *
   * @param \Drupal\druki_content\Parser\ContentHtmlElementParserInterface $element_parser
   *   The element parser instance.
   */
  public function addElementParser(ContentHtmlElementParserInterface $element_parser): void {
    $this->elementParsers[] = $element_parser;
  }

  /**
   * Parse HTML string into structured content.
   *
   * @param string $html
   *   The HTML to parse.
   * @param \Drupal\druki_content\Data\ContentParserContext|null $context
   *   The parser context.
   *
   * @return \Drupal\druki_content\Data\Content
   *   The structured content.
   */
  public function parse(string $html, ?ContentParserContext $context = NULL): Content {
    $content = new Content();
    if (!$context) {
      $context = new ContentParserContext();
    }
    $context->setContent($content);

    $crawler = new Crawler($html);
    // Move to body. We expect content here.
    $crawler = $crawler->filter('body');
    foreach ($crawler->children() as $element) {
      /** @var \Drupal\druki_content\Parser\ContentHtmlElementParserInterface $element_parser */
      foreach ($this->elementParsers as $element_parser) {
        if ($element_parser->parse($element, $context)) {
          // If element successfully parsed, move to another element.
          continue 2;
        }
      }
    }
    return $content;
  }

  /**
   * Parses internal links to another markdown files.
   *
   * @param \DOMNode $dom_element
   *   The DOM element to process.
   * @param string $filepath
   *   The filepath of file in which this link was found.
   */
  protected function processInternalLink(\DOMNode $dom_element, string $filepath): void {
    if (empty($dom_element->childNodes)) {
      return;
    }

    /** @var \DOMElement $child_node */
    foreach ($dom_element->childNodes as $child_node) {

      if ($child_node->nodeName == 'a') {
        $href = $child_node->getAttribute('href');

        // Must end up with Markdown extension: .md, .MD.
        if (!\preg_match("/.*\.md$/mi", $href)) {
          continue;
        }

        $child_node->setAttribute('data-druki-internal-link-filepath', $filepath);
      }

      $this->processInternalLink($child_node, $filepath);
    }
  }

  /**
   * Parses note.
   *
   * @param \DOMElement $dom_element
   *   The DOM element to process.
   * @param \Drupal\druki_content\Sync\ParsedContent\Content\ContentList $content
   *   The value object of content list.
   *
   * @return bool
   *   TRUE if parsed successfully, NULL otherwise.
   */
  protected function parseNote(\DOMElement $dom_element, ContentList $content): ?bool {
    $crawler = new Crawler($dom_element->ownerDocument->saveHTML($dom_element));
    $note_element = $crawler->filter('div[data-druki-note]');

    if (\count($note_element)) {
      $element = $note_element->getNode(0);

      $value = '';

      foreach ($element->childNodes as $child) {
        $value .= $element->ownerDocument->saveHTML($child);
      }

      $note = new ParagraphNote($element->getAttribute('data-druki-note'), $value);
      $content->add($note);

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Parses code.
   *
   * @param \DOMElement $dom_element
   *   The DOM element to process.
   * @param \Drupal\druki_content\Sync\ParsedContent\Content\ContentList $content
   *   The value object of content list.
   *
   * @return bool
   *   TRUE if parsed successfully, NULL otherwise.
   */
  protected function parseCode(\DOMElement $dom_element, ContentList $content): bool {
    $node_name = $dom_element->nodeName;
    $code_elements = ['pre'];

    if (\in_array($node_name, $code_elements)) {
      $code = new ParagraphCode($dom_element->ownerDocument->saveHTML($dom_element));
      $content->add($code);

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Parses image.
   *
   * @param \DOMElement $dom_element
   *   The DOM element to process.
   * @param \Drupal\druki_content\Sync\ParsedContent\Content\ContentList $content
   *   The value object of content list.
   *
   * @return bool
   *   TRUE if parsed successfully, NULL otherwise.
   */
  protected function parseImage(\DOMElement $dom_element, ContentList $content): bool {
    $crawler = new Crawler($dom_element);
    $image = $crawler->filter('img')->first();

    if (\count($image)) {
      $image_element = new ParagraphImage($image->attr('src'), $image->attr('alt'));
      $content->add($image_element);

      return TRUE;
    }

    return FALSE;
  }

}
