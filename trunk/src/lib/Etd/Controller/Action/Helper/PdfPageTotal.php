<?php

class Etd_Controller_Action_Helper_PdfPageTotal extends Zend_Controller_Action_Helper_Abstract {

  // run pdftohtml on pdf
  // pull out number of pages and return

  public function direct($filename) {
    return $this->pagetotal($filename);
  }

  public function pagetotal($filename) {
    $pdfinfo_output = shell_exec("pdfinfo $filename");
    $pdfinfo_output = trim($pdfinfo_output);	// trim the newline
    
    //split output of pdfinfo by line and then by field (currently only using Pages)
    $pdfi_lines = explode("\n", $pdfinfo_output);
    foreach ($pdfi_lines as $pl) {
      // FIXME: add error-checking for case when pdfinfo can't read the PDF
      list($key,$val) =  preg_split("/:\s+/", $pl, 2);
      $pdfinfo{$key} = $val;
    }

    // retrieve and return page count
    return $pdfinfo{"Pages"};
  }

}