<?

// fixme: how to make xforms namespace configurable?

class Zend_View_Helper_xformText {

  public function xformText($label, $nodeset) {
    // make sure nodeset expression uses single quotes (nested inside double quotes)
    $nodeset = str_replace('"', "'", $nodeset);
    
    $xform = '<xf:input nodeset="' . $nodeset . '"> 
  <xf:label>' . $label . '</xf:label>
</xf:input>';
    return $xform;
  }

}

?>