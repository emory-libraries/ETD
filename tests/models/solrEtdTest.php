<?

require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
    require_once('models/esdPerson.php');
require_once('models/solrEtd.php');

class SolrEtdTest  extends ControllerTestCase{

  private $schools_cfg;
  private $person;
  private $user;
  private $solrResponse;
  private $solrEtd;

  function setUp() {
    $this->schools_cfg = Zend_Registry::get("schools-config");

    //Solr Response
    $this->solrResponse = array(
                    "PID" => "emory:19qdg",
                    "author" => "Alcaraz, Ana Angelica",
                    "dateIssued"=> "20090531",
                    "document_type" => "Dissertation",
                    "embargo_duration" => "6 months",
                    "program" => "Chemistry",
                    "program_id" => "chemistry",
                    "status" => "published",
                    "timestamp" => "2010-02-08T17:46:57.137Z",
                    "title" => "Computational Studies of Ligand Protein Interactions Part I",
                    "year" => "2009",
                    "committee" => Array ("Liotta, Dennis C", "Snyder, James P"),
                    "contentModel" => Array ("emory-control:ETD-1.0"),
                    "subject" => Array ("Chemistry, Organic"),
                    "keyword" => Array ("T-Taxol", "Conformation"),
                    "advisor" => Array ("Liotta, Dennis C", "Snyder, James P"),
                    "collection" => Array ("emory-control:ETD-collection", "emory-control:ETD-GradSchool-collection"),
                    "score" => "0.09043152",
                    "school_config" => array
                        (
                            "_index:protected" => "0",
                            "_count:protected" => "7",
                            "_data:protected" => array
                                (
                                    "label" => "Graduate School",
                                    "acl_id" => "grad",
                                    "db_id" => "GSAS",
                                    "fedora_collection" => "emory-control:ETD-GradSchool-collection",
                                    "degrees" => array
                                        (
                                            "_index:protected" => "0",
                                            "_count:protected" => "1",
                                            "_data:protected" => array
                                                (
                                                    "degree" => array
                                                        (
                                                            "_index:protected" => "0",
                                                            "_count:protected" => "3",
                                                            "_data:protected" => Array
                                                                (
                                                                     "PhD",
                                                                     "MA"
                                                                )

                                                        )

                                                )

                                        ),

                                    "admin" => array
                                        (
                                            "_index:protected" => "0",
                                            "_count:protected" => "2",
                                            "_data:protected" => array
                                                (
                                                    "department" => "GRS: Student Development",
                                                    "netid" => "gradqew"
                                                )

                                        ),

                                    "submission_fields" => array
                                        (
                                            "_index:protected" => "0",
                                            "_count:protected" => "1",
                                            "_data:protected" => Array
                                                (
                                                    "required" => array
                                                        (
                                                            "_index:protected" => "0",
                                                            "_count:protected" => "19",
                                                            "_data:protected" => Array
                                                                (
                                                                    "title",
                                                                    "author",
                                                                    "program",
                                                                    "chair",
                                                                    "committee members",
                                                                    "researchfields",
                                                                    "keywords",
                                                                    "degree",
                                                                    "language",
                                                                    "abstract",
                                                                    "table of contents",
                                                                    "embargo request",
                                                                    "submission agreement",
                                                                    "send to ProQuest",
                                                                    "copyright",
                                                                    "name",
                                                                    "email",
                                                                    "permanent email",
                                                                    "permanent address"
                                                                )


                                                        )

                                                )


                                        )

                                )


                        )
                  );




    //test user
    $this->person = new esdPerson();
    $this->user = $this->person->getTestPerson();

  }

  function tearDown() {
    $this->user = null;
    $this->person = null;
  }


  function testGetUserRole(){
    //Grad admin
    $this->user->role = "grad admin";
    $response = $this->solrResponse;
    $grad_coll = $this->schools_cfg->graduate_school->fedora_collection;
    $response["collection"] = array($grad_coll);
    $response = new Emory_Service_Solr_Response_Document($response);
    $this->solrEtd = new solrEtd($response);
    $result = $this->solrEtd->getUserRole($this->user);
    $this->assertEqual($result, "admin");

    //Honors admin
    $this->user->role = "honors admin";
    $response = $this->solrResponse;
    $college_coll = $this->schools_cfg->emory_college->fedora_collection;
    $response["collection"] = array($college_coll);
    $response = new Emory_Service_Solr_Response_Document($response);
    $this->solrEtd = new solrEtd($response);
    $result = $this->solrEtd->getUserRole($this->user);
    $this->assertEqual($result, "admin");

    //Candler admin
    $this->user->role = "candler admin";
    $response = $this->solrResponse;
    $candler_coll = $this->schools_cfg->candler->fedora_collection;
    $response["collection"] = array($candler_coll);
    $response = new Emory_Service_Solr_Response_Document($response);
    $this->solrEtd = new solrEtd($response);
    $result = $this->solrEtd->getUserRole($this->user);
    $this->assertEqual($result, "admin");

    //Rollins admin
    $this->user->role = "rollins admin";
    $response = $this->solrResponse;
    $rollins_coll = $this->schools_cfg->rollins->fedora_collection;
    $response["collection"] = array($rollins_coll);
    $response = new Emory_Service_Solr_Response_Document($response);
    $this->solrEtd = new solrEtd($response);
    $result = $this->solrEtd->getUserRole($this->user);
    $this->assertEqual($result, "admin");;

    //Admin
    $this->user->role = "admin";
    $response = $this->solrResponse;
    $candler_coll = $this->schools_cfg->candler->fedora_collection;
    $response["collection"] = array($candler_coll);  //does not matter for admin role
    $response = new Emory_Service_Solr_Response_Document($response);
    $this->solrEtd = new solrEtd($response);
    $result = $this->solrEtd->getUserRole($this->user);
    $this->assertEqual($result, "admin");

    //Superuser
    $this->user->role = "superuser";
    $response = $this->solrResponse;
    $candler_coll = $this->schools_cfg->candler->fedora_collection;
    $response["collection"] = array($candler_coll);  //does not matter for superuser role
    $response = new Emory_Service_Solr_Response_Document($response);
    $this->solrEtd = new solrEtd($response);
    $result = $this->solrEtd->getUserRole($this->user);
    $this->assertEqual($result, "superuser");


    //Grad Coord
    //$this->user->netid = "gradqew";
    $this->user->role = "staff";
    $this->user->grad_coord = "Chemistry";
    $response = $this->solrResponse;
    $candler_coll = $this->schools_cfg->candler->fedora_collection;
    $response["collection"] = array($candler_coll);  //does not matter for superuser role
    $response = new Emory_Service_Solr_Response_Document($response);
    $this->solrEtd = new solrEtd($response);
    $result = $this->solrEtd->getUserRole($this->user);
    $this->assertEqual($result, "program coordinator");

    //Guest
    $this->user = null;
    $response = $this->solrResponse;
    $candler_coll = $this->schools_cfg->candler->fedora_collection;
    $response["collection"] = array($candler_coll);  //does not matter for guest role
    $response = new Emory_Service_Solr_Response_Document($response);
    $this->solrEtd = new solrEtd($response);
    $result = $this->solrEtd->getUserRole($this->user);
    $this->assertEqual($result, "guest");


  }


}

runtest(new SolrEtdTest());

?>
