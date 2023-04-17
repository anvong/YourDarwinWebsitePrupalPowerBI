<?php

/**
 * @file
 * Contains export_entity.page.inc.
 *
 * Page callback for Export entity entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Export entity templates.
 *
 * Default template: export_entity.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_export_entity(array &$variables) {
  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
