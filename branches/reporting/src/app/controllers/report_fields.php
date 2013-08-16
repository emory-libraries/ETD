<?php

// Fields with their descriptions
        global $all_report_fields;        
        $all_report_fields = array(
        array('value'=>'label:', 'label'=>'Label', 'description'=>      'Label - Fedora object label (set from initial title)'),
        array('value'=>'ownerId:', 'label'=>'OwnerId', 'description'=>    'OwnerId - netid of user who owns the object (author'),
        array('value'=>'state:', 'label'=>'State', 'description'=>      'State - Fedora object state indicating whether the objest is Active, Inactive, Deleted'),
        array('value'=>'author:', 'label'=>'Author', 'description'=>     'Author - Full name of the ETD author as lastname, firstname'),
        array('value'=>'date_embargoedUntil:', 'label'=>'DateEmbargoedUntil', 'description'=> 'DateEmbargoedUntil - Date embargo expires in YYYYMMDD format; only available for published records'),
        array('value'=>'dateIssued:', 'label'=>'DateIssued', 'description'=> 'DateIssued - Date published in YYYYMMDD format; only available for published records'),
        array('value'=>'degree_level:', 'label'=>'DegreeLevel', 'description'=>       'Degree_level - Level of the degree, e.g. bachelors, masters, doctoral'),
        array('value'=>'degree_name:', 'label'=>'DegreeName:', 'description'=>        'DegreeName - Actual degree received, e.g. PhD, MA, BA'),
        array('value'=>'document_type:', 'label'=>'DocumentType:', 'description'=>      'DocumentType - Genre of the record, i.e. dissertation or thesis'),
        array('value'=>'registering_copyright:', 'label'=>'RegisteringCopyright', 'description'=> 'RegisteringCopyright - yes/no flag indicating that the user is registering copyright'),
        array('value'=>'embargo_duration:', 'label'=>'EmbargoDuration', 'description'=>   'EmbargoDuration - Duration of embargo'),
        array('value'=>'embargo_notice:', 'label'=>'EmbargoNotice', 'description'=>     'EmbargoNotice - If set, indicates when 60 day embargo expiration was sent (e.g., "sent 2013-04-04")'),
        array('value'=>'embargo_requested:', 'label'=>'EmbargoRequested', 'description'=>  'EmbargoRequested - yes/no flag indicating whether the user requested an embargo'),
        array('value'=>'language:', 'label'=>'Language', 'description'=>   'Language - Language in which the thesis is written'),
        array('value'=>'num_pages:', 'label'=>'NumPages', 'description'=>  'NumPages - Number of pages in the submitted PDF'),
        array('value'=>'program:', 'label'=>'Program', 'description'=>    'Program - Name of program ETD is associated'),
        array('value'=>'program_id:', 'label'=>'ProgramId', 'description'=> 'ProgramId - ETD code for the associated program'),
        array('value'=>'subfield:', 'label'=>'Subfield', 'description'=>   'Subfield - Name of sub-divison of program ETD is associated'),
        array('value'=>'subfield_id:', 'label'=>'SubfieldId', 'description'=>   'SubfieldId - Code of sub-divison of program ETD is associated'),
        array('value'=>'subject:', 'label'=>'Subject', 'description'=>    'Subject - Text list of subjects / research fields'),
        array('value'=>'year:', 'label'=>'Year', 'description'=>       'Year - Year the record was published (only available for records that have been published)'),
        array('value'=>'abstract:', 'label'=>'Abstract', 'description'=>   ' Abstract - Text of the abstract, without any formatting'),
        array('value'=>'tableOfContents:', 'label'=>'TableOfContents', 'description'=>  'TableOfContents - Text of the table of contents, without any formatting'),
        array('value'=>'title:', 'label'=>'Title', 'description'=>      'Title - Title of the record without any formatting'),
        array('value'=>'advisor:', 'label'=>'Advisor', 'description'=>    'Advisor - List of full names for all committee chairs/advisors in lastname, first format'),
        array('value'=>'advisor_id:', 'label'=>'AdvisorId', 'description'=> 'AdvisorId - List of netids for committee chair/advisor (Emory faculty only)'),
        array('value'=>'committee:', 'label'=>'Committee', 'description'=>  'Committee - List of full names for all committee members in lastname, first format'),
        array('value'=>'committee_id:', 'label'=>'CommitteeId', 'description'=>  'CommitteeId - List of netids for committee members on the record (Emory faculty only)'),
        array('value'=>'keyword:', 'label'=>'Keyword', 'description'=>    'Keyword - Text keyword terms entered by author'),
        array('value'=>'status:', 'label'=>'Status', 'description'=>     'Status - Status of the record within the ETD system (draft, submitted, approved, reviewed, published, inactive)'),
        array('value'=>'partneringagencies:', 'label'=>'PartneringAgencies', 'Partneringagencies - label'=> 'Text names of Rollins partnering agencies'),
        array('value'=>'collection:', 'label'=>'Collection', 'description'=> 'Collection - Fedora identifier for the collection this ETD belongs to; use to associated ETD records with schools (i.e., Graduate School, Theology, Rollins, etc)')
        );
