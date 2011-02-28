<?
/**
 * view helper to generate edit link for an etd field
 * @category Etd
 * @package Etd_View_Helpers
 */

class Zend_View_Helper_EditFieldLink {
  public $view;

  /**
   * determine which page a field should be edited on
   * - NOTE: url is generated relative to current page, which should include etd pid
   * @param string $field
   * @return string url for page field should be edited on
   */ 
  public function EditFieldLink($field) {
    switch ($field) {
    case "author":
    case "keywords":
    case "degree":
    case "language":
      $action = "record"; break;
      
    case "chair":
    case "committee members":
      $action = "faculty"; break;
      
    case "researchfields":	 $action = "researchfield"; break;
    case "table of contents":   $action = "contents"; break;
      
    case "send to ProQuest":
    case "copyright":
    case "embargo request":
    case "submission agreement":
      $action = "rights"; break;
      
    case "title":
    case "program":
    case "abstract":
    default:
      $action = $field;
    }
    
    return $this->view->url(array('controller' => 'edit', 'action' => $action));
  }

  public function setView(Zend_View_Interface $view) {
    $this->view = $view;
  }
}