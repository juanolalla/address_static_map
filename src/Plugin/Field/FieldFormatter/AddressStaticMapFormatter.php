<?php

namespace Drupal\address_static_map\Plugin\Field\FieldFormatter;

use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'address_static_map' formatter.
 *
 * @FieldFormatter(
 *   id = "address_static_map",
 *   label = @Translation("Address Static Map"),
 *   field_types = {
 *     "address",
 *   },
 * )
 */
class AddressStaticMapFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'zoom_level' => 'auto',
        'map_size' => '',
        'scroll_lock' => '',
        'additional' => '',
        'info_window' => false,
        'text_address' => false,
        'map_style' => 'roadmap',
        'scale' => 1,
        'advanced_settings_index' => 0,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['zoom_level'] = [
      '#title' => $this->t('Zoom level'),
      '#description' => t('The zoom level to use on the map. Must be between 1 and 16 (inclusive) for Mapquest, or any of the options for Google Maps.'),
      '#type' => 'select',
      '#options' => ['auto' => $this->t('Auto')] + range(0,21),
      '#default_value' => $this->getSetting('zoom_level'),
      '#required' => TRUE,
    ];

    $form['map_size'] = [
      '#title' => $this->t('Map size'),
      '#type' => 'textfield',
      '#size' => 10,
      '#default_value' => $this->getSetting('map_size'),
      '#required' => TRUE,
    ];

    $form['scroll_lock'] = [
      '#title' => $this->t('Prevent scrolling and zooming the map'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('scroll_lock'),
    ];

    $form['additional'] = [
      '#title' => $this->t('Additional parameters to use in the map URL (i.e. styling a map)'),
      '#type' => 'textfield',
      '#size' => 2048,
      '#default_value' => $this->getSetting('additional'),
    ];

    $form['info_window'] = [
      '#title' => $this->t('Show the address in an info window'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('info_window'),
    ];

    $form['text_address'] = [
      '#title' => t('Show the address in text format'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('text_address'),
    ];

    $form['map_style'] = [
      '#type' => 'select',
      '#title' => t('Map style'),
      '#description' => t('The format to use for the rendered map. Hybrid blends, satellite and roadmap'),
      '#default_value' => $this->getSetting('map_style'),
      '#options' => [
        'roadmap' => $this->t('Roadmap'),
        'satellite' => $this->t('Satellite'),
        'terrain' => $this->t('Terrain'),
        'hybrid' => $this->t('Hybrid'),
      ],
    ];

    $form['scale'] = [
      '#type' => 'select',
      '#title' => $this->t('Scale'),
      '#description' => $this->t('The scale parameter for the image (retina). 4 will only work on Google if you have a premium subscription.'),
      '#default_value' => $this->getSetting('scale'),
      '#options' => [
        1 => t('1x'),
        2 => t('2x'),
        4 => t('4x'),
      ],
    ];

    $form['advanced_settings_index'] = [
      '#type' => 'select',
      '#title' => $this->t('Advanced settings block'),
      '#description' => $this->t('Select which block you\'d like to take the advanced settings from.'),
      '#default_value' => $this->getSetting('advanced_settings_index'),
      '#options' => [
        0 => t('Map block type 1'),
        1 => t('Map block type 2'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Zoom level: @zoom_level', ['@zoom_level' => $this->getSetting('zoom_level')]);
    $summary[] = $this->t('Map size: @map_size', ['@map_size' => $this->getSetting('map_size')]);
    if (!empty($this->getSetting('scroll_lock'))) {
      $summary[] = $this->t('Prevent map zoom and scroll');
    }
    if (!empty($this->getSetting('additional'))) {
      $summary[] = $this->t('Additional parameters: @additional', ['@additional' => $this->getSetting('additional')]);
    }
    if (!empty($this->getSetting('text_address'))) {
      $summary[] = $this->t('Show the address in text format');
    }
    if (!empty($this->getSetting('info_window'))) {
      $summary[] = $this->t('Show the address in an info window');
    }
    if (!empty($this->getSetting('scale'))) {
      $summary[] = $this->t('Scale: @scale', ['@scale' => $this->getSetting('scale')]);
    }
    if (!empty($this->getSetting('map_style'))) {
      // Show the type name and not only the key.
      $map_style = [
        'roadmap' => $this->t('Roadmap'),
        'satellite' => $this->t('Satellite'),
        'terrain' => $this->t('Terrain'),
        'hybrid' => $this->t('Hybrid'),
      ];
      $summary[] = $this->t('Map style: @map_style', ['@map_style' => $map_style[$this->getSetting('map_style')]]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $data_cleaned = array_filter($item->getValue());
      // If only the country is set, skip this (for some reason the country value
      // becomes mandatory if you limit the list).
      if (isset($data_cleaned['country_code']) && count($data_cleaned) <= 1) {
        continue;
      }
      //$address = $this->cleanAddress($item->getValue());
      $address = $item->getValue();

      $settings = $this->getSettings();
      /*$settings = array(
        'zoom' => $display['settings']['zoom_level'],
        'size' => $display['settings']['map_size'],
        'maptype' => $display['settings']['map_style'],
        'scale' => $display['settings']['scale'] ? $display['settings']['scale'] : 1,
        'index' => $display['settings']['advanced_settings_index'],
        'scroll_lock' => $display['settings']['scroll_lock'],
        'additional' => $display['settings']['additional'],
        'info_window' => $display['settings']['info_window'],
        'text_address' => $display['settings']['text_address'],
      );*/

      $api = 'google_maps';
      $settings['premier'] = TRUE;

      if ($api !== 'mapquest' && $settings['premier']) {
        $settings['client_id'] = 'gme-civicconnect';
        $settings['cripto_key'] = 'Lp-1kZ6VJnIGT2SMej4UjlM7_fg=';
      }
      else {
        //$settings['api_key'] = variable_get('addressfield_staticmap_api_key_' . $settings['index'], '');
      }

      // Use Google Maps.
      if ($api == 'google_maps') {
        $settings['icon_url'] = 'color:green';
        $element[$delta]['#markup'] = $this->renderGoogleMapsImage($address, $settings);
      }
    }

    return $element;
  }

  /**
   * Render static Google Map image for a specific address.
   *
   * @param string $address
   *   The address being displayed.
   * @param array $settings
   *   An array of settings related to the map to be displayed.
   *
   * @return string
   *   Rendered Google map.
   */
  protected function renderGoogleMapsImage(string $address, array $settings) {
    //global $is_https;
    $is_https = TRUE;

    $url_args = [
      'external' => TRUE,
      'https' => $is_https,
      'query' => [
        'center' => $address,
        'zoom' => $settings['zoom_level'],
        'size' => $settings['size'],
        'scale' => $settings['scale'],
        'maptype' => $settings['map_style'],
        'markers' => implode('|',
          [
            url($settings['icon_url'], array('external' => TRUE)),
            $address,
          ]
        ),
      ],
    ];

    if ($url_args['query']['zoom'] == 'auto') {
      unset($url_args['query']['zoom']);
    }

    // Check for Google Maps API key vs Premium Plan via Client ID & Signature.
    if (isset($settings['premier']) && $settings['premier']) {
      $url_args['query']['client'] = $settings['client_id'];
    }
    else {
      $url_args['query']['key'] = $settings['api_key'];
    }

    $settings['staticmap_url'] = Url::fromUri('//maps.googleapis.com/maps/api/staticmap', $url_args);

    if (!empty($settings['additional'])) {
      $settings['staticmap_url'] .= '&' . $settings['additional'];
    }

    if (isset($settings['premier']) && $settings['premier']) {
      $data = str_replace('//maps.googleapis.com', '', $settings['staticmap_url']);
      $signature = hash_hmac('sha1', $data, base64_decode(strtr($settings['crypto_key'], '-_', '+/')), true);
      $signature = strtr(base64_encode($signature), '+/', '-_');

      $settings['staticmap_url'] .= '&signature=' . $signature;
    }

    // Google Maps link.
    $url = '//maps.google.com/maps';
    $options = ['external' => TRUE, 'https' => $is_https];
    $index = $settings['advanced_settings_index'];
    //$target = variable_get('addressfield_staticmap_gmap_link_target_' . $index, '');
    $target = '';
    //$rel = variable_get('addressfield_staticmap_noopener_' . $index, FALSE) ? "noopener" : "";
    $rel = '';

    // Add 'Get directions' text link.
    /*if (variable_get('addressfield_staticmap_directions_link_' . $index)) {
      $link_text = variable_get('addressfield_staticmap_directions_text_' . $index, t('Get directions'));
      $options['query'] = array('daddr' => $address);
      $options['attributes'] = empty($target) ? array('title' => $link_text) : array(
        'title' => $link_text,
        'target' => $target,
        'rel' => $rel,
      );
      $settings['directions'] = l($link_text, $url, $options);
    }*/

    // Link to actual Google map.
    /*if (variable_get('addressfield_staticmap_gmap_link_' . $index, FALSE)) {
      $attributes = array();
      if (!empty($target)) {
        $attributes['target'] = $target;
      }
      if ($target == '_blank' && !empty($rel)) {
        $attributes['rel'] = $rel;
      }

      $settings['target'] = empty($attributes) ? '' : drupal_attributes($attributes);
      $options['query'] = array('q' => $address);
      $settings['link'] = url($url, $options);
    }*/

    /*$render = theme('addressfield_staticmap_static_map', array(
      'address' => $address,
      'settings' => $settings,
      'entity' => $entity,
    ));*/

    $render = [
      '#theme' => 'image',
      '#uri' => $settings['staticmap_url'],
      '#alt' => 'address'
    ];

    return $render;
  }

  /**
   * Returns a Google Maps-friendly address from the Address format.
   *
   * @param array $address
   *   An array containing parts of the address to use.
   *
   * @return array
   *   A string containing the address, formatted for Google / Mapquest.
   */
  /*protected function cleanAddress(array $address) {
    $address = $this->renderAddress($address);
    // Remove newline from address prevents %0A in URL encode.
    $address = str_replace(array("\r\n", "\r", "\n"), ' ', $address);
    // Add some commas so that the address can still be parsed by Google Map's
    // API.
    $address = preg_replace('/(<\/[^>]+?>)(<[^>\/][^>]*?>)/', '$1, $2', $address);

    return $address;
  }*/

  /**
   * Helper function to render the address.
   *
   * @param array $address
   *   An array of parts of an address.
   *
   * @return string
   *   The rendered static map for the address.
   */
  protected function renderAddress(array $address) {
    // Set up some default arguments for addressfield_generate().
    /*$handlers = array('address' => 'address');
    $context = array('mode' => 'render');

    $address = $this->generateRenderableAddress($address, $handlers, $context);
    return drupal_render($address);*/
      
    $renderable_address = $address;
    return \Drupal::service('renderer')->render($renderable_address);
  }

  /**
   * Generate a format for a given address.
   *
   * @param $address
   *   The address format being generated.
   * @param $handlers
   *   The format handlers to use to generate the format.
   * @param $context
   *   An associative array of context information pertaining to how the address
   *   format should be generated. If no mode is given, it will initialize to the
   *   default value. The remaining context keys should only be present when the
   *   address format is being generated for a field:
   *   - mode: either 'form' or 'render'; defaults to 'render'.
   *   - field: the field info array.
   *   - instance: the field instance array.
   *   - langcode: the langcode of the language the field is being rendered in.
   *   - delta: the delta value of the given address.
   *
   * @return
   *   A renderable array suitable for use as part of a form (if 'mode' is 'form')
   *   or for formatted address output when passed to drupal_render().
   */
  /*protected function generateRenderableAddress($address, array $handlers, array $context = array()) {
    // If no mode is given in the context array, default it to 'render'.
    if (empty($context['mode'])) {
      $context['mode'] = 'render';
    }

    ctools_include('plugins');
    $format = array();
    // Add the handlers, ordered by weight.
    $plugins = addressfield_format_plugins();
    $format['#handlers'] = array_intersect(array_keys($plugins), $handlers);

    foreach ($format['#handlers'] as $handler) {
      if ($callback = ctools_plugin_load_function('addressfield', 'format', $handler, 'format callback')) {
        $callback($format, $address, $context);
      }
    }

    // Store the address in the format, for processing.
    $format['#address'] = $address;

    // Post-process the format stub, depending on the rendering mode.
    if ($context['mode'] == 'form') {
      $format['#addressfield'] = TRUE;
      $format['#process'][] = 'addressfield_process_format_form';
    }
    elseif ($context['mode'] == 'render') {
      $format['#pre_render'][] = 'addressfield_render_address';
    }

    return $format;
  }*/

}
