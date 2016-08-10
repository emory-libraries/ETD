<?
/**
 * helper for outputting javascript tags with site-relative path
 * 
 * @category EmoryZF
 * @package Emory_View
 * @subpackage Emory_View_Helpers
 */

class Emory_View_Helper_Javascript
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
     * Return the Javascript tag based on the base setting for the library
     *
     */
    public function javascript($scriptname)
    {
        if (self::$_baseurl === null)
        {
            $request = Zend_Controller_Front::getInstance()->getRequest();
            $root = '/' . trim($request->getBaseUrl(), '/');
            if ($root == '/')
            {
                $root = '';
            }
            self::$_baseurl = "$root/";
        }        

        $js_path = self::$_baseurl . 'js';    
	
	return "<script type='text/javascript' src='$js_path/$scriptname'></script>";
    }
}
