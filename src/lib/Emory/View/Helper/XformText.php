<?

/**
 * view helper for xform text tag
 *
 * @todo FIXME: how to make xforms namespace configurable?
 *
 * @category EmoryZF
 * @package Emory_View
 * @subpackage Emory_View_Helpers
 */
class Zend_View_Helper_xformText {

  public function xformText($label, $nodeset) {
    // make sure nodeset expression uses single quotes (nested inside double quotes)
    $nodeset = str_replace('"', "'", $nodeset);

    $xform = '<xf:input ref="' . $nodeset . '">
  <xf:label>' . $label . '</xf:label>
</xf:input>';
    return $xform;
  }

}
