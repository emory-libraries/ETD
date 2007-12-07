<?

// fixme: how to make xforms namespace configurable?

class Zend_View_Helper_xformTextarea {

  public function xformTextarea($label, $nodeset) {
    // make sure nodeset expression uses single quotes (nested inside double quotes)
    $nodeset = str_replace('"', "'", $nodeset);
    
    $xform = '<xf:textarea nodeset="' . $nodeset . '"> 
  <xf:label>' . $label . '</xf:label>
</xf:textarea>';
    return $xform;
  }

}

?>