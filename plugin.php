<?php defined( 'App' ) or die( 'BoidCMS' );
/**
 *
 * AMP – Accelerated Mobile Pages
 *
 * @package Plugin_AMP
 * @author Shuaib Yusuf Shuaib
 * @version 0.1.0
 */

if ( 'amp' !== basename( __DIR__ ) ) return;

global $App;
$App->set_action( 'install', 'amp_install' );
$App->set_action( 'uninstall', 'amp_uninstall' );
$App->set_action( 'site_head', 'amp_rel_amphtml' );
$App->set_action( [ 'update_success', 'delete_success' ], 'amp_delete_cache' );
$App->set_action( 'amp:pre_build', 'amp_optimize' );
$App->set_action( 'rendered', 'amp_rendered' );
$App->set_action( 'render', 'amp_render' );
$App->set_action( 'admin', 'amp_admin' );

/**
 * Initialize AMP, first time install
 * @param string $plugin
 * @return void
 */
function amp_install( string $plugin ): void {
  global $App;
  if ( 'amp' === $plugin ) {
    $config = array();
    $config[ 'exclude' ] = array();
    $config[ 'template' ] = 'prime';
    $dir = $App->root( 'data/ampcache' );
    ( is_dir( $dir ) ?: mkdir( $dir ) );
    $App->set( $config, 'amp' );
  }
}

/**
 * Free database space, while uninstalled
 * @param string $plugin
 * @return void
 */
function amp_uninstall( string $plugin ): void {
  global $App;
  if ( 'amp' === $plugin ) {
    $App->unset( 'amp' );
    amp_wipe_all_cache();
  }
}

/**
 * AMP discovery link
 * @param ?string $slug
 * @return ?string
 */
function amp_rel_amphtml( ?string $slug = null ): ?string {
  if ( amp_excluded() ) {
    return null;
  }
  
  global $App;
  $config = $App->get( 'amp' );
  $link = $App->url( $slug ?? $App->page );
  if ( str_contains( $link, '?' ) ) {
    $structure = '&amp';
  }
  
  $structure ??= '?amp';
  $format = '<link rel="amphtml" href="%s%s">';
  return sprintf( $format, $link, $structure );
}

/**
 * Render AMP cache
 * @return void
 */
function amp_render(): void {
  if ( amp_ready() ) {
    $file = amp_cache_file();
    if ( ! is_file( $file ) ) {
      amp_rendered();
    }
    
    readfile( $file );
    exit;
  }
}

/**
 * Build AMP cache
 * @return void
 */
function amp_rendered(): void {
  if ( amp_ready() ) {
    ob_start( 'amp_build_cache' );
    require amp_template();
    ob_end_clean();
  }
}

/**
 * Server side optimize AMP
 * @param string $buffer
 * @param string $slug
 * @return ?string
 */
function amp_optimize( string $buffer, string $slug ): string {
  require_once ( __DIR__ . '/vendor/autoload.php' );
  
  $errorCollection      = new AmpProject\Optimizer\ErrorCollection;
  $transformationEngine = new AmpProject\Optimizer\TransformationEngine();
  
  $optimized = $transformationEngine->optimizeHtml(
    $buffer,
    $errorCollection
  );
  
  if ( $errorCollection->count() === 0 ) {
    return $optimized;
  }
  
  $error_msg = 'AMP Optimizer Error(s) for Page "' . $slug . '": ' . PHP_EOL;
  foreach ( $errorCollection as $error ) {
    $error_msg .= ( $error->getCode() . ': ' . $error->getMessage() . PHP_EOL );
  }
  
  global $App;
  $App->log( $error_msg );
  return $optimized;
}

/**
 * Admin settings
 * @return void
 */
function amp_admin(): void {
  global $App, $layout, $page;
  switch ( $page ) {
    case 'amp':
    $config = $App->get( 'amp' );
    $layout[ 'title' ] = 'AMP';
    $layout[ 'content' ] = '
    <form action="' . $App->admin_url( '?page=amp', true ) . '" method="post">
      <label for="template" class="ss-label">Template</label>
      <select id="template" name="template" class="ss-select ss-mobile ss-w-6 ss-auto">';
    foreach ( amp_templates() as $template ) {
      $layout[ 'content' ] .= '<option value="' . $template . '">' . ucwords( str_replace( [ '-', '_' ], ' ', $template ) ) . '</option>';
    }
    $layout[ 'content' ] .= '
      </select>
      <label for="exclude" class="ss-label">Exclude Pages</label>
      <select id="exclude" name="exclude[]" class="ss-select ss-mobile ss-w-6 ss-auto" multiple>';
    foreach ( $App->data()[ 'pages' ] as $slug => $post ) {
      if ( $post[ 'pub' ] ) {
        $exclude = in_array( $slug, $config[ 'exclude' ] );
        $layout[ 'content' ] .= '<option value="' . $slug . '"' . ( $exclude ? ' selected' : '' ) . '>' . $post[ 'title' ] . ' "' . $slug . '"</option>';
      }
    }
    $layout[ 'content' ] .= '
      </select>
      <p class="ss-small ss-mb-5">This option allows you to disable AMP on selected pages.</p>
      <input type="hidden" name="token" value="' . $App->token() . '">
      <input type="submit" name="save" value="Save" class="ss-btn ss-mobile ss-w-5">
    </form>';
    if ( isset( $_POST[ 'save' ] ) ) {
      $App->auth();
      $config[ 'exclude' ] = ( $_POST[ 'exclude' ] ?? array() );
      $template_changed = ( ( $_POST[ 'template' ] ?? '' ) !== $config[ 'template' ] );
      $config[ 'template' ] = ( $_POST[ 'template' ] ?? $config[ 'template' ] );
      if ( $App->set( $config, 'amp' ) ) {
        if ( $template_changed ) {
          amp_wipe_all_cache( false );
        }
        $App->alert( 'Settings saved successfully.', 'success' );
        $App->go( $App->admin_url( '?page=amp' ) );
      }
      $App->alert( 'Failed to save settings, please try again.', 'error' );
      $App->go( $App->admin_url( '?page=amp' ) );
    }
    require_once $App->root( 'app/layout.php' );
    break;
  }
}

/**
 * Tells whether amp is requested
 * @return bool
 */
function is_amp(): bool {
  return isset( $_GET[ 'amp' ] );
}

/**
 * Tells whether AMP should be served
 * @return bool
 */
function amp_ready(): bool {
  global $App;
  $ready = ( $App->page( 'pub' ) && is_amp() && ! amp_excluded() );
  return $App->get_filter( $ready, 'amp:ready' );
}

/**
 * Escape text
 * @param ?string $text
 * @return string
 */
function amp_escape( ?string $text ): string {
  return htmlspecialchars(   $text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8', false );
}

/**
 * Tells whether slug is excluded
 * @return bool
 */
function amp_excluded( ?string $slug = null ): bool {
  global $App;
  $slug ??= $App->page;
  $excluded = $App->get( 'amp' )[ 'exclude' ];
  return in_array( $slug, $excluded );
}

/**
 * Server side render AMP
 * @param string $buffer
 * @return void
 */
function amp_build_cache( string $buffer ): void {
  global $App;
  $buffer = $App->get_filter( $buffer, 'amp:pre_build', $App->page );
  if ( ! empty( $buffer ) ) {
    file_put_contents( amp_cache_file(), $buffer, LOCK_EX );
  }
}

/**
 * Cache file path
 * @param ?string $slug
 * @return string
 */
function amp_cache_file( ?string $slug = null ): string {
  global $App;
  return $App->root( 'data/ampcache/' . md5( $slug ?? $App->page ) );
}

/**
 * Delete AMP page cache
 * @param string $slug
 * @return bool
 */
function amp_delete_cache( string $slug ): bool {
  return unlink( amp_cache_file( $slug ) );
}

/**
 * Delete all AMP pages cache
 * @param bool $whole
 * @return void
 */
function amp_wipe_all_cache( bool $whole = true ): void {
  global $App;
  $dir = $App->root( 'data/ampcache/' );
  foreach ( glob( $dir . '*' ) as $file ) {
    unlink( $file );
  }
  
  if ( $whole ) {
    rmdir( $dir );
  }
}

/**
 * Local templates
 * @param bool $system
 * @return array
 */
function amp_templates( bool $system = false ): array {
  $folder = ( __DIR__ . '/templates/' );
  $templates = scandir( $folder );
  foreach ( $templates as $index => $template ) {
    if ( '.' === $template || '..' === $template ) {
      unset( $templates[ $index ] );
    }
    
    if ( ! is_dir( $folder . $template ) ) {
      unset( $templates[ $index ] );
    }
    
    if ( $system ) {
      $templates[ $index ] = ( $folder . $template );
    }
  }
  
  return $templates;
}

/**
 * Get AMP and components
 * @param ?string $content
 * @return array
 */
function ampify( ?string $content ): array {
  return [
    ( $content = amp_convert( $content ) ),
      amp_content_components( $content )
  ];
}

/**
 * Apply converters to content
 * @param ?string $content
 * @return string
 */
function amp_convert( ?string $content ): string {
  if ( empty( $content ) )  return '';
  return amp_convert_tags( $content );
}

/**
 * AMP template file
 * @param ?string $type
 * @return string
 */
function amp_template( ?string $type = null ): string {
  global $App;
  $type ??= $App->page( 'type' );
  $custom = $App->get_filter( null, 'amp:template', $type );
  if ( ! empty( $custom ) ) {
    return $custom;
  }
  
  $template = $App->get( 'amp' )[ 'template' ];
  $template = ( __DIR__ . '/templates/' . $template );
  $file = ( $template . '/' . $type . '.php' );
  if ( is_file( $file ) ) {
    return $file;
  }
  
  return ( $template . '/single.php' );
}

/**
 * Apply converters to content
 * @param string $content
 * @return string
 */
function amp_convert_tags( string $content ): string {
  $content = amp_convert_img_tags( $content );
  $content = amp_convert_audio_tags( $content );
  $content = amp_convert_video_tags( $content );
  $content = amp_convert_iframe_tags( $content );
  $content = amp_convert_form_tags( $content );
  $content = amp_custom_converters( $content );
  return amp_strip_tags( $content );
}

/**
 * Convert image tags
 * @param string $content
 * @return string
 */
function amp_convert_img_tags( string $content ): string {
  // Required attributes
  $atts[  'alt'   ] = '';
  $atts[ 'height' ] = '900';
  $atts[ 'width'  ] = '1600';
  $atts[ 'layout' ] = 'responsive';
  
  // Convert non-amp tags
  $regexp = '|\<img\b([\s\S]*?)\>|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $the_atts = amp_element_atts( $match[1], $atts, $all );
    $amp = '<amp-img' . $the_atts . '>' . amp_fallback_image() . '</amp-img>';
    if ( ! isset( $all[ 'src' ] ) || empty( $all[ 'src' ] ) ) $amp = '';
    $content = str_replace( $match[0], $amp, $content );
  }
  
  // Fix tags inside noscript tag
  $ban_atts = [ 'layout' ];
  $regexp = '|\<noscript\b([\s\S]*?)\>([\s\S]*?)\</noscript\>|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $fix = $match[0];
    if ( str_contains( $match[2], '<amp-img' ) ) {
      $regexp = '|\<amp\-img\b([\s\S]*?)\>([\s\S]*?)\</amp\-img\>|i';
      preg_match_all( $regexp, $match[2], $mchs, PREG_SET_ORDER );
      foreach ( $mchs as $mch ) {
        $non_amp = '<img' . amp_element_atts( $mch[1], ban_atts: $ban_atts ) . '>';
        $fix = str_replace( [ $mch[0] . '</amp-img>', $mch[0] ], $non_amp, $fix );
      }
    }
    
    $content = str_replace( $match[0], $fix, $content );
  }
  
  return $content;
}

/**
 * Convert audio tags
 * @param string $content
 * @return string
 */
function amp_convert_audio_tags( string $content ): string {
  // Required attributes
  $atts[ 'height' ] = '50';
  $atts[ 'width'  ] = 'auto';
  
  // Convert non-amp tags
  $regexp = '|\<audio\b([\s\S]*?)\>([\s\S]*?)\</audio\>|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $the_atts = amp_element_atts( $match[1], $atts, $all );
    $amp = '<amp-audio' . $the_atts . '>' . amp_convert( $match[2] ) . amp_fallback_text( 'audio' ) . '</amp-audio>';
    if ( ! isset( $all[ 'src' ] ) || empty( $all[ 'src' ] ) ) $amp = '';
    $content = str_replace( $match[0], $amp, $content );
  }
  
  return $content;
}

/**
 * Convert video tags
 * @param string $content
 * @return string
 */
function amp_convert_video_tags( string $content ): string {
  // Required attributes
  $atts[ 'height' ] = '360';
  $atts[ 'width'  ] = '640';
  $atts[ 'layout' ] = 'responsive';
  $atts[ 'poster' ] = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAIAQMAAAD+wSzIAAAABlBMVEX///+/v7+jQ3Y5AAAADklEQVQI12P4AIX8EAgALgAD/aNpbtEAAAAASUVORK5CYII';
  
  // Convert non-amp tags
  $regexp = '|\<video\b([\s\S]*?)\>([\s\S]*?)\</video\>|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $the_atts = amp_element_atts( $match[1], $atts, $all );
    $amp = '<amp-video' . $the_atts . '>' . amp_convert( $match[2] ) . amp_fallback_text( 'video' ) . '</amp-video>';
    $content = str_replace( $match[0], $amp, $content );
  }
  
  return $content;
}

/**
 * Convert iframe tags
 * @param string $content
 * @return string
 */
function amp_convert_iframe_tags( string $content ): string {
  // Required attributes
  $atts[ 'height' ] = '300';
  $atts[ 'width'  ] = '300';
  $atts[ 'layout'  ] = 'responsive';
  $atts[ 'sandbox'  ] = 'allow-scripts allow-same-origin';
  
  // Convert non-amp tags
  $regexp = '|\<iframe\b([\s\S]*?)\>([\s\S]*?)\</iframe\>|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $the_atts = amp_element_atts( $match[1], $atts, $all );
    $amp = '<amp-iframe' . $the_atts . '>' . amp_convert( $match[2] ) . amp_placeholder_image() . '</amp-iframe>';
    if ( ! isset( $all[ 'src' ] ) || empty( $all[ 'src' ] ) ) $amp = '';
    $content = str_replace( $match[0], $amp, $content );
  }
  
  return $content;
}

/**
 * Convert form tags
 * @param string $content
 * @return string
 */
function amp_convert_form_tags( string $content ): string {
  // Required attributes
  $atts[ 'method' ] = 'get';
  $atts[ 'target' ] = '_top';
  
  // Convert non-amp compatible tags
  $regexp = '|\<form\b([\s\S]*?)\>([\s\S]*?)\</form\>|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $the_atts = amp_element_atts( $match[1], $atts, $all );
    if ( 'get' === $all[ 'method' ] ) {
      if ( ! isset( $all[ 'action' ] ) || empty( $all[ 'action' ] ) ) {
        $amp = '';
      } else {
        $amp = '<form' . $the_atts . '>' . amp_convert( $match[2] ) . '</form>';
      }
    } else {
      $amp = '<form' . $the_atts . '>' . amp_convert( $match[2] ) . '</form>';
      if ( ! isset( $all[ 'action-xhr' ] ) || empty( $all[ 'src' ] ) ) $amp = '';
    }
    
    $content = str_replace( $match[0], $amp, $content );
  }
  
  return $content;
}

/**
 * Apply custom converters
 * @param string $content
 * @return string
 */
function amp_custom_converters( string $content ): string {
  global $App;
  return $App->get_filter( $content, 'amp:convert' );
}

/**
 * Strip illegal tags
 * @param string $content
 * @return string
 */
function amp_strip_tags( string $content ): string {
  // Remove "base" tags
  $regexp = '|\<base\b([\s\S]*?)\>|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content = str_replace( $match[0], '', $content );
  }
  
  // Remove "frame" & "frameset" tags
  $regexp = '|\<(?<tag>frame(set)?)\b([\s\S]*?)\>(([\s\S]*?)\<\/(?P=tag)\>)?|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content = str_replace( $match[0], '', $content );
  }
  
  // Remove "object" tags
  $regexp = '|\<object\b([\s\S]*?)\>([\s\S]*?)\<\/object\>|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content = str_replace( $match[0], '', $content );
  }
  
  // Remove "param" tags
  $regexp = '|\<param\b([\s\S]*?)\>|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content = str_replace( $match[0], '', $content );
  }
  
  // Remove "applet" tags
  $regexp = '|\<applet\b([\s\S]*?)\>([\s\S]*?)\<\/applet\>|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content = str_replace( $match[0], '', $content );
  }
  
  // Remove "embed" tags
  $regexp = '|\<embed\b([\s\S]*?)\>|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content = str_replace( $match[0], '', $content );
  }
  
  // Remove "style" tags
  $regexp = '|\<style\b([\s\S]*?)\>([\s\S]*?)\<\/style\>|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content = str_replace( $match[0], '', $content );
  }
  
  // Remove "script" tags
  $regexp = '|\<script\b([\s\S]*?)\>([\s\S]*?)\<\/script\>|i';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content = str_replace( $match[0], '', $content );
  }
  
  // Remove comments tags
  $regexp = '|\<\!\-\-[\s\S]*?\-\-\>|';
  preg_match_all( $regexp, $content, $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content = str_replace( $match[0], '', $content );
  }
  
  return trim( $content );
}

/**
 * Build element attributes
 * @param string $element
 * @param array $atts
 * @param ?array &$all_atts
 * @param array $ban_atts
 * @return string
 */
function amp_element_atts( string $element, array $atts = [], ?array &$all_atts = null, array $ban_atts = [] ): string {
  $str  = '';
  $atts = amp_filter_element_atts( $element, $atts, $ban_atts );
  foreach ( $atts as $index => $value ) {
    if ( '' === $value ) {
      $str .= ' ' . $index;
      continue;
    }
    
    $quot = '"';
    if ( str_contains( $value, '"' ) ) $quot = "'";
    $str .= sprintf( ' %s=%3$s%s%3$s', $index, $value, $quot );
  }
  
  $all_atts = $atts;
  return $str;
}

/**
 * Recover element attributes
 * @param string $element
 * @return array
 */
function amp_element_get_atts( string $element ): array {
  $regexp = '/([\w-]+)(\s*\=\s*(?|(?<quot>[\'"])([\s\S]*?)(?P=quot)|(?<quot>)([^\s\'"\=\<\>\`]+)))?/';
  preg_match_all(  $regexp, $element, $match  );
  $match[1] = array_map( 'strtolower', $match[1] );
  return array_combine( $match[1], $match[4] );
}

/**
 * Filter element attributes
 * @param string $element
 * @param array $must_atts
 * @param array $ban_atts
 * @return array
 */
function amp_filter_element_atts( string $element, array $must_atts = [], array $ban_atts = [] ): array {
  $regex = '|^on(.+)|i';
  $atts = amp_element_get_atts( $element );
  $atts = array_merge( $must_atts, $atts );
  foreach ( $atts as $index => $att ) {
    if ( preg_match( $regex, $index ) ) {
      unset( $atts[ $index ] );
      continue;
    }
    
    if ( isset( $must_atts[ $index ] ) ) {
      if ( empty( trim( $att ) ) ) {
        $atts[ $index ] = $must_atts[ $index ];
      }
    }
  }
  
  $ban_atts = array_flip( $ban_atts );
  $atts = array_diff_key( $atts, $ban_atts );
  return $atts;
}

/**
 * Placeholder image element
 * @return string
 */
function amp_placeholder_image(): string {
  return '<amp-img placeholder alt layout="fill" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAIAQMAAAD+wSzIAAAABlBMVEX///+/v7+jQ3Y5AAAADklEQVQI12P4AIX8EAgALgAD/aNpbtEAAAAASUVORK5CYII"></amp-img>';
}

/**
 * Fallback image element
 * @return string
 */
function amp_fallback_image(): string {
  return '<amp-img fallback alt layout="fill" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAIAQMAAAD+wSzIAAAABlBMVEX///+/v7+jQ3Y5AAAADklEQVQI12P4AIX8EAgALgAD/aNpbtEAAAAASUVORK5CYII"></amp-img>';
}

/**
 * Fallback element
 * @param string $tag
 * @return string
 */
function amp_fallback_text( string $tag ): string {
  return '<div fallback hidden>This browser does not support the ' . amp_escape( $tag ) . ' element.</div>';
}

/**
 * Content components
 * @param string $content
 * @return string
 */
function amp_content_components( string $content ): string {
  $list = '';
  $all = amp_components();
  $format = '<script async src="%s" custom-element="%s"></script>';
  foreach ( $all as $component => $data ) {
    if ( preg_match( $data[ 'regex' ], $content ) ) {
      $list .= sprintf( $format, ...array_values( $data ) ) . PHP_EOL;
    }
  }
  
  return $list;
}

/**
 * Custom components script
 * @return array
 */
function amp_components(): array {
  global $App;
  $comp = array();
  
  // Audio component
  $comp[ 'amp-audio' ] = array();
  $comp[ 'amp-audio' ][ 'link' ] = 'https://cdn.ampproject.org/v0/amp-audio-0.1.js';
  $comp[ 'amp-audio' ][ 'name' ] = 'amp-audio';
  $comp[ 'amp-audio' ][ 'regex' ] = '|\<amp\-audio|i';
  
  // Form component
  $comp[ 'amp-form' ] = array();
  $comp[ 'amp-form' ][ 'link' ] = 'https://cdn.ampproject.org/v0/amp-form-0.1.js';
  $comp[ 'amp-form' ][ 'name' ] = 'amp-form';
  $comp[ 'amp-form' ][ 'regex' ] = '|\<form|i';
  
  // Iframe component
  $comp[ 'amp-iframe' ] = array();
  $comp[ 'amp-iframe' ][ 'link' ] = 'https://cdn.ampproject.org/v0/amp-iframe-0.1.js';
  $comp[ 'amp-iframe' ][ 'name' ] = 'amp-iframe';
  $comp[ 'amp-iframe' ][ 'regex' ] = '|\<amp\-iframe|i';
  
  // Video component
  $comp[ 'amp-video' ] = array();
  $comp[ 'amp-video' ][ 'link' ] = 'https://cdn.ampproject.org/v0/amp-video-0.1.js';
  $comp[ 'amp-video' ][ 'name' ] = 'amp-video';
  $comp[ 'amp-video' ][ 'regex' ] = '|\<amp\-video|i';
  
  // Mustache component
  $comp[ 'amp-mustache' ] = array();
  $comp[ 'amp-mustache' ][ 'link' ] = 'https://cdn.ampproject.org/v0/amp-mustache-0.2.js';
  $comp[ 'amp-mustache' ][ 'name' ] = 'amp-mustache';
  $comp[ 'amp-mustache' ][ 'regex' ] = '|type=amp\-mustache|i';
  
  return $App->get_filter( $comp, 'amp:components' );
}
?>
