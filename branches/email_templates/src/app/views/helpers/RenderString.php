<?

class Zend_View_Helper_RenderString { 
  public $view;

  /**
   * Render the contents of a text variable as if it were a template file
   *
   * @param string $content
   */
  public function RenderString($content) {
    // get tmpdir from config?
    $tmpfile = tempnam("/tmp", "view_");
    file_put_contents($tmpfile, $content);
    $this->view->addScriptPath("/tmp");
    return $this->view->render(basename($tmpfile));    
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
