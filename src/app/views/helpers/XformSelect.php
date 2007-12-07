<?

class Zend_View_Helper_xformSelect {

  public function xformSelect($label, $nodeset, $options, $multiple = false) {
    // make sure nodeset expression uses single quotes (nested inside double quotes)
    $nodeset = str_replace('"', "'", $nodeset);

    // multiple or single select
    if ($multiple)
      $select = "select";
    else $select = "select1";
    
    $xform = '<xf:' . $select . ' ref="' . $nodeset . '">
   <xf:label>' . $label . '</xf:label>
';

    foreach ($options as $opt_value => $opt_label) {
      $xform .= "<xf:item>
  <xf:label>" . $opt_label . "</xf:label>
  <xf:value>" . $opt_value . "</xf:value>
</xf:item>
";
    }
    $xform .= "</xf:" . $select . ">";

    return $xform;
  }

}

?>