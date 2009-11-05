<?
/**
 * view helper to scale a graph value for css charts
 *
 * @category Etd
 * @package Etd_View_Helpers
 */


class Zend_View_Helper_ScaleGraphValue { 
  public $view;

  /**
   * 
   * 
   */  
  public function scaleGraphValue($num) {
    if ($num == 0) return 0;
    return ($num / $this->view->graph_max) * $this->view->graph_width;
  }

  /**
   * Set the view object
   *
   * @param Zend_View_Interface $view
   * @return void
   */
  public function setView(Zend_View_Interface $view) {
    $this->view = $view;
  }

}

?>
