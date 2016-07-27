<?

/**
 * view helper for generating links relative to site base url
 * 
 * @category EmoryZF
 * @package Emory_View
 * @subpackage Emory_View_Helpers
 */
class Zend_View_Helper_LinkTo
{
    protected static $baseURL = null;
    
    public function linkTo($path)
    {
        if (self::$baseURL === null)
        {
            $request = Zend_Controller_Front::getInstance()->getRequest();
            $root = '/' . trim($request->getBaseUrl(), '/');
            if ($root == '/')
            {
                $root = '';
            }
            self::$baseURL = "$root/";
        }
        return self::$baseURL . ltrim($path, '/');
    }
}
