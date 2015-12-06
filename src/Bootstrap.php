<?php
/**
 * @file
 * Contains \Drupal\bootstrap\Bootstrap.
 */

namespace Drupal\bootstrap;

use Drupal\bootstrap\Plugin\AlterManager;
use Drupal\bootstrap\Plugin\FormManager;
use Drupal\bootstrap\Plugin\PreprocessManager;
use Drupal\bootstrap\Utility\Unicode;
use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Template\Attribute;

/**
 * The primary class for the Drupal Bootstrap base theme.
 *
 * Provides many helper methods.
 */
class Bootstrap {

  /**
   * Tag used to invalidate caches.
   *
   * @var string
   */
  const CACHE_TAG = 'theme_registry';

  /**
   * The current supported Bootstrap Framework version.
   */
  const FRAMEWORK_VERSION = '3.3.5';

  /**
   * The Bootstrap Framework documentation site.
   */
  const FRAMEWORK_HOMEPAGE = 'http://getbootstrap.com';

  /**
   * The Bootstrap Framework repository.
   */
  const FRAMEWORK_REPOSITORY = 'https://github.com/twbs/bootstrap';

  /**
   * The project branch.
   */
  const PROJECT_BRANCH = '8.x-3.x';

  /**
   * The Drupal Bootstrap documentation site.
   */
  const PROJECT_DOCUMENTATION = 'http://drupal-bootstrap.org';

  /**
   * The Drupal Bootstrap project page.
   */
  const PROJECT_PAGE = 'https://www.drupal.org/project/bootstrap';

  /**
   * Manages theme alter hooks as classes and allows sub-themes to sub-class.
   *
   * @param string $function
   *   The procedural function name of the alter (e.g. __FUNCTION__).
   * @param mixed $data
   *   The variable that was passed to the hook_TYPE_alter() implementation to
   *   be altered. The type of this variable depends on the value of the $type
   *   argument. For example, when altering a 'form', $data will be a structured
   *   array. When altering a 'profile', $data will be an object.
   * @param mixed $context1
   *   (optional) An additional variable that is passed by reference.
   * @param mixed $context2
   *   (optional) An additional variable that is passed by reference. If more
   *   context needs to be provided to implementations, then this should be an
   *   associative array as described above.
   */
  public static function alter($function, &$data, &$context1 = NULL, &$context2 = NULL) {
    static $theme;
    if (!isset($theme)) {
      $theme = static::getTheme();
    }

    // Immediately return if the active theme is not Bootstrap based.
    if (!$theme->subthemeOf('bootstrap')) {
      return;
    }

    // Extract the alter hook name.
    $hook = Unicode::extractHook($function, 'alter');

    // Handle form alters separately.
    if (strpos($hook, 'form') === 0) {
      $form_id = $context2;
      if (!$form_id) {
        $form_id = Unicode::extractHook($function, 'alter', 'form');
      }

      // Due to a core bug that affects admin themes, we should not double
      // process the "system_theme_settings" form twice in the global
      // hook_form_alter() invocation.
      // @see https://drupal.org/node/943212
      if ($context2 === 'system_theme_settings') {
        return;
      }

      // Retrieve a list of form definitions.
      $form_manager = new FormManager($theme);

      /** @var \Drupal\bootstrap\Plugin\Form\FormInterface $form */
      if ($form_manager->hasDefinition($form_id) && ($form = $form_manager->createInstance($form_id))) {
        $data['#submit'][] = [$form, 'submitForm'];
        $data['#validate'][] = [$form, 'validateForm'];
        $form->alterForm($data, $context1, $context2);
      }
    }
    // Process hook alter normally.
    else {
      // Retrieve a list of alter definitions.
      $alter_manager = new AlterManager($theme);

      /** @var \Drupal\bootstrap\Plugin\Alter\AlterInterface $class */
      if ($alter_manager->hasDefinition($hook) && ($class = $alter_manager->createInstance($hook))) {
        $class->alter($data, $context1, $context2);
      }
    }
  }

  /**
   * Returns a documentation search URL for a given query.
   *
   * @param string $query
   *   The query to search for.
   *
   * @return string
   *   The complete URL to the documentation site.
   */
  public static function apiSearchUrl($query = '') {
    return static::PROJECT_DOCUMENTATION . '/api/bootstrap/' . static::PROJECT_BRANCH . '/search/' . Html::escape($query);
  }


  /**
   * Matches a Bootstrap class based on a string value.
   *
   * @param string $string
   *   The string to match classes against.
   * @param string $default
   *   The default class to return if no match is found.
   *
   * @return string
   *   The Bootstrap class matched against the value of $haystack or $default
   *   if no match could be made.
   */
  public static function cssClassFromString($string, $default = '') {
    $theme = Bootstrap::getTheme();
    $texts = $theme->getCache('cssClassFromString');

    if ($texts->isEmpty()) {
      $data = [
        // Text that match these specific strings are checked first.
        'matches' => [
          // Primary class.
          t('Download feature')->render()   => 'primary',

          // Success class.
          t('Add effect')->render()         => 'success',
          t('Add and configure')->render()  => 'success',

          // Info class.
          t('Save and add')->render()       => 'info',
          t('Add another item')->render()   => 'info',
          t('Update style')->render()       => 'info',
        ],

        // Text containing these words anywhere in the string are checked last.
        'contains' => [
          // Primary class.
          t('Confirm')->render()            => 'primary',
          t('Filter')->render()             => 'primary',
          t('Submit')->render()             => 'primary',
          t('Search')->render()             => 'primary',

          // Success class.
          t('Add')->render()                => 'success',
          t('Create')->render()             => 'success',
          t('Save')->render()               => 'success',
          t('Write')->render()              => 'success',

          // Warning class.
          t('Export')->render()             => 'warning',
          t('Import')->render()             => 'warning',
          t('Restore')->render()            => 'warning',
          t('Rebuild')->render()            => 'warning',

          // Info class.
          t('Apply')->render()              => 'info',
          t('Update')->render()             => 'info',

          // Danger class.
          t('Delete')->render()             => 'danger',
          t('Remove')->render()             => 'danger',
        ],
      ];

      // Allow sub-themes to alter this array of patterns.
      /** @var \Drupal\Core\Theme\ThemeManager $theme_manager */
      $theme_manager = \Drupal::service('theme.manager');
      $theme_manager->alter('bootstrap_colorize_text', $data);

      $texts->setMultiple($data);
    }

    // Iterate over the array.
    foreach ($texts as $pattern => $strings) {
      foreach ($strings as $value => $class) {
        switch ($pattern) {
          case 'matches':
            if ((string) $string === $value) {
              return $class;
            }
            break;

          case 'contains':
            if (strpos(Unicode::strtolower((string) $string), Unicode::strtolower($value)) !== FALSE) {
              return $class;
            }
            break;
        }
      }
    }

    // Return the default if nothing was matched.
    return $default;
  }

  /**
   * Logs and displays a warning about a deprecated function/method being used.
   */
  public static function deprecated() {
    // Log backtrace.
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    \Drupal::logger('bootstrap')->warning('<pre><code>' . print_r($backtrace, TRUE) . '</code></pre>');

    if (!static::getTheme()->getSetting('suppress_deprecated_warnings')) {
      return;
    }

    // Extrapolate the caller.
    $caller = $backtrace[1];
    $class = '';
    if (isset($caller['class'])) {
      $parts = explode('\\', $caller['class']);
      $class = array_pop($parts) . '::';
    }
    drupal_set_message(t('The following function(s) or method(s) have been deprecated, please check the logs for a more detailed backtrace on where these are being invoked. Click on the function or method link to search the documentation site for a possible replacement or solution.'), 'warning');
    drupal_set_message(t('<a href=":url" target="_blank">@title</a>.', [
      ':url' => static::apiSearchUrl($class . $caller['function']),
      '@title' => ($class ? $caller['class'] . $caller['type'] : '') . $caller['function'] . '()',
    ]), 'warning');
  }

  /**
   * Provides additional variables to be used in elements and templates.
   *
   * @return array
   *   An associative array containing key/default value pairs.
   */
  public static function extraVariables() {
    return [
      // @see https://drupal.org/node/2035055
      'context' => [],

      // @see https://drupal.org/node/2219965
      'icon' => NULL,
      'icon_position' => 'before',
    ];
  }

  /**
   * Returns the theme hook definition information.
   *
   * This base-theme's custom theme hook implementations. Never define "path"
   * or "template" as these are detected and automatically added.
   *
   * @see bootstrap_theme_registry_alter()
   * @see \Drupal\bootstrap\Registry
   * @see hook_theme()
   */
  public static function getInfo() {
    $hooks['bootstrap_carousel'] = [
      'variables' => [
        'attributes' => [],
        'items' => [],
        'start_index' => 0,
        'controls' => TRUE,
        'indicators' => TRUE,
        'interval' => 5000,
        'pause' => 'hover',
        'wrap' => TRUE,
      ],
    ];

    $hooks['bootstrap_dropdown'] = [
      'render element' => 'element',
    ];

    $hooks['bootstrap_modal'] = [
      'variables' => [
        'heading' => '',
        'body' => '',
        'footer' => '',
        'dialog_attributes' => [],
        'attributes' => [],
        'size' => '',
        'html_heading' => FALSE,
      ],
    ];

    $hooks['bootstrap_panel'] = [
      'render element' => 'element',
    ];
    return $hooks;
  }

  /**
   * Retrieves a theme instance of \Drupal\bootstrap.
   *
   * @param string|\Drupal\Core\Extension\Extension $theme
   *   The machine name or \Drupal\Core\Extension\Extension object. If
   *   omitted, the active theme will be used.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler object.
   *
   * @return \Drupal\bootstrap\Theme
   *   A theme object.
   */
  public static function getTheme($theme = NULL, ThemeHandlerInterface $theme_handler = NULL) {
    // Immediately return if theme passed is already instantiated.
    if ($theme instanceof Theme) {
      return $theme;
    }

    static $themes = [];

    if (!isset($theme_handler)) {
      $theme_handler = static::getThemeHandler();
    }
    if (!isset($theme)) {
      $theme = \Drupal::theme()->getActiveTheme()->getName();
    }
    if (is_string($theme)) {
      $theme = $theme_handler->getTheme($theme);
    }

    if (!($theme instanceof Extension)) {
      throw new \InvalidArgumentException(sprintf('The $theme argument provided is not of the class \Drupal\Core\Extension\Extension: %s.', $theme));
    }

    if (!isset($themes[$theme->getName()])) {
      $themes[$theme->getName()] = new Theme($theme, $theme_handler);
    }

    return $themes[$theme->getName()];
  }

  /**
   * Retrieves the theme handler instance.
   *
   * @return \Drupal\Core\Extension\ThemeHandlerInterface
   *   The theme handler instance.
   */
  public static function getThemeHandler() {
    static $theme_handler;
    if (!isset($theme_handler)) {
      $theme_handler = \Drupal::service('theme_handler');
    }
    return $theme_handler;
  }

  /**
   * Returns a specific Bootstrap Glyphicon.
   *
   * @param string $name
   *   The icon name, minus the "glyphicon-" prefix.
   * @param array $default
   *   (Optional) The default render array to use if $name is not available.
   *
   * @return array
   *   The render containing the icon defined by $name, $default value if
   *   icon does not exist or returns NULL if no icon could be rendered.
   */
  public static function glyphicon($name, $default = []) {
    // Ensure the icon specified is a valid Bootstrap Glyphicon.
    // @todo Supply a specific version to _bootstrap_glyphicons() when Icon API
    // supports versioning.
    if (static::getTheme()->hasGlyphicons() && in_array($name, static::glyphicons())) {
      // Attempt to use the Icon API module, if enabled and it generates output.
      if (\Drupal::moduleHandler()->moduleExists('icon')) {
        return [
          '#type' => 'icon',
          '#bundle' => 'bootstrap',
          '#icon' => 'glyphicon-' . $name,
        ];
      }
      return [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => '',
        '#attributes' => new Attribute([
          'class' => ['icon', 'glyphicon', 'glyphicon-' . $name],
          'aria-hidden' => 'true',
        ]),
      ];
    }
    return $default;
  }

  /**
   * Matches a Bootstrap Glyphicon based on a string value.
   *
   * @param string $string
   *   The string to match classes against.
   * @param array $default
   *   The default render array to return if no match is found.
   *
   * @return string
   *   The Bootstrap icon matched against the value of $haystack or $default if
   *   no match could be made.
   */
  public static function glyphiconFromString($string, $default = []) {
    $theme = Bootstrap::getTheme();
    $texts = $theme->getCache('glyphiconFromString', []);

    if ($texts->isEmpty()) {
      $data = [
        // Text that match these specific strings are checked first.
        'matches' => [],

        // Text containing these words anywhere in the string are checked last.
        'contains' => [
          t('Manage')->render()     => 'cog',
          t('Configure')->render()  => 'cog',
          t('Download')->render()   => 'download',
          t('Export')->render()     => 'export',
          t('Filter')->render()     => 'filter',
          t('Import')->render()     => 'import',
          t('Save')->render()       => 'ok',
          t('Update')->render()     => 'ok',
          t('Edit')->render()       => 'pencil',
          t('Add')->render()        => 'plus',
          t('Write')->render()      => 'plus',
          t('Cancel')->render()     => 'remove',
          t('Delete')->render()     => 'trash',
          t('Remove')->render()     => 'trash',
          t('Upload')->render()     => 'upload',
        ],
      ];

      // Allow sub-themes to alter this array of patterns.
      /** @var \Drupal\Core\Theme\ThemeManager $theme_manager */
      $theme_manager = \Drupal::service('theme.manager');
      $theme_manager->alter('bootstrap_iconize_text', $data);

      $texts->setMultiple($data);
    }

    // Iterate over the array.
    foreach ($texts as $pattern => $strings) {
      foreach ($strings as $value => $icon) {
        switch ($pattern) {
          case 'matches':
            if ((string) $string === $value) {
              return static::glyphicon($icon, $default);
            }
            break;

          case 'contains':
            if (strpos(Unicode::strtolower((string) $string), Unicode::strtolower($value)) !== FALSE) {
              return static::glyphicon($icon, $default);
            }
            break;
        }
      }
    }

    // Return a default icon if nothing was matched.
    return $default;
  }

  /**
   * Returns a list of available Bootstrap Framework Glyphicons.
   *
   * @param string $version
   *   The specific version of glyphicons to return. If not set, the latest
   *   BOOTSTRAP_VERSION will be used.
   *
   * @return array
   *   An associative array of icons keyed by their classes.
   */
  public static function glyphicons($version = NULL) {
    static $versions;
    if (!isset($versions)) {
      $versions = [];
      $versions['3.0.0'] = [
        // Class => Name.
        'glyphicon-adjust' => 'adjust',
        'glyphicon-align-center' => 'align-center',
        'glyphicon-align-justify' => 'align-justify',
        'glyphicon-align-left' => 'align-left',
        'glyphicon-align-right' => 'align-right',
        'glyphicon-arrow-down' => 'arrow-down',
        'glyphicon-arrow-left' => 'arrow-left',
        'glyphicon-arrow-right' => 'arrow-right',
        'glyphicon-arrow-up' => 'arrow-up',
        'glyphicon-asterisk' => 'asterisk',
        'glyphicon-backward' => 'backward',
        'glyphicon-ban-circle' => 'ban-circle',
        'glyphicon-barcode' => 'barcode',
        'glyphicon-bell' => 'bell',
        'glyphicon-bold' => 'bold',
        'glyphicon-book' => 'book',
        'glyphicon-bookmark' => 'bookmark',
        'glyphicon-briefcase' => 'briefcase',
        'glyphicon-bullhorn' => 'bullhorn',
        'glyphicon-calendar' => 'calendar',
        'glyphicon-camera' => 'camera',
        'glyphicon-certificate' => 'certificate',
        'glyphicon-check' => 'check',
        'glyphicon-chevron-down' => 'chevron-down',
        'glyphicon-chevron-left' => 'chevron-left',
        'glyphicon-chevron-right' => 'chevron-right',
        'glyphicon-chevron-up' => 'chevron-up',
        'glyphicon-circle-arrow-down' => 'circle-arrow-down',
        'glyphicon-circle-arrow-left' => 'circle-arrow-left',
        'glyphicon-circle-arrow-right' => 'circle-arrow-right',
        'glyphicon-circle-arrow-up' => 'circle-arrow-up',
        'glyphicon-cloud' => 'cloud',
        'glyphicon-cloud-download' => 'cloud-download',
        'glyphicon-cloud-upload' => 'cloud-upload',
        'glyphicon-cog' => 'cog',
        'glyphicon-collapse-down' => 'collapse-down',
        'glyphicon-collapse-up' => 'collapse-up',
        'glyphicon-comment' => 'comment',
        'glyphicon-compressed' => 'compressed',
        'glyphicon-copyright-mark' => 'copyright-mark',
        'glyphicon-credit-card' => 'credit-card',
        'glyphicon-cutlery' => 'cutlery',
        'glyphicon-dashboard' => 'dashboard',
        'glyphicon-download' => 'download',
        'glyphicon-download-alt' => 'download-alt',
        'glyphicon-earphone' => 'earphone',
        'glyphicon-edit' => 'edit',
        'glyphicon-eject' => 'eject',
        'glyphicon-envelope' => 'envelope',
        'glyphicon-euro' => 'euro',
        'glyphicon-exclamation-sign' => 'exclamation-sign',
        'glyphicon-expand' => 'expand',
        'glyphicon-export' => 'export',
        'glyphicon-eye-close' => 'eye-close',
        'glyphicon-eye-open' => 'eye-open',
        'glyphicon-facetime-video' => 'facetime-video',
        'glyphicon-fast-backward' => 'fast-backward',
        'glyphicon-fast-forward' => 'fast-forward',
        'glyphicon-file' => 'file',
        'glyphicon-film' => 'film',
        'glyphicon-filter' => 'filter',
        'glyphicon-fire' => 'fire',
        'glyphicon-flag' => 'flag',
        'glyphicon-flash' => 'flash',
        'glyphicon-floppy-disk' => 'floppy-disk',
        'glyphicon-floppy-open' => 'floppy-open',
        'glyphicon-floppy-remove' => 'floppy-remove',
        'glyphicon-floppy-save' => 'floppy-save',
        'glyphicon-floppy-saved' => 'floppy-saved',
        'glyphicon-folder-close' => 'folder-close',
        'glyphicon-folder-open' => 'folder-open',
        'glyphicon-font' => 'font',
        'glyphicon-forward' => 'forward',
        'glyphicon-fullscreen' => 'fullscreen',
        'glyphicon-gbp' => 'gbp',
        'glyphicon-gift' => 'gift',
        'glyphicon-glass' => 'glass',
        'glyphicon-globe' => 'globe',
        'glyphicon-hand-down' => 'hand-down',
        'glyphicon-hand-left' => 'hand-left',
        'glyphicon-hand-right' => 'hand-right',
        'glyphicon-hand-up' => 'hand-up',
        'glyphicon-hd-video' => 'hd-video',
        'glyphicon-hdd' => 'hdd',
        'glyphicon-header' => 'header',
        'glyphicon-headphones' => 'headphones',
        'glyphicon-heart' => 'heart',
        'glyphicon-heart-empty' => 'heart-empty',
        'glyphicon-home' => 'home',
        'glyphicon-import' => 'import',
        'glyphicon-inbox' => 'inbox',
        'glyphicon-indent-left' => 'indent-left',
        'glyphicon-indent-right' => 'indent-right',
        'glyphicon-info-sign' => 'info-sign',
        'glyphicon-italic' => 'italic',
        'glyphicon-leaf' => 'leaf',
        'glyphicon-link' => 'link',
        'glyphicon-list' => 'list',
        'glyphicon-list-alt' => 'list-alt',
        'glyphicon-lock' => 'lock',
        'glyphicon-log-in' => 'log-in',
        'glyphicon-log-out' => 'log-out',
        'glyphicon-magnet' => 'magnet',
        'glyphicon-map-marker' => 'map-marker',
        'glyphicon-minus' => 'minus',
        'glyphicon-minus-sign' => 'minus-sign',
        'glyphicon-move' => 'move',
        'glyphicon-music' => 'music',
        'glyphicon-new-window' => 'new-window',
        'glyphicon-off' => 'off',
        'glyphicon-ok' => 'ok',
        'glyphicon-ok-circle' => 'ok-circle',
        'glyphicon-ok-sign' => 'ok-sign',
        'glyphicon-open' => 'open',
        'glyphicon-paperclip' => 'paperclip',
        'glyphicon-pause' => 'pause',
        'glyphicon-pencil' => 'pencil',
        'glyphicon-phone' => 'phone',
        'glyphicon-phone-alt' => 'phone-alt',
        'glyphicon-picture' => 'picture',
        'glyphicon-plane' => 'plane',
        'glyphicon-play' => 'play',
        'glyphicon-play-circle' => 'play-circle',
        'glyphicon-plus' => 'plus',
        'glyphicon-plus-sign' => 'plus-sign',
        'glyphicon-print' => 'print',
        'glyphicon-pushpin' => 'pushpin',
        'glyphicon-qrcode' => 'qrcode',
        'glyphicon-question-sign' => 'question-sign',
        'glyphicon-random' => 'random',
        'glyphicon-record' => 'record',
        'glyphicon-refresh' => 'refresh',
        'glyphicon-registration-mark' => 'registration-mark',
        'glyphicon-remove' => 'remove',
        'glyphicon-remove-circle' => 'remove-circle',
        'glyphicon-remove-sign' => 'remove-sign',
        'glyphicon-repeat' => 'repeat',
        'glyphicon-resize-full' => 'resize-full',
        'glyphicon-resize-horizontal' => 'resize-horizontal',
        'glyphicon-resize-small' => 'resize-small',
        'glyphicon-resize-vertical' => 'resize-vertical',
        'glyphicon-retweet' => 'retweet',
        'glyphicon-road' => 'road',
        'glyphicon-save' => 'save',
        'glyphicon-saved' => 'saved',
        'glyphicon-screenshot' => 'screenshot',
        'glyphicon-sd-video' => 'sd-video',
        'glyphicon-search' => 'search',
        'glyphicon-send' => 'send',
        'glyphicon-share' => 'share',
        'glyphicon-share-alt' => 'share-alt',
        'glyphicon-shopping-cart' => 'shopping-cart',
        'glyphicon-signal' => 'signal',
        'glyphicon-sort' => 'sort',
        'glyphicon-sort-by-alphabet' => 'sort-by-alphabet',
        'glyphicon-sort-by-alphabet-alt' => 'sort-by-alphabet-alt',
        'glyphicon-sort-by-attributes' => 'sort-by-attributes',
        'glyphicon-sort-by-attributes-alt' => 'sort-by-attributes-alt',
        'glyphicon-sort-by-order' => 'sort-by-order',
        'glyphicon-sort-by-order-alt' => 'sort-by-order-alt',
        'glyphicon-sound-5-1' => 'sound-5-1',
        'glyphicon-sound-6-1' => 'sound-6-1',
        'glyphicon-sound-7-1' => 'sound-7-1',
        'glyphicon-sound-dolby' => 'sound-dolby',
        'glyphicon-sound-stereo' => 'sound-stereo',
        'glyphicon-star' => 'star',
        'glyphicon-star-empty' => 'star-empty',
        'glyphicon-stats' => 'stats',
        'glyphicon-step-backward' => 'step-backward',
        'glyphicon-step-forward' => 'step-forward',
        'glyphicon-stop' => 'stop',
        'glyphicon-subtitles' => 'subtitles',
        'glyphicon-tag' => 'tag',
        'glyphicon-tags' => 'tags',
        'glyphicon-tasks' => 'tasks',
        'glyphicon-text-height' => 'text-height',
        'glyphicon-text-width' => 'text-width',
        'glyphicon-th' => 'th',
        'glyphicon-th-large' => 'th-large',
        'glyphicon-th-list' => 'th-list',
        'glyphicon-thumbs-down' => 'thumbs-down',
        'glyphicon-thumbs-up' => 'thumbs-up',
        'glyphicon-time' => 'time',
        'glyphicon-tint' => 'tint',
        'glyphicon-tower' => 'tower',
        'glyphicon-transfer' => 'transfer',
        'glyphicon-trash' => 'trash',
        'glyphicon-tree-conifer' => 'tree-conifer',
        'glyphicon-tree-deciduous' => 'tree-deciduous',
        'glyphicon-unchecked' => 'unchecked',
        'glyphicon-upload' => 'upload',
        'glyphicon-usd' => 'usd',
        'glyphicon-user' => 'user',
        'glyphicon-volume-down' => 'volume-down',
        'glyphicon-volume-off' => 'volume-off',
        'glyphicon-volume-up' => 'volume-up',
        'glyphicon-warning-sign' => 'warning-sign',
        'glyphicon-wrench' => 'wrench',
        'glyphicon-zoom-in' => 'zoom-in',
        'glyphicon-zoom-out' => 'zoom-out',
      ];
      $versions['3.0.1'] = $versions['3.0.0'];
      $versions['3.0.2'] = $versions['3.0.1'];
      $versions['3.0.3'] = $versions['3.0.2'];
      $versions['3.1.0'] = $versions['3.0.3'];
      $versions['3.1.1'] = $versions['3.1.0'];
      $versions['3.2.0'] = $versions['3.1.1'];
      $versions['3.3.0'] = array_merge($versions['3.2.0'], [
        'glyphicon-eur' => 'eur',
      ]);
      $versions['3.3.1'] = $versions['3.3.0'];
      $versions['3.3.2'] = array_merge($versions['3.3.1'], [
        'glyphicon-alert' => 'alert',
        'glyphicon-apple' => 'apple',
        'glyphicon-baby-formula' => 'baby-formula',
        'glyphicon-bed' => 'bed',
        'glyphicon-bishop' => 'bishop',
        'glyphicon-bitcoin' => 'bitcoin',
        'glyphicon-blackboard' => 'blackboard',
        'glyphicon-cd' => 'cd',
        'glyphicon-console' => 'console',
        'glyphicon-copy' => 'copy',
        'glyphicon-duplicate' => 'duplicate',
        'glyphicon-education' => 'education',
        'glyphicon-equalizer' => 'equalizer',
        'glyphicon-erase' => 'erase',
        'glyphicon-grain' => 'grain',
        'glyphicon-hourglass' => 'hourglass',
        'glyphicon-ice-lolly' => 'ice-lolly',
        'glyphicon-ice-lolly-tasted' => 'ice-lolly-tasted',
        'glyphicon-king' => 'king',
        'glyphicon-knight' => 'knight',
        'glyphicon-lamp' => 'lamp',
        'glyphicon-level-up' => 'level-up',
        'glyphicon-menu-down' => 'menu-down',
        'glyphicon-menu-hamburger' => 'menu-hamburger',
        'glyphicon-menu-left' => 'menu-left',
        'glyphicon-menu-right' => 'menu-right',
        'glyphicon-menu-up' => 'menu-up',
        'glyphicon-modal-window' => 'modal-window',
        'glyphicon-object-align-bottom' => 'object-align-bottom',
        'glyphicon-object-align-horizontal' => 'object-align-horizontal',
        'glyphicon-object-align-left' => 'object-align-left',
        'glyphicon-object-align-right' => 'object-align-right',
        'glyphicon-object-align-top' => 'object-align-top',
        'glyphicon-object-align-vertical' => 'object-align-vertical',
        'glyphicon-oil' => 'oil',
        'glyphicon-open-file' => 'open-file',
        'glyphicon-option-horizontal' => 'option-horizontal',
        'glyphicon-option-vertical' => 'option-vertical',
        'glyphicon-paste' => 'paste',
        'glyphicon-pawn' => 'pawn',
        'glyphicon-piggy-bank' => 'piggy-bank',
        'glyphicon-queen' => 'queen',
        'glyphicon-ruble' => 'ruble',
        'glyphicon-save-file' => 'save-file',
        'glyphicon-scale' => 'scale',
        'glyphicon-scissors' => 'scissors',
        'glyphicon-subscript' => 'subscript',
        'glyphicon-sunglasses' => 'sunglasses',
        'glyphicon-superscript' => 'superscript',
        'glyphicon-tent' => 'tent',
        'glyphicon-text-background' => 'text-background',
        'glyphicon-text-color' => 'text-color',
        'glyphicon-text-size' => 'text-size',
        'glyphicon-triangle-bottom' => 'triangle-bottom',
        'glyphicon-triangle-left' => 'triangle-left',
        'glyphicon-triangle-right' => 'triangle-right',
        'glyphicon-triangle-top' => 'triangle-top',
        'glyphicon-yen' => 'yen',
      ]);
      $versions['3.3.4'] = array_merge($versions['3.3.2'], [
        'glyphicon-btc' => 'btc',
        'glyphicon-jpy' => 'jpy',
        'glyphicon-rub' => 'rub',
        'glyphicon-xbt' => 'xbt',
      ]);
      $versions['3.3.5'] = $versions['3.3.4'];
    }

    // Return a specific versions icon set.
    if (isset($version) && isset($versions[$version])) {
      return $versions[$version];
    }

    // Return the latest version.
    return $versions[static::FRAMEWORK_VERSION];
  }

  /**
   * Initializes the active theme.
   */
  final public static function initialize() {
    static $initialized = FALSE;
    if (!$initialized) {
      // Initialize the active theme.
      $active_theme = static::getTheme();

      // Include any deprecated.inc file from each theme.
      // @todo create a setting that makes this opt-in.
      foreach ($active_theme->getAncestry() as $ancestor) {
        $files = $ancestor->fileScan('/^deprecated\.(inc|inc\.php|php)$/');
        if ($file = reset($files)) {
          $ancestor->includeOnce($file->uri, FALSE);
        }
      }

      $initialized = TRUE;
    }
  }

  /**
   * Preprocess theme hook variables.
   *
   * @param array $variables
   *   The variables array, passed by reference.
   * @param string $hook
   *   The name of the theme hook.
   */
  public static function preprocess(array &$variables, $hook) {
    static $theme;
    if (!isset($theme)) {
      $theme = static::getTheme();
    }

    // Add extra variables to all theme hooks.
    foreach (static::extraVariables() as $key => $value) {
      if (!isset($variables[$key])) {
        $variables[$key] = $value;
      }
    }

    // Retrieve a list of preprocess definitions.
    $preprocess_manager = new PreprocessManager($theme);

    // Extrapolate the theme hooks (with __ suggestions).
    // @todo This is really ugly, hook_preprocess() currently has limitations.
    $preprocess = [];
    $hooks = array_unique([$hook, $variables['theme_hook_original']]);
    foreach ($hooks as $hook) {
      $suggestions = explode('__', $hook);
      $hook = array_shift($suggestions);
      if (!in_array($hook, $preprocess)) {
        $preprocess[] = $hook;
      }
      foreach ($suggestions as $suggestion) {
        $hook .= "__$suggestion";
        if (!in_array($hook, $preprocess)) {
          $preprocess[] = $hook;
        }
      }
    }

    // Invoke necessary preprocess plugins.
    foreach ($preprocess as $hook) {
      /** @var \Drupal\bootstrap\Plugin\Preprocess\PreprocessInterface $class */
      if ($preprocess_manager->hasDefinition($hook) && ($class = $preprocess_manager->createInstance($hook))) {
        $class->preprocess($variables);
      }
    }
  }

}
