<?php

class esd_test_data {
  private $db;
  public $tables;
  public $prsn_fields;
  public $persons;
  public $person_data;
  public $addr_fields;

  public function __construct() {
    $this->db = Zend_Registry::get("esd-db");
    $this->tables = array("V_ETD_PRSN", "V_ETD_PRSN_TABL", "V_ETD_PRAD");

    $this->prsn_fields = array('PRSN_F_FCLT_CRNT','PRSN_I','LOGN8NTWR_I','PRSN_C_TYPE','PRSN_N_LAST','PRSN_N_FRST','PRSN_N_MIDL','PRSN_N_FM_DTRY','PRSN_E_TITL_DTRY','DPRT_C','DPRT_N','ACCA_I','ACPR8PRMR_I','ACPL_I','ACPL_N','DEGR_I','TERM8CMPL_I','ACPL8GPCO_N','STDN_F_HONR','EMAD_N');
    
    $this->persons = array(
			   // staff person
			   array(' ',1,'PSTAFF','E','Staff','Person',' ','A. Person','Employee','1000','University Libraries',null,null,null,null,null,null,null,null,'person.staff@emory.edu'),
			   // two faculty persons with similar names
			   array('Y',2,'ENGRBS','F','Scholar','Roger','B','R Bradley','Professor','1001','English',null,null,null,null,null,null,null,null,'r.scholar@emory.edu'),
			   array('Y',3,'BSCHOLA','F','Scholar','Brad','A','Brad A','Assoc Professor','1002','SOM: Neurology',null,null,null,null,null,null,null,null,'b.scholar@emory.edu'),
			   // another faculty person
			   array('Y',5,'MTHINK','F','Thinker','Michele','D','','Professor','1003','Philosophy',null,null,null,null,null,null,null,null,'michele.thinker@emory.edu'),
			   // former faculty 
			   array('N',6,'EMIRIT','R','Emiritus','Peter','J','','Retired Professor','1009',null,null,null,null,null,null,null,null,null,null),			   
			   // student
			   array(' ',4,'MSTUDEN','B','Student','Mary','Kim',null,null,null,null,'GSAS','PHD','BBSPHD','Bioscience','PHD',null,null,'N','m.student@emory.edu'),
			   // student employee  -- *two* records in current ESD, one without info we need
			   array(' ',7,'JSTUDEN','B','Student','Joe','Bob',null,null,null,null,null,null,null,null,null,null,null,'N','j.student@emory.edu'),
			   array(' ',7,'JSTUDEN','B','Student','Joe','Bob',null,null,null,null,'GSAS','PHD','BBSPHD','Bioscience','PHD',null,null,'N','j.student@emory.edu'),

			   // grad administrator
			   array(' ',1,'GADMIN','E','Admin','Graduate',' ','','GSAS Administrator','1007','Graduate School Administration',null,null,null,null,null,null,null,null,'grad.admin@emory.edu'),
			   // grad dept. coordinator
			   array(' ',1,'DADMIN','E','Admin','Department',' ','','Department Coordinator','1008','Graduate School',null,null,null,null,null,null,"Philosophy",null,'grad.admin@emory.edu'),
               array(' ',1,'RVIEWER','E','Bob','Smith',' ','','Analyst','1009','Reports Department',null,null,null,null,null,null,"Philosophy",null,'grad.admin@emory.edu'),
               array(' ', '24883', 'VCARTER', 'E', 'Carter', 'Vincent', 'M.', 'Vincent', 'Asst Dir, Evaluation & Survey Research', '900000', 'EVP Academic Affairs & Provost', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'vcarter@emory.edu'),
               array('N', '24883', 'VCARTER', 'E', 'Carter', 'Vincent', 'M.', 'Vincent', 'Asst Dir, Evaluation & Survey Research', '832080', 'ECAS: Sociology', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'vcarter@emory.edu'),
               array('N', '24883', 'NOID', 'E', 'Carter', 'Vincent', 'M.', 'Vincent', 'Asst Dir, Evaluation & Survey Research', '900000', 'EVP Academic Affairs & Provost', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'vcarter@emory.edu'),
               array('N', '24883', 'NOID', 'E', 'Carter', 'Vincent', 'M.', 'Vincent', 'Asst Dir, Evaluation & Survey Research', '832080', 'ECAS: Sociology', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'vcarter@emory.edu'),
            );

    $this->addr_fields = array("PRSN_I","PRAD_A_LOCL_LIN1","PRAD_A_LOCL_LIN2","PRAD_A_LOCL_LIN3","PRAD_A_LOCL_CITY","PRAD_A_LOCL_STAT","PRAD_A_LOCL_ZIP","PRAD_A_LOCL_PSTL","PRAD_A_LOCL_CNTR","PRAD_A_LOCL_TLPH","PRAD_A_STDN_LIN1","PRAD_A_STDN_LIN2","PRAD_A_STDN_LIN3","PRAD_A_STDN_CITY","PRAD_A_STDN_STAT","PRAD_A_STDN_ZIP","PRAD_A_STDN_PSTL","PRAD_A_STDN_CNTR","PRAD_A_STDN_TLPH");
    
    $this->addresses = array(
			 array(4,'5544 Rambling Road','Apt. 3C','c/o Spot','Atlanta','GA','30432',null,'USA','4041234566','346 Lamplight Trail','Suite #2','Rm. 323','Cleveland','OH','99304',null,"CA",'545234566'),
			 );
  }

  public function loadAll() {
    // combine field names as array keys, data for values to generate format needed for db insert
    foreach ($this->persons as $data) {
      $this->db->insert("V_ETD_PRSN_TABL", array_combine($this->prsn_fields, $data));
    }
    foreach ($this->addresses as $data) {
      $this->db->insert("V_ETD_PRAD", array_combine($this->addr_fields, $data));
    }
  }

  public function cleanUp() {
    foreach ($this->tables as $table) {
      $this->db->query("truncate table $table");
    }

  }
}


?>