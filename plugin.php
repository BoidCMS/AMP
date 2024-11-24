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
$App->set_action( [ 'update_success', 'delete_success' ], 'delete_amp_cache' );
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
  global $App;
  if ( ! $App->page( 'pub' ) ||
         amp_excluded()
     ) return null;
  
  $structure = '?amp';
  $link = $App->url( $slug ?? $App->page );
  if ( str_contains( $link, '?' ) ) {
    $structure = '&amp';
  }
  
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
  
  $error_msg = '';
  foreach ( $errorCollection as $error ) {
    $error_msg .= ( '~~~' . date( DATE_RSS ) . PHP_EOL . $error->getCode() . PHP_EOL . $error->getMessage() . '~~~' . PHP_EOL );
  }
  
  global $App;
  $file = $App->root( 'data/ampcache/@log_' . md5( $slug ) );
  file_put_contents( $file, $error_msg, FILE_APPEND | LOCK_EX );
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
function delete_amp_cache( string $slug ): bool {
  $file = amp_cache_file( $slug );
  if ( is_file( $file ) ) {
    return unlink( $file );
  }
  
  return false;
}

/**
 * Delete all files from cache dir
 * @param bool $whole
 * @return void
 */
function amp_wipe_all_cache( bool $whole = true ): void {
  global $App;
  $dir = $App->root( 'data/ampcache/' );
  foreach ( glob( $dir . '*', GLOB_NOSORT ) as $file ) {
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
  if ( is_file( $file ) ) return $file;
  return ( $template . '/theme.php' );
}

/**
 * Alias of amp_convert_tags
 * @param ?string $content
 * @return array
 */
function ampify( ?string $content = null ): array {
  global $App;
  $content ??= $App->page( 'content' );
  if ( empty( trim( $content ?? '' ) ) ) {
    return [ 0 => '', 1 => '' ];
  }
  
  $content = [ 0 => $content, 1 => '' ];
  return   amp_convert_tags( $content );
}

/**
 * Apply converters to content
 * @param array $content
 * @return array
 */
function amp_convert_tags( array $content ): array {
  $content = amp_convert_img_tags(    $content );
  $content = amp_convert_audio_tags(  $content );
  $content = amp_convert_video_tags(  $content );
  $content = amp_convert_iframe_tags( $content );
  $content = amp_convert_form_tags(   $content );
  $content = amp_custom_converters(   $content );
  return     amp_strip_tags(          $content );
}

/**
 * Convert image tags
 * @param array $content
 * @return array
 */
function amp_convert_img_tags( array $content ): array {
  // Required attributes
  $atts[  'alt'   ] = '';
  $atts[ 'height' ] = '720';
  $atts[ 'width'  ] = '1280';
  $atts[ 'layout' ] = 'responsive';
  
  // Convert non-amp tags
  $regexp = '|\<img\b([\s\S]*?)\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $the_atts = amp_element_atts( $match[1], $atts, $all );
    $amp = '<amp-img' . $the_atts . '>' . amp_fallback_image() . '</amp-img>';
    if ( ! isset( $all[ 'src' ] ) || empty( $all[ 'src' ] ) ) $amp = '';
    $content[0] = str_replace( $match[0], $amp, $content[0] );
  }
  
  // Fix tags inside noscript tag
  $ban_atts = [ 'layout' ];
  $regexp = '|\<noscript\b([\s\S]*?)\>([\s\S]*?)\</noscript\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
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
    
    $content[0] = str_replace( $match[0], $fix, $content[0] );
  }
  
  return $content;
}

/**
 * Convert audio tags
 * @param array $content
 * @return array
 */
function amp_convert_audio_tags( array $content ): array {
  // Required attributes
  $atts[ 'height' ] = '50';
  $atts[ 'width'  ] = 'auto';
  
  // Convert non-amp tags
  $regexp = '|\<audio\b([\s\S]*?)\>([\s\S]*?)\</audio\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $the_atts = amp_element_atts( $match[1], $atts, $all );
    $amp = '<amp-audio' . $the_atts . '>' . ampify( $match[2] )[0] . amp_fallback_text( 'audio' ) . '</amp-audio>';
    if ( ! isset( $all[ 'src' ] ) || empty( $all[ 'src' ] ) ) $amp = '';
    $content[0] = str_replace( $match[0], $amp, $content[0] );
  }
  
  // Fix tags inside noscript tag
  $regexp = '|\<noscript\b([\s\S]*?)\>([\s\S]*?)\</noscript\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $fix = $match[0];
    if ( str_contains( $match[2], '<amp-audio' ) ) {
      $regexp = '|\<amp\-audio\b([\s\S]*?)\>([\s\S]*?)\</amp\-audio\>|i';
      preg_match_all( $regexp, $match[2], $mchs, PREG_SET_ORDER );
      foreach ( $mchs as $mch ) {
        $non_amp = '<audio' . amp_element_atts( $mch[1] ) . '>' . str_replace( [ '<noscript>', '</noscript>' ], '', ampify( '<noscript>' . $mch[2] . '</noscript>' )[0] ) . '</audio>';
        $fix = str_replace( $mch[0], $non_amp, $fix );
      }
    }
    
    $content[0] = str_replace( $match[0], $fix, $content[0] );
  }
  
  if ( str_contains( $content[0], '<amp-audio' ) ) {
    // Required extension script
    $content[1] .= '<script async src="https://cdn.ampproject.org/v0/amp-audio-0.1.js" custom-element="amp-audio"></script>';
  }
  
  return $content;
}

/**
 * Convert video tags
 * @param array $content
 * @return array
 */
function amp_convert_video_tags( array $content ): array {
  // Required attributes
  $atts[ 'height' ] = '360';
  $atts[ 'width'  ] = '640';
  $atts[ 'layout' ] = 'responsive';
  $atts[ 'poster' ] = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAIAQMAAAD+wSzIAAAABlBMVEX///+/v7+jQ3Y5AAAADklEQVQI12P4AIX8EAgALgAD/aNpbtEAAAAASUVORK5CYII';
  
  // Convert non-amp tags
  $regexp = '|\<video\b([\s\S]*?)\>([\s\S]*?)\</video\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $the_atts = amp_element_atts( $match[1], $atts, $all );
    $amp = '<amp-video' . $the_atts . '>' . ampify( $match[2] )[0] . amp_fallback_text( 'video' ) . '</amp-video>';
    $content[0] = str_replace( $match[0], $amp, $content[0] );
  }
  
  // Fix tags inside noscript tag
  $ban_atts = [ 'layout', 'poster' ];
  $regexp = '|\<noscript\b([\s\S]*?)\>([\s\S]*?)\</noscript\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $fix = $match[0];
    if ( str_contains( $match[2], '<amp-video' ) ) {
      $regexp = '|\<amp\-video\b([\s\S]*?)\>([\s\S]*?)\</amp\-video\>|i';
      preg_match_all( $regexp, $match[2], $mchs, PREG_SET_ORDER );
      foreach ( $mchs as $mch ) {
        $non_amp = '<video' . amp_element_atts( $mch[1], ban_atts: $ban_atts ) . '>' . str_replace( [ '<noscript>', '</noscript>' ], '', ampify( '<noscript>' . $mch[2] . '</noscript>' )[0] ) . '</video>';
        $fix = str_replace( $mch[0], $non_amp, $fix );
      }
    }
    
    $content[0] = str_replace( $match[0], $fix, $content[0] );
  }
  
  if ( str_contains( $content[0], '<amp-video' ) ) {
    // Required extension script
    $content[1] .= '<script async src="https://cdn.ampproject.org/v0/amp-video-0.1.js" custom-element="amp-video"></script>';
  }
  
  return $content;
}

/**
 * Convert iframe tags
 * @param array $content
 * @return array
 */
function amp_convert_iframe_tags( array $content ): array {
  // Required attributes
  $atts[ 'height' ] = '300';
  $atts[ 'width'  ] = '300';
  $atts[ 'layout'  ] = 'responsive';
  $atts[ 'sandbox'  ] = 'allow-scripts allow-same-origin';
  
  // Convert non-amp tags
  $regexp = '|\<iframe\b([\s\S]*?)\>([\s\S]*?)\</iframe\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $the_atts = amp_element_atts( $match[1], $atts, $all );
    $amp = '<amp-iframe' . $the_atts . '>' . ampify( $match[2] )[0] . amp_placeholder_image() . '</amp-iframe>';
    if ( ! isset( $all[ 'src' ] ) || empty( $all[ 'src' ] ) ) $amp = '';
    $content[0] = str_replace( $match[0], $amp, $content[0] );
  }
  
  // Fix tags inside noscript tag
  $ban_atts = [ 'layout', 'sandbox' ];
  $regexp = '|\<noscript\b([\s\S]*?)\>([\s\S]*?)\</noscript\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $fix = $match[0];
    if ( str_contains( $match[2], '<amp-iframe' ) ) {
      $regexp = '|\<amp\-iframe\b([\s\S]*?)\>([\s\S]*?)\</amp\-iframe\>|i';
      preg_match_all( $regexp, $match[2], $mchs, PREG_SET_ORDER );
      foreach ( $mchs as $mch ) {
        $non_amp = '<iframe' . amp_element_atts( $mch[1], ban_atts: $ban_atts ) . '>' . str_replace( [ '<noscript>', '</noscript>' ], '', ampify( '<noscript>' . $mch[2] . '</noscript>' )[0] ) . '</iframe>';
        $fix = str_replace( $mch[0], $non_amp, $fix );
      }
    }
    
    $content[0] = str_replace( $match[0], $fix, $content[0] );
  }
  
  if ( str_contains( $content[0], '<amp-iframe' ) ) {
    // Required extension script
    $content[1] .= '<script async src="https://cdn.ampproject.org/v0/amp-iframe-0.1.js" custom-element="amp-iframe"></script>';
  }
  
  return $content;
}

/**
 * Convert form tags
 * @param array $content
 * @return array
 */
function amp_convert_form_tags( array $content ): array {
  // Required attributes
  $atts[ 'method' ] = 'get';
  $atts[ 'target' ] = '_top';
  
  // Convert non-amp compatible tags
  $regexp = '|\<form\b([\s\S]*?)\>([\s\S]*?)\</form\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $the_atts = amp_element_atts( $match[1], $atts, $all );
    if ( 'get' === $all[ 'method' ] ) {
      if ( ! isset( $all[ 'action' ] ) || empty( $all[ 'action' ] ) ) {
        $amp = '';
      } else {
        $amp = '<form' . $the_atts . '>' . ampify( $match[2] )[0] . '</form>';
      }
    } else {
      $amp = '<form' . $the_atts . '>' . ampify( $match[2] )[0] . '</form>';
      if ( ! isset( $all[ 'action-xhr' ] ) || empty( $all[ 'src' ] ) ) $amp = '';
    }
    
    $content[0] = str_replace( $match[0], $amp, $content[0] );
  }
  
  if ( str_contains( $content[0], '<form' ) ) {
    // Required extension script
    $content[1] .= '<script async src="https://cdn.ampproject.org/v0/amp-form-0.1.js" custom-element="amp-form"></script>';
  }
  
  return $content;
}

/**
 * Apply custom converters
 * @param array $content
 * @return array
 */
function amp_custom_converters( array $content ): array {
  global $App;
  return $App->get_filter( $content, 'amp:convert' );
}

/**
 * Strip illegal tags
 * @param array $content
 * @return array
 */
function amp_strip_tags( array $content ): array {
  // Remove "base" tags
  $regexp = '|\<base\b([\s\S]*?)\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content[0] = str_replace( $match[0], '', $content[0] );
  }
  
  // Remove "frame" & "frameset" tags
  $regexp = '|\<(?<tag>frame(set)?)\b([\s\S]*?)\>(([\s\S]*?)\<\/(?P=tag)\>)?|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content[0] = str_replace( $match[0], '', $content[0] );
  }
  
  // Remove "object" tags
  $regexp = '|\<object\b([\s\S]*?)\>([\s\S]*?)\<\/object\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content[0] = str_replace( $match[0], '', $content[0] );
  }
  
  // Remove "param" tags
  $regexp = '|\<param\b([\s\S]*?)\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content[0] = str_replace( $match[0], '', $content[0] );
  }
  
  // Remove "applet" tags
  $regexp = '|\<applet\b([\s\S]*?)\>([\s\S]*?)\<\/applet\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content[0] = str_replace( $match[0], '', $content[0] );
  }
  
  // Remove "embed" tags
  $regexp = '|\<embed\b([\s\S]*?)\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content[0] = str_replace( $match[0], '', $content[0] );
  }
  
  // Remove "style" tags
  $regexp = '|\<style\b([\s\S]*?)\>([\s\S]*?)\<\/style\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content[0] = str_replace( $match[0], '', $content[0] );
  }
  
  // Remove "script" tags
  $regexp = '|\<script\b([\s\S]*?)\>([\s\S]*?)\<\/script\>|i';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content[0] = str_replace( $match[0], '', $content[0] );
  }
  
  // Remove comments
  $regexp = '|\<\!\-\-[\s\S]*?\-\-\>|';
  preg_match_all( $regexp, $content[0], $matches, PREG_SET_ORDER );
  foreach ( $matches as $match ) {
    $content[0] = str_replace( $match[0], '', $content[0] );
  }
  
  $content[0] = trim( $content[0] );
  return $content;
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
    
    $value = htmlspecialchars( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false );
    $str .= sprintf( ' %s=%3$s%s%3$s', $index, $value, '"' );
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
?>
