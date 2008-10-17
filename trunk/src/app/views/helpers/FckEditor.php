<?

require_once("js/FCKeditor/fckeditor.php");

class Zend_View_Helper_FckEditor { 
  public $view;

  /**
   * generate an FCKeditor text box input
   *
   * @param string $title name of the form field
   * @param string $value current content for the edit field
   * @param array  $options configuration options (config, toolbar, height)
   */
  public function FckEditor($title, $value, $options) {
    $oFCKeditor = new FCKeditor($title);
    $oFCKeditor->BasePath = (string)$this->view->linkTo("js/FCKeditor/");
    $oFCKeditor->Value = $value;
    if (isset($options["config"])) $oFCKeditor->Config["CustomConfigurationsPath"] = $options["config"];
    if (isset($options["toolbar"])) $oFCKeditor->ToolbarSet = $options["toolbar"];
    if (isset($options["height"])) $oFCKeditor->Height = $options["height"];
    if (isset($options["css"]))    $oFCKeditor->Config["EditorAreaCSS"] = $options["css"];
    if (isset($options["BodyId"])) $oFCKeditor->Config["BodyId"] = $options["BodyId"];

    return $oFCKeditor->Create();
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
