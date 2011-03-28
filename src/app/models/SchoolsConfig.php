<?php
/**
 * @category Etd
 * @package Etd_Models
 */

require_once("esdPerson.php");

/**
 * multi-school config object
 * can be initialized as a standard Zend_Config_Xml object, but
 * adds several functions for finding & accessing specific school data
 */
class SchoolsConfig extends Zend_Config_Xml {

  /**
   * return full label by school id/shorthand
   * @param string $id id for the school (equivalent to section tag in xml config)
   * @return string|null label as string on success, null when no match for id
   */
  public function getLabel($id) {
    if (isset($this->$id)) return $this->$id->label;
    return null;
  }

  /**
   * get school id by the id of the corresponding Fedora collection object
   * @param string $collection fedora pid for configured collection
   * @return string|null
   */
  public function getIdByFedoraCollection($collection) {
    foreach ($this as $school) {
      if ($collection == $school->fedora_collection) {
	return $this->idBySchool($school);
      }
    }
    return null;
  }

  /**
   * determine if a user is a school-specific admin, according to configured filters
   * if yes, returns school configured ACL id; otherwise returns null
   * @param esdPerson $user
   * @return string|false
   */
  public function isAdmin(esdPerson $user) {
    $etd_db = Zend_Registry::get("etd-db"); //etd util DB
    foreach ($this as $school) {
      // check for user netids explicitly specified
      // -- handle single netid or multiple
      // -- have to add additional check for admin section because all_schools do not have admin section
      if (isset($school->admin) && $this->valueInConfig($user->netid, $school->admin->netid) &&
                 $this->valueInConfig($user->department, $school->admin->department)) {
	      return $school->acl_id;
      }
      
      //FIXME: Migrate all admin logic to use db instead of config files
      
      //see if user exists in admins table for correct school when admin section is not set in config
      elseif(isset($school->acl_id) && !isset($school->admin)){
          $where = 'netid = upper(?) AND schoolid = ?';
          $bind = array(':netid' => $user->netid, ':schoolid' => $school->db_id);
          $result = $etd_db->fetchall($etd_db->select()->from("etd_admins")->where($where)->bind($bind));
          if($result) return $school->acl_id;  // at least one result for user + school
      }
    }
  }


  /**
   * checks to see if a value is in the config object
   * Will handle mutiple values and single entries
   * @param string $value - value you are looking for
   * @param $entry - entry in the config file, should be either a string or Zend_Config
   * @return boolean
   */
  function valueInConfig($value, $entry){
      //$value must be a non-empty string
      //$entry must be a non-empty string or a Zend_Config object
      if(empty($value) || !is_string($value) || empty($entry) || ( !($entry instanceof Zend_Config) && !is_string($entry))) return false;

      //has mutiple values
      if(is_object($entry)){
          if(in_array($value, $entry->toArray())) return true;
      }
      //single value
      else{
          if($value == $entry) return true;
      }

  //if you get here there is no match
  return false;
  }


  /**
   * return school section id by corresponding database id
   * @param string $db_id
   * @return string|null
   */
  public function getIdByDatabaseId($db_id) {
    foreach ($this as $school) {
      if ($school->db_id == $db_id) return $this->idBySchool($school);
    }
    return null;
  }

  
  /**
   * return school config by acl id
   * @param string $acl_id school id used in ACL, e.g. honors for honors admin
   * @return Zend_Config|null school config on success, null when no match for acl id
   */
  public function getSchoolByAclId($id) {
    foreach ($this as $school) {
      if ($school->acl_id == $id) return $school;
    }
    return null;
  }

  /**
   * return school id (section label) by the school section config object
   * - finds the section id of this school by looking for its key in the full data array
   * @return string
   */
  protected function idBySchool(Zend_Config $school) {
    return array_search($school, $this->_data);
  }
   
}
