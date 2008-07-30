<?
class Zend_View_Helper_FiletypeIcon
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

    public function FiletypeIcon($mimetype) {
      if (self::$_baseurl === null) {
	$request = Zend_Controller_Front::getInstance()->getRequest();
	$root = '/' . trim($request->getBaseUrl(), '/');
	if ($root == '/'){
	  $root = '';
	}
	self::$_baseurl = "$root/";
      }

      $img_path = self::$_baseurl . 'images/filetype/';

      // split into major and minor types
      list($major, $minor) = split('/', $mimetype);
      
      switch ($major) {
      case "image": 	$icon = "image"; break;				
      case "audio": 	$icon = "audio"; break;
      case "video": 	$icon = "video"; break;
      case "text": 	$icon = "text"; break;
      case "application":
	switch ($minor) {
	case "pdf":
	case "x-pdf":
	  $icon = "pdf"; break;
	case "msword":			// docx mimetype ?
	  $icon = "word"; break;
	case "vnc.ms-excel":
	  $icon = "excel"; break;
	case "zip":
	case "x-zip":
	  $icon = "zip"; break;
	case "vnd.oasis.opendocument.text":
	  $icon = "oo-write"; break;
	case "vnd.oasis.opendocument.spreadsheet":
	  $icon = "oo-calc"; break;
	case "vnd.oasis.opendocument.presentation":
	  $icon = "oo-impress"; break;
	  // Microsoft Office 2007 formats
	case "vnd.openxmlformats-officedocument.wordprocessingml.document":
	  $icon = "word2007"; break;	// docx
	case "vnd.openxmlformats-officedocument.spreadsheetml.sheet":
	  $icon = "excel2007"; break;
	case "vnd.openxmlformats-officedocument.presentationml.presentation":
	  $icon = "powerpoint2007"; break;
	}
	break;
      }

      
      if (isset($icon))
	return "<img src='" . $img_path . $icon . ".gif' alt='$mimetype'/>";
      else
	return;		// can't determine appropriate icon
    }
}
?>

