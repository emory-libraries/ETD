<?
/**
 * helper for outputting css includes with site-relative path
 * 
 * @category EmoryZF
 * @package Emory_View
 * @subpackage Emory_View_Helpers
 */
class Emory_View_Helper_Css
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
     * Return the CSS based on the base setting for the library
     *
     */
    public function css($name)
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

        $css_path = self::$_baseurl . 'css';    

	return "<link rel='stylesheet' href='$css_path/$name' type='text/css'></link>";
    }
}