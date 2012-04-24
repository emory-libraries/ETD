<?php
/**
 * Use pdfinfo to get number of pages in a PDF file.
 * @category Etd
 * @package Etd_Controllers
 * @subpackage Etd_Controller_Helpers
 */

class Etd_Controller_Action_Helper_PdfPageTotal extends Zend_Controller_Action_Helper_Abstract {

  /**
   * shortcut to ingest_or_error (default helper action)
   * @see Etd_Controller_Action_Helper_PdfPageTotl::pagetotal()
   */
  public function direct($filename) {
    return $this->pagetotal($filename);
  }

  /**
   * get the number of pages in a PDF document
   * - runs pdftohtml on pdf file, pulls number of pages from the output and returns it
   * @param string $filename
   * @return string pages
   */
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