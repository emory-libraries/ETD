<?php
/**
 * view helper for outputting an array as csv
 *
 * @category EmoryZF
 * @package Emory_View
 * @subpackage Emory_View_Helpers
 */

class Zend_View_Helper_PutCsvLine {
  public $view;

  /**
   * format an array of values as a single line of comma separated values
   * @param array $values
   * @return string comma-separated values
   */
  public function PutCsvLine(array $values) {
    $csv = fopen('php://temp', 'r+'); //open stream to store in memory
    fputcsv($csv, $values); //write to stream
    rewind($csv); //go back to the start
    $csv_string = stream_get_contents($csv); //put in variable
    fclose($csv);
    return $csv_string;
  }


}
