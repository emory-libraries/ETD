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
    foreach ($this as $school) {
      // check for user netids explicitly specified
      // -- handle single netid or multiple
      if (isset($school->admin->netid) &&
	  // single netid in config file
	  ($school->admin->netid != '' && $user->netid == $school->admin->netid)
	  || (is_object($school->admin->netid) &&
	      // multiple netids in config file
	      in_array($user->netid, $school->admin->netid->toArray()))) {
	return $school->acl_id;
      }
      
      // check if department name OR code is configured & matches user department name/code
      if ( !empty($school->admin->department) && 
	   ($user->department == $school->admin->department) ||
	   (!empty($school->admin->department_code) && 
	    $school->admin->department_code == $user->department_code)) {

	// if job title specified, do a case-insensitive string compare
	if ( !empty($school->admin->job_title)) {
	  if (stristr($user->directory_title, $school->admin->job_title)) {
	    return $school->acl_id;
	  }
	} else {
	  // job title not specified & department name/code match found
	  return $school->acl_id;
	}
      }
    }
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
