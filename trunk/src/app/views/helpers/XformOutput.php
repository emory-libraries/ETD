<?

class Zend_View_Helper_xformOutput {

  public function xformOutput($label, $nodeset) {
    $nodeset = str_replace('"', "'", $nodeset);

    $xform = '<xf:output nodeset="' . $nodeset . '">
      <xf:label>' . $label . '</xf:label>
   </xf:output>';
    return $xform;
  }
}

?>