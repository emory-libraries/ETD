<?
/**
 * view helper for displaying bytes in human-readable units
 * 
 * @category EmoryZF
 * @package Emory_View
 * @subpackage Emory_View_Helpers
 */
class Emory_View_Helper_ReadableSize {

  /**
   * convert size in bytes to a more human-readable size with units
   * @param int $size
   * @return string
   */
  public function readableSize($size) {
    $bytes = array('B','KB','MB','GB','TB');
    foreach($bytes as $val) {
      if($size > 1024){
	$size = $size / 1024;
      }else{
	break;
      }
    }
    return round($size, 1)." ".$val;
  } 
  
}

