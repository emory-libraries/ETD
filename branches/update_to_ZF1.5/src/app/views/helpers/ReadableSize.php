<?
class Zend_View_Helper_ReadableSize {

  /* convert size in bytes to a more human-readable size with units */
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

