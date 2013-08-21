<?php

// Fields with their descriptions
        global $all_report_fields;        
        $all_report_fields = array(
        array(
          'value' => 'label:', 
          'label' => 'Label', 
          'description' => 'Fedora object label (set from initial title)',
          'available' => false
        ),
        array(
          'value' => 'ownerId:', 
          'label' => 'OwnerId', 
          'description' => 'netid of user who owns the object (author',
          'available' => false
        ),
        array(
          'value' => 'state:', 
          'label' => 'State', 
          'description' => 'Fedora object state indicating whether the objest is Active, Inactive, Deleted',
          'available' => false
        ),
        array(
          'value' => 'author:', 
          'label' => 'Author', 
          'description' => 'Full name of the ETD author as lastname, firstname',
          'available' => true
        ),
        array(
          'value' => 'date_embargoedUntil:', 
          'label' => 'DateEmbargoedUntil', 
          'description' => 'Date embargo expires in YYYYMMDD format; only available for published records',
          'available' => false
        ),
        array(
          'value' => 'dateIssued:', 
          'label' => 'DateIssued', 
          'description' => 'Date published in YYYYMMDD format; only available for published records',
          'available' => true
        ),
        array(
          'value' => 'degree_level:', 
          'label' => 'DegreeLevel', 
          'description' => 'Level of the degree, e.g. bachelors, masters, doctoral',
          'available' => false
        ),
        array(
          'value' => 'degree_name:', 
          'label' => 'DegreeName:', 
          'description' => 'Actual degree received, e.g. PhD, MA, BA',
          'available' => true
        ),
        array(
          'value' => 'document_type:', 
          'label' => 'DocumentType:', 
          'description' => 'Genre of the record, i.e. dissertation or thesis',
          'available' => true
        ),
        array(
          'value' => 'registering_copyright:', 
          'label' => 'RegisteringCopyright', 
          'description' => 'yes/no flag indicating that the user is registering copyright',
          'available' => false
        ),
        array(
          'value' => 'embargo_duration:', 
          'label' => 'EmbargoDuration', 
          'description' => 'Duration of embargo',
          'available' => true
        ),
        array(
          'value' => 'embargo_notice:', 
          'label' => 'EmbargoNotice', 
          'description' => 'If set, indicates when 60 day embargo expiration was sent (e.g., "sent 2013-04-04")',
          'available' => false
        ),
        array(
          'value' => 'embargo_requested:', 
          'label' => 'EmbargoRequested', 
          'description' => 'yes/no flag indicating whether the user requested an embargo',
          'available' => false
        ),
        array(
          'value' => 'language:', 
          'label' => 'Language', 
          'description' => 'Language in which the thesis is written',
          'available' => true
        ),
        array(
          'value' => 'num_pages:', 
          'label' => 'NumPages', 
          'description' => 'Number of pages in the submitted PDF',
          'available' => false
        ),
        array(
          'value' => 'program:', 
          'label' => 'Program', 
          'description' => 'Name of program ETD is associated',
          'available' => true
        ),
        array(
          'value' => 'program_id:', 
          'label' => 'ProgramId', 
          'description' => 'ETD code for the associated program',
          'available' => true
        ),
        array(
          'value' => 'subfield:', 
          'label' => 'Subfield', 
          'description' => 'Name of sub-divison of program ETD is associated',
          'available' => true
        ),
        array(
          'value' => 'subfield_id:', 
          'label' => 'SubfieldId', 
          'description' => 'Code of sub-divison of program ETD is associated',
          'available' => true
        ),
        array(
          'value' => 'subject:', 
          'label' => 'Subject', 
          'description' => 'Text list of subjects / research fields',
          'available' => true
        ),
        array(
          'value' => 'year:', 
          'label' => 'Year', 
          'description' => 'Year the record was published (only available for records that have been published)',
          'available' => true
        ),
        array(
          'value' => 'abstract:', 
          'label' => 'Abstract', 
          'description' => 'Text of the abstract, without any formatting',
          'available' => false
        ),
        array(
          'value' => 'tableOfContents:', 
          'label' => 'TableOfContents', 
          'description' => 'Text of the table of contents, without any formatting',
          'available' => false
        ),
        array(
          'value' => 'title:', 
          'label' => 'Title', 
          'description' => 'Title of the record without any formatting',
          'available' => true
        ),
        array(
          'value' => 'advisor:', 
          'label' => 'Advisor', 
          'description' => 'List of full names for all committee chairs/advisors in lastname, first format',
          'available' => true
        ),
        array(
          'value' => 'advisor_id:', 
          'label' => 'AdvisorId', 
          'description' => 'List of netids for committee chair/advisor (Emory faculty only)',
          'available' => false
        ),
        array(
          'value' => 'committee:', 
          'label' => 'Committee', 
          'description' => 'List of full names for all committee members in lastname, first format',
          'available' => true
        ),
        array(
          'value' => 'committee_id:', 
          'label' => 'CommitteeId', 
          'description' => 'List of netids for committee members on the record (Emory faculty only)',
          'available' => false
        ),
        array(
          'value' => 'keyword:', 
          'label' => 'Keyword', 
          'description' => 'Text keyword terms entered by author',
          'available' => true
        ),
        array(
          'value' => 'status:', 
          'label' => 'Status', 
          'description' => 'Status of the record within the ETD system (draft, submitted, approved, reviewed, published, inactive)',
          'available' => true
        ),
        array(
          'value' => 'partneringagencies:', 
          'label' => 'PartneringAgencies', 
          'description' => 'Text names of Rollins partnering agencies',
          'available' => true
        ),
        array(
          'value' => 'collection:', 
          'label' => 'Collection', 
          'description' => 'Fedora identifier for the collection this ETD belongs to; use to associated ETD records with schools (i.e., Graduate School, Theology, Rollins, etc)',
          'available' => true
        ),
        );
