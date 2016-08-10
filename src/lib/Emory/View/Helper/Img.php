<?

/**
 * helper for outputting image tags includes with site-relative path
 *
 * @category EmoryZF
 * @package Emory_View
 * @subpackage Emory_View_Helpers
 */
class Emory_View_Helper_Img
{
  public $view;
  protected static $_baseurl = null;
  protected static $_cache = null;
  /**
   * Constructor
   */
  public function __construct()
  {
    if (null === self::$_baseurl) {
      $url = Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl();
      $root = '/' . trim($url, '/');
      if ('/' == $root) {
        $root = '';
      }
      self::$_baseurl = $root . '/';
    }
  }
  /**
   * Output the <img> tag
   *
   * @param string $path
   * @param array $params
   * @return string
   */
  public function img($path, $params = array())
  {
    $plist = array();
    $paramstr = null;
    $imagepath = self::$_baseurl . ltrim($path, '/');
    if (!isset(self::$_cache[$path])) {
      $document_root = dirname($_SERVER["SCRIPT_FILENAME"]);
      /** NOTE:
          Previous version did not correctly handle paths for
          public_html user directories;
          using directory from script filename and path without base_url
          to check if image file exists.
       */
      // $document_root = $_SERVER['DOCUMENT_ROOT'];
      self::$_cache[$path] = file_exists(realpath($document_root . '/' . $path));
    }
    if (!isset($params['alt'])) {
      $params['alt'] = '';
    }
    foreach ($params as $param => $value) {
      $plist[] = $param . '="' . $this->view->escape($value) . '"';
    }
    $paramstr = ' ' . join(' ', $plist);
    return '<img src="' .
        ((self::$_cache[$path])
          ? self::$_baseurl . ltrim($path, '/')
          : 'data:image/gif;base64,R0lGODlhFAAUAIAAAAAAAP///yH5BAAAAAAALAAAAAAUABQAAAI5jI+pywv4DJiMyovTi1srHnTQd1BRSaKh6rHT2cTyHJqnVcPcDWZgJ0oBV7sb5jc6KldHUytHi0oLADs=') .
        '"' . $paramstr . ' />';
  }
  /**
   * Set the view object
   *
   * @param Zend_View_Interface $view
   * @return void
   */
  public function setView(Zend_View_Interface $view)
  {
    $this->view = $view;
  }
}
