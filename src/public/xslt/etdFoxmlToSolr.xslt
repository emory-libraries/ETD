<?xml version="1.0" encoding="UTF-8"?> 
<!-- $Id: demoFoxmlToLucene.xslt 5734 2006-11-28 11:20:15Z gertsp $ -->
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"   
  xmlns:exts="xalan://dk.defxws.fedoragsearch.server.GenericOperationsImpl"
  xmlns:foxml="info:fedora/fedora-system:def/foxml#"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:mods="http://www.loc.gov/mods/v3"
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" 
  xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
  xmlns:rel="info:fedora/fedora-system:def/relations-external#"
  xmlns:fedora-model="info:fedora/fedora-system:def/model#"
  xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
  xmlns:etd="http://www.ndltd.org/standards/metadata/etdms/1.0/"
  exclude-result-prefixes="exts xsl foxml dc mods rdf rdfs rel fedora-model oai_dc etd"
  >
  <xsl:output method="xml" indent="yes" encoding="UTF-8"/>

  <!--
       This xslt stylesheet generates the Solr doc element consisting of field elements
       from a FOXML record. The PID field is mandatory.
       Options for tailoring:
       - generation of fields from other XML metadata streams than DC
       - generation of fields from other datastream types than XML
       - from datastream by ID, text fetched, if mimetype can be handled
       currently the mimetypes text/plain, text/xml, text/html, application/pdf can be handled.
       -->

  <xsl:param name="REPOSITORYNAME" select="FedoraRepository"/>
  <xsl:param name="FEDORAUSER" select="'fedoraAdmin'"/>
  <xsl:param name="FEDORAPASS" select="'fedoraAdmin'"/>
  <xsl:param name="REPOSITORYURL" select = "'http://localhost:8080/fedora/'" />
  <xsl:param name="FEDORASOAP" select="fedora"/>
  <xsl:param name="TRUSTSTOREPATH" select="FedoraRepository"/>
  <xsl:param name="TRUSTSTOREPASS" select="FedoraRepository"/>
  <xsl:variable name="PID" select="/foxml:digitalObject/@PID"/>
  <xsl:variable name="docBoost" select="1.4*2.5"/> <!-- or any other calculation, default boost is 1.0 -->
  
<!-- descend into managed xml datastreams -->
<!-- Using API-A-LITE format because REST-API format does not work with our policies -->
<!-- This is a possible bug see: https://jira.duraspace.org/browse/FCREPO-703 -->
  <xsl:template match='foxml:datastream[@CONTROL_GROUP="M"][foxml:datastreamVersion[last()]/@MIMETYPE = "text/xml"
    or foxml:datastreamVersion[last()]/@MIMETYPE = "application/rdf+xml"]'>
    <xsl:if test="$PID !=''">
        <xsl:variable name='dsurl' select='concat($REPOSITORYURL, "get/", $PID,
                    "/", @ID)'/>
    <xsl:apply-templates select='document($dsurl)/*'/>
   </xsl:if>
  </xsl:template>


  <!-- used for inline datastreams -->
  <xsl:template match='foxml:datastream[@CONTROL_GROUP="X"]'>
          <xsl:apply-templates select='foxml:datastreamVersion[last()]/foxml:xmlContent/*'/>
  </xsl:template>

  <xsl:template match="/">
  <xsl:comment><xsl:value-of select="$REPOSITORYURL" /> </xsl:comment>
  <xsl:comment>PID is:<xsl:value-of select="$PID"/></xsl:comment>
  <!-- pids for all current content models (comma-separated list) -->

  <xsl:variable name="contentModel">
   <xsl:if test="$PID !=''">
     <!-- query risearch to determine content model -->
     <xsl:variable name="lt">%3C</xsl:variable>
     <xsl:variable name="gt">%3E</xsl:variable>
     <xsl:variable name="space">%20</xsl:variable>
     <xsl:variable name="and">&#x26;</xsl:variable>   
     <!-- actual query:  <info:fedora/PID> <fedora-model:hasModel> * -->
     <xsl:variable name='query' select='concat($lt, "info:fedora/", $PID, $gt, $space, $lt,
                                        "fedora-model:hasModel", $gt, $space, "*")'/>
     <!-- construct the full risearch query url -->
     <xsl:variable name='url' select='concat($REPOSITORYURL, "risearch?type=triples", $and,
         "flush=true", $and, "lang=spo", $and, "format=RDF/XML", $and, "query=", $query)'/>

     <!-- pull content model pids from the result -->
     <xsl:variable name='object' select='document($url)'/> 
     <xsl:for-each select="$object//rdf:Description/fedora-model:hasModel">
       <xsl:value-of select="concat(@rdf:resource, ', ')"/> 
     </xsl:for-each> 
   </xsl:if>
 </xsl:variable>
    <xsl:comment>CONTENT MODEL is: <xsl:value-of select="$contentModel"/></xsl:comment>
    
    <xsl:variable name="state" select="/foxml:digitalObject/foxml:objectProperties/foxml:property[@NAME='info:fedora/fedora-system:def/model#state']/@VALUE"/>

    <xsl:if test="($state = 'Active' or $state = 'Inactive')
                    and contains($contentModel, 'emory-control:ETD-1.0')">
      <!-- FIXME: need to include etdFile objects at some point -->
      <add> 
        <doc> 
        <field name="PID"><xsl:value-of select="$PID"/></field>
        
        <xsl:apply-templates select="foxml:digitalObject/foxml:objectProperties/foxml:property"/>
        <xsl:apply-templates select="foxml:digitalObject/foxml:datastream[@ID='MODS']"/>
         <xsl:apply-templates select="foxml:digitalObject/foxml:datastream[@ID='RELS-EXT']"/>
        
        
        </doc>
        <commit/>
      </add>

    </xsl:if>
    
  </xsl:template>

  <!-- foxml properties -->
  <xsl:template match="foxml:property">
    <field>
      <xsl:attribute name="name"><xsl:value-of select="substring-after(@NAME,'#')"/></xsl:attribute>
      <xsl:value-of select="@VALUE"/>
    </field>
  </xsl:template>

  <xsl:template match="mods:mods">
    <xsl:comment> MODS descriptive metadata</xsl:comment> 
    
    <xsl:apply-templates select="mods:titleInfo"/>
    <xsl:apply-templates select="mods:genre[@authority='aat']"/>
    <xsl:apply-templates select="mods:name[@type='personal']"/>
    <xsl:apply-templates select="mods:language"/>
    <xsl:apply-templates select="mods:subject"/>
    <xsl:apply-templates select="mods:originInfo/*"/>
    <xsl:apply-templates select="mods:abstract"/>
    <xsl:apply-templates select="mods:tableOfContents"/>
    <xsl:apply-templates select="mods:note"/>
    <xsl:apply-templates select="mods:accessCondition[@type='restrictionOnAccess']"/>
    <xsl:apply-templates select="mods:physicalDescription/mods:extent"/>
    <xsl:apply-templates select="mods:part/mods:extent"/>
    <xsl:apply-templates select="mods:extension/etd:degree"/>
  </xsl:template>

  <!-- generic mods field : use mods name as index name -->
  <xsl:template match="mods:titleInfo/mods:title | mods:abstract | mods:subject | mods:tableOfContents">
    <xsl:if test="normalize-space(text()) != ''">
      <field><xsl:attribute name="name"><xsl:value-of select="local-name()"/></xsl:attribute><xsl:value-of select="text()"/></field>
    </xsl:if>
  </xsl:template>
  
  <!-- for ETDs: dissertation, masters thesis, honors thesis -->
  <xsl:template match="mods:genre">
    <field name="document_type"><xsl:value-of select="text()"/></field>
  </xsl:template>

  <!-- ignore originInfo issuance -->
  <xsl:template match="mods:originInfo/mods:issuance" priority="2"/>
  
  <!-- date fields -->
  <xsl:template match="mods:originInfo/*">
    <xsl:if test=". != ''">	<!-- only include if set -->

      <!-- date format is YYYY-MM-DDT00:00:00Z 
           (if data is not in this format, date string will be empty)
           -->
      
      <!-- convert date to YYYMMDD (lucene recommended form)-->
      <!-- FIXME: is time important? -->
      <xsl:variable name="date">
        <xsl:call-template name="date-to-YYYYMMDD">
          <xsl:with-param name="date"><xsl:value-of select="."/></xsl:with-param>
        </xsl:call-template>
      </xsl:variable>
      
      <xsl:variable name="fieldname">
        <xsl:choose>
          <xsl:when test="local-name() = 'dateOther'">
            <xsl:value-of select="concat('date_', @type)"/>
          </xsl:when>
          <xsl:otherwise>
            <!-- dateIssued, dateCreated, dateModified, copyrightDate -->
            <xsl:value-of select="local-name()"/>
          </xsl:otherwise>
        </xsl:choose>
      </xsl:variable>
      
      <field>
        <xsl:attribute name="name"><xsl:value-of select="$fieldname"/></xsl:attribute>
        <xsl:value-of select="$date"/>
      </field>
      
      <!-- key on date issued for pubyear -->
      <xsl:if test="$fieldname = 'dateIssued'">	<!-- first four digits -->
        <field name="year"><xsl:value-of select="substring($date, 1, 4)"/></field>
      </xsl:if>

    </xsl:if>
  </xsl:template>

  <!-- template to convert date from YYYY-MM-DDT00:00:00Z or YYYY-MM-DD to 
       YYYMMDD (Lucene recommended date form)	-->
  <xsl:template name="date-to-YYYYMMDD">
    <xsl:param name="date"/>
    
    <xsl:variable name="year"><xsl:value-of select="substring-before($date, '-')"/></xsl:variable>
    <xsl:variable name="afteryear"><xsl:value-of select="substring-after($date, '-')"/></xsl:variable>
    
    <xsl:variable name="month"><xsl:value-of select="substring-before($afteryear, '-')"/></xsl:variable>
    <xsl:variable name="aftermonth"><xsl:value-of select="substring-after($afteryear, '-')"/></xsl:variable>
    
    <xsl:variable name="day">
      <xsl:choose>
        <!-- if includes time -->
        <xsl:when test="contains($date, 'T')">
          <xsl:value-of select="substring-before($aftermonth, 'T')"/>
        </xsl:when>
        <xsl:otherwise>
          <xsl:value-of select="$aftermonth"/>
        </xsl:otherwise>
      </xsl:choose>
    </xsl:variable>
  
    <xsl:value-of select="concat($year, $month, $day)"/>
  </xsl:template>

  <xsl:template match="mods:name[@type='personal']">
    <!-- Fez adds empty committee members; skip them -->
    <!-- also skip empty names that are showing up as ", " in the display form -->
    <xsl:if test="mods:displayForm != '' and normalize-space(mods:displayForm) != ','"> 
      <xsl:variable name="person-type">
        <xsl:choose>
          <xsl:when test="mods:role/mods:roleTerm = 'author'">author</xsl:when>
          <xsl:when test="mods:role/mods:roleTerm = 'Thesis Advisor'">advisor</xsl:when>
          <xsl:when test="mods:role/mods:roleTerm = 'Committee Member'">committee</xsl:when>
        </xsl:choose>
      </xsl:variable>
      
      <field>
        <xsl:attribute name="name"><xsl:value-of select="$person-type"/></xsl:attribute>
        <xsl:if test="$person-type = 'advisor'"><xsl:attribute name="boost">1.25</xsl:attribute></xsl:if>
        <xsl:value-of select="mods:displayForm"/>
      </field>
      <xsl:if test="$person-type = 'author'">
        <xsl:apply-templates select="mods:affiliation"/>
      </xsl:if>
      
      <!-- index committee netids in order to retrieve records for faculty view -->
      <xsl:if test="$person-type = 'advisor' or $person-type = 'committee'">
        <field>
          <xsl:attribute name="name"><xsl:value-of select="concat($person-type, '_id')"/></xsl:attribute>
          <xsl:if test="$person-type = 'advisor'"><xsl:attribute name="boost">1.25</xsl:attribute></xsl:if>
          <xsl:value-of select="@ID"/>
        </field>
      </xsl:if>

      <!-- FIXME: do we care about indexing non-emory committee member affiliations? -->
      
    </xsl:if>
  </xsl:template>

  <!-- author affiliation = department/program -->
  <xsl:template match="mods:name[mods:role/mods:roleTerm = 'author']/mods:affiliation">
    <field name="program"><xsl:apply-templates/></field>
  </xsl:template>

  <xsl:template match="mods:language">
    <field name="language"><xsl:value-of select="mods:languageTerm"/></field>
  </xsl:template>

  <xsl:template match="mods:subject">
    <xsl:if test="mods:topic != ''">          <!-- skip empty terms  -->
      <field>
        <xsl:attribute name="name">
          <xsl:choose>
            <xsl:when test="@authority = 'proquestresearchfield'">subject</xsl:when>
            <xsl:when test="@authority = 'keyword'">keyword</xsl:when>
            <!-- need a default if nothing is set or else the index will be broken -->
            <xsl:otherwise>subject</xsl:otherwise>
          </xsl:choose>
        </xsl:attribute>
        <xsl:value-of select="mods:topic"/>
      </field>
    </xsl:if>
  </xsl:template>

  <!-- asking PQ to register copyright : yes/no -->
  <xsl:template match="mods:note[@ID='copyright']">
    <xsl:variable name="yesno">
      <xsl:value-of select="substring-after(., 'registering copyright? ')"/>
    </xsl:variable>
    
    <xsl:if test="$yesno != ''">
      <field name="registering_copyright"><xsl:value-of select="$yesno"/></field>
    </xsl:if>
  </xsl:template>

  <!-- embargo requested : yes/no -->
  <xsl:template match="mods:note[@ID='embargo']">
    <xsl:variable name="yesno">
      <xsl:value-of select="substring-after(., 'embargo requested? ')"/>
    </xsl:variable>

    <xsl:if test="$yesno != ''">
      <field name="embargo_requested"><xsl:value-of select="$yesno"/></field>
    </xsl:if>
  </xsl:template>

  <xsl:template match="mods:note[@ID='embargo_expiration_notice']">
    <xsl:if test=". != ''">
      <field name="embargo_notice"><xsl:value-of select="."/></field>
    </xsl:if>
  </xsl:template>

  <xsl:template match="mods:note[@type='partneragencytype']">
    <xsl:if test=". != ''">
      <field name="partneringagencies"><xsl:value-of select="."/></field>
    </xsl:if>
  </xsl:template>

  <!-- embargo duration -->
  <xsl:template match="mods:accessCondition[@type='restrictionOnAccess']">
    <xsl:if test=". != ''">
      <field name="embargo_duration"><xsl:value-of select="substring-after(., 'Embargoed for ')"/></field>
    </xsl:if>
  </xsl:template>

  <!-- page count -->
  <xsl:template match="mods:physicalDescription/mods:extent">
    <xsl:if test=". != ''">	<!-- ignore when blank -->
      <!-- format with leading zeroes so a range-search will work -->
      <field name="num_pages"><xsl:value-of select="format-number(substring-before(., ' p.'), '00000')"/></field>
    </xsl:if>
  </xsl:template>



  <!-- degree name -->
  <xsl:template match="mods:extension/etd:degree/etd:name">
    <xsl:if test=". != ''">                     <!-- only include if not empty -->
      <field name="degree_name"><xsl:apply-templates/></field>
    </xsl:if>
  </xsl:template>

  <!-- degree level -->
  <xsl:template match="mods:extension/etd:degree/etd:level">
    <xsl:if test=". != ''">                     <!-- only include if not empty -->
      <field name="degree_level"><xsl:value-of select="normalize-space(.)"/></field>
    </xsl:if>
  </xsl:template>


  <!-- departmental subfield; also included in program facet -->
  <xsl:template match="mods:extension/etd:degree/etd:discipline">
    <xsl:if test=". != ''">                     <!-- only include if not empty -->
      <field name="subfield"><xsl:apply-templates/></field>
    </xsl:if>
  </xsl:template>


  <!-- RELS-EXT -->
  <xsl:template match="rdf:RDF">
    <xsl:apply-templates select="rdf:Description/rel:etdStatus"/>
    <xsl:apply-templates select="rdf:Description/rel:hasPDF"/>

    <xsl:apply-templates select="rdf:Description/rel:program"/>
    <xsl:apply-templates select="rdf:Description/rel:subfield"/>

    <xsl:apply-templates select="rdf:Description/fedora-model:hasModel"/>
    <xsl:apply-templates select="rdf:Description/rel:isMemberOfCollection"/>
  </xsl:template>

  <xsl:template match="rel:etdStatus">
    <field name="status"><xsl:value-of select="."/></field>
  </xsl:template>

  <xsl:template match="rel:program">
    <xsl:if test=". != ''">                     <!-- only include if not empty -->
      <field name="program_id"><xsl:value-of select="."/></field>
    </xsl:if>
  </xsl:template>

  <xsl:template match="rel:subfield">
    <xsl:if test=". != ''">                     <!-- only include if not empty -->
      <field name="subfield_id"><xsl:value-of select="."/></field>
    </xsl:if>
  </xsl:template>

   <xsl:template match="fedora-model:hasModel">
    <field name="contentModel"><xsl:value-of select="substring-after(@rdf:resource, 'info:fedora/')"/></field>
  </xsl:template>

   <xsl:template match="rel:isMemberOfCollection">
    <field name="collection"><xsl:value-of select="substring-after(@rdf:resource, 'info:fedora/')"/></field>
  </xsl:template>

  <!-- index pdf datastream for related object -->
  <xsl:template match="rel:hasPDF">
    <!-- pid of the etdFile object for the PDF  -->
    <!--<xsl:variable name="etdfile"><xsl:value-of select="substring-after(@rdf:resource, 'info:fedora/')"/></xsl:variable> -->

    <!-- adding text of the PDF to default text index for etd -->
<!-- may eventually just put in default search field... 
             using a new field for testing  -->

<!-- FIXME: for some documents we get null characters here;
    Solr chokes on it, and it can't be cleaned up in xslt -->
<!--    <field name="pdf">
      <xsl:value-of select="normalize-space(exts:getDatastreamText($etdfile, $REPOSITORYNAME, 'FILE', $FEDORASOAP, $FEDORAUSER, $FEDORAPASS, $TRUSTSTOREPATH, $TRUSTSTOREPASS))"/>
    </field> -->
  </xsl:template>



<!-- a managed datastream is fetched, if its mimetype 
     can be handled, the text becomes the value of the field. -->
<!--<xsl:for-each select="foxml:datastream[@CONTROL_GROUP='M']"> -->
<!--<field index="TOKENIZED" store="YES" termVector="NO"> -->
<!--<field>
     <xsl:attribute name="name">
       <xsl:value-of select="concat('dsm.', @ID)"/>
     </xsl:attribute>
     <xsl:value-of select="exts:getDatastreamText($PID, $REPOSITORYNAME, @ID, $FEDORASOAP, $FEDORAUSER, $FEDORAPASS, $TRUSTSTOREPATH, $TRUSTSTOREPASS)"/>
   </field>
 </xsl:for-each> -->
 
 
</xsl:stylesheet>	
