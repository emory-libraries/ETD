<?

/**
 * extending the Zend Http Client to override one behavior:
 * when it generates parameters, if there are multiple values for one name,
 * it changes the parameter name and adds [] on then end
 *
 * @category EmoryZF
 * @package Emory_Http
 */
class Emory_Http_Client extends Zend_Http_Client {

  protected function _getParametersRecursive($parray, $urlencode = false)
    {
        if (! is_array($parray)) return $parray;
        $parameters = array();

        foreach ($parray as $name => $value) {
            if ($urlencode) $name = urlencode($name);

            // If $value is an array, iterate over it
            if (is_array($value)) {
        /** this commented out line is the only difference from parent function **/
        //	      $name .= ($urlencode ? '%5B%5D' : '[]');
                foreach ($value as $subval) {
                    if ($urlencode) $subval = urlencode($subval);
                    $parameters[] = array($name, $subval);
                }
            } else {
                if ($urlencode) $value = urlencode($value);
                $parameters[] = array($name, $value);
            }
        }
        return $parameters;
    }

}
