<?php

// Fields with their descriptions
        global $all_report_fields;        
        $all_report_fields = array(
        array(
          'field' => 'label', 
          'label' => 'Label', 
          'description' => 'Fedora object label (set from initial title)',
          'available' => false
        ),
        array(
          'field' => 'ownerId', 
          'label' => 'OwnerId', 
          'description' => 'netid of user who owns the object (author',
          'available' => false
        ),
        array(
          'field' => 'state', 
          'label' => 'State', 
          'description' => 'Fedora object state indicating whether the objest is (A)ctive), (I)nactive), (D)eleted',
          'available' => false
        ),
        array(
          'field' => 'author', 
          'label' => 'Author', 
          'description' => 'Full name of the ETD author as lastname, firstname',
          'available' => true
        ),
        array(
          'field' => 'date_embargoedUntil', 
          'label' => 'DateEmbargoedUntil', 
          'description' => 'Date embargo expires in YYYYMMDD format; only available for published records',
          'available' => false
        ),
        array(
          'field' => 'dateIssued', 
          'label' => 'DateIssued', 
          'description' => 'Date published in YYYYMMDD format; only available for published records',
          'available' => true
        ),
        array(
          'field' => 'degree_level', 
          'label' => 'DegreeLevel', 
          'description' => 'Level of the degree, e.g. bachelors, masters, doctoral',
          'available' => false
        ),
        array(
          'field' => 'degree_name', 
          'label' => 'DegreeName', 
          'description' => 'Actual degree received, e.g. PhD, MA, BA',
          'available' => true
        ),
        array(
          'field' => 'document_type', 
          'label' => 'DocumentType', 
          'description' => 'Genre of the record, i.e. dissertation or thesis',
          'available' => true
        ),
        array(
          'field' => 'registering_copyright', 
          'label' => 'RegisteringCopyright', 
          'description' => 'yes/no flag indicating that the user is registering copyright',
          'available' => false
        ),
        array(
          'field' => 'embargo_duration', 
          'label' => 'EmbargoDuration', 
          'description' => 'Duration of embargo',
          'available' => true
        ),
        array(
          'field' => 'embargo_notice', 
          'label' => 'EmbargoNotice', 
          'description' => 'If set, indicates when 60 day embargo expiration was sent (e.g., "sent 2013-04-04")',
          'available' => false
        ),
        array(
          'field' => 'embargo_requested', 
          'label' => 'EmbargoRequested', 
          'description' => 'yes/no flag indicating whether the user requested an embargo',
          'available' => false
        ),
        array(
          'field' => 'language', 
          'label' => 'Language', 
          'description' => 'Language in which the thesis is written',
          'available' => true
        ),
        array(
          'field' => 'num_pages', 
          'label' => 'NumPages', 
          'description' => 'Number of pages in the submitted PDF',
          'available' => false
        ),
        array(
          'field' => 'program', 
          'label' => 'Program', 
          'description' => 'Name of program ETD is associated',
          'available' => true
        ),
        array(
          'field' => 'program_id', 
          'label' => 'ProgramId', 
          'description' => 'ETD code for the associated program',
          'available' => true
        ),
        array(
          'field' => 'subfield', 
          'label' => 'Subfield', 
          'description' => 'Name of sub-divison of program ETD is associated',
          'available' => true
        ),
        array(
          'field' => 'subfield_id', 
          'label' => 'SubfieldId', 
          'description' => 'Code of sub-divison of program ETD is associated',
          'available' => true
        ),
        array(
          'field' => 'subject', 
          'label' => 'Subject', 
          'description' => 'Text list of subjects / research fields',
          'available' => true
        ),
        array(
          'field' => 'year', 
          'label' => 'Year', 
          'description' => 'Year the record was published (only available for records that have been published)',
          'available' => true
        ),
        array(
          'field' => 'abstract', 
          'label' => 'Abstract', 
          'description' => 'Text of the abstract, without any formatting. (May increase report generation time)',
          'available' => true
        ),
        array(
          'field' => 'tableOfContents', 
          'label' => 'TableOfContents', 
          'description' => 'Text of the table of contents, without any formatting',
          'available' => false
        ),
        array(
          'field' => 'title', 
          'label' => 'Title', 
          'description' => 'Title of the record without any formatting',
          'available' => true
        ),
        array(
          'field' => 'advisor', 
          'label' => 'Advisor', 
          'description' => 'List of full names for all committee chairs/advisors in lastname, first format',
          'available' => true
        ),
        array(
          'field' => 'advisor_id', 
          'label' => 'AdvisorId', 
          'description' => 'List of netids for committee chair/advisor (Emory faculty only)',
          'available' => false
        ),
        array(
          'field' => 'committee', 
          'label' => 'Committee', 
          'description' => 'List of full names for all committee members in lastname, first format',
          'available' => true
        ),
        array(
          'field' => 'committee_id', 
          'label' => 'CommitteeId', 
          'description' => 'List of netids for committee members on the record (Emory faculty only)',
          'available' => false
        ),
        array(
          'field' => 'keyword', 
          'label' => 'Keyword', 
          'description' => 'Text keyword terms entered by author',
          'available' => true
        ),
        array(
          'field' => 'status', 
          'label' => 'Status', 
          'description' => 'Status of the record within the ETD system (draft, submitted, approved, reviewed, published, inactive)',
          'available' => true
        ),
        array(
          'field' => 'partneringagencies', 
          'label' => 'PartneringAgencies', 
          'description' => 'Text names of Rollins partnering agencies',
          'available' => true
        ),
        array(
          'field' => 'collection', 
          'label' => 'School', 
          'description' => 'Fedora identifier for the collection this ETD belongs to; use to associated ETD records with schools (i.e., Graduate School, Theology, Rollins, etc)',
          'available' => true
        ),
        );
