<?php

require_once("models/foxml.php");

/**
 * Foxml Collection object that also functions as an OAI set
 *
 * @category ETD
 * @package ETD_models
 */
class FedoraCollection extends foxml {

    protected $config;
    protected $x =2;


    /**
     * pid for the content model object
     * @var string
     */
    protected $cmodel_pid = "emory-control:Collection-1.0";

    public function __construct($arg = null) {
        parent::__construct($arg);
        $this->config = Zend_Registry::get('config');

        if ($this->init_mode == "pid") {
            // check that this is the right type of object - content model is set correctly
            if ($this->rels_ext->hasModels->includes($this->fedora->risearch->pid_to_risearchpid($this->config->contentModels->etd)))
            throw new FoxmlBadContentModel("$arg does not have correct content model");
        } elseif ($this->init_mode == "template") {
            // when creating new objects, add content model relation
            $this->setContentModel($this->cmodel_pid);
        }
    }

    /**
     * set/update OAI setSpec & setName in rels-ext
     * -if set id & name are present they will be updated; otherwise they will be added
     * @param string $id  set id (setSpec for Fedora OAI provider)
     * @param string $name set name (set name/description for Fedora OAI provider)
     */
    public function setOAISetInfo($id, $name) {
      if (isset($this->rels_ext->oaiSetSpec)) {
        $this->rels_ext->oaiSetSpec = $id;
      } else {
        $this->rels_ext->addRelation("oai:setSpec", $id);
      }
      if (isset($this->rels_ext->oaiSetName)) {
        $this->rels_ext->oaiSetName = $name;
      } else {
        $this->rels_ext->addRelation("oai:setName", $name);
      }
    }

}