<?xml version="1.0" encoding="UTF-8"?> 
<!-- $Id: demoFoxmlToLucene.xslt 5734 2006-11-28 11:20:15Z gertsp $ -->
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"   
  xmlns:exts="xalan://dk.defxws.fedoragsearch.server.XsltExtensions"
  xmlns:foxml="info:fedora/fedora-system:def/foxml#"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
  xmlns:mods="http://www.loc.gov/mods/v3"
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" 
  xmlns:rel="info:fedora/fedora-system:def/relations-external#"
  xmlns:skos="http://www.w3.org/2004/02/skos/core#"
  xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
  xmlns:etd="http://www.ndltd.org/standards/metadata/etdms/1.0/"
  exclude-result-prefixes="exts foxml dc oai_dc mods rdf rel rdfs skos etd">

  <xsl:output method="xml" indent="yes" encoding="UTF-8"/>

  <!-- create an IndexDocument from FOXML -->

  
  <!--
       This xslt stylesheet generates the IndexDocument consisting of IndexFields
       from a FOXML record. The IndexFields are:
       - from the root element = PID
       - from foxml:property   = type, state, contentModel, ...
       - from oai_dc:dc        = title, creator, ...
       Options for tailoring:
       - IndexField types, see Lucene javadoc for Field.Store, Field.Index, Field.TermVector
       - IndexField boosts, see Lucene documentation for explanation
       - IndexDocument boosts, see Lucene documentation for explanation
       - generation of IndexFields from other XML metadata streams than DC
       - e.g. as for uvalibdesc included above and called below, the XML is inline
       - for not inline XML, the datastream may be fetched with the document() function,
       see the example below (however, none of the demo objects can test this)
       - generation of IndexFields from other datastream types than XML
       - from datastream by ID, text fetched, if mimetype can be handled
       - from datastream by sequence of mimetypes, 
       text fetched from the first mimetype that can be handled,
       default sequence given in properties
       - currently only the mimetype application/pdf can be handled.
       -->

  <xsl:variable name="pid" select="/foxml:digitalObject/@PID"/>
  <xsl:variable name="cmodel"  
    select="//foxml:objectProperties/foxml:property[@NAME='info:fedora/fedora-system:def/model#contentModel']/@VALUE"/>

  <xsl:template match="/">
    <IndexDocument> 
    <!-- The PID attribute is mandatory for indexing to work -->
    <xsl:attribute name="PID">
      <xsl:value-of select="$pid"/>
    </xsl:attribute>

    <!-- FIXME: probably should boost main etd & pdf higher and/or supplements lower 
         <xsl:attribute name="boost"/> -->


         <xsl:choose>
           <!-- only index etd records  -->
           <xsl:when test="$cmodel = 'etd'">
             <xsl:apply-templates/>
           </xsl:when>
           <xsl:when test="$cmodel = 'etdfile'">
             <xsl:apply-templates mode="etdFile"/>   <!-- doesn't exist yet -->
           </xsl:when>
         </xsl:choose>

       </IndexDocument>
     </xsl:template>



     <xsl:template match="/foxml:digitalObject">
       
       <IndexField IFname="PID" index="UN_TOKENIZED" store="YES" termVector="NO" boost="2.5">
         <xsl:value-of select="$pid"/>
       </IndexField>

       <xsl:apply-templates select="foxml:objectProperties/foxml:property"/>

       <!-- only index the latest version of the datastream -->

       <!-- dublin core data is completely redundant, based on metadata found elsewhere -->
       <!--       <xsl:apply-templates select="foxml:datastream/foxml:datastreamVersion[position() = last()]/foxml:xmlContent/oai_dc:dc"/> -->

       <xsl:apply-templates select="foxml:datastream/foxml:datastreamVersion[position() = last()]/foxml:xmlContent/mods:mods"/>

       <xsl:apply-templates select="foxml:datastream[@ID='RELS-EXT']/foxml:datastreamVersion[position() = last()]/foxml:xmlContent/rdf:RDF"/>


       <!-- for not inline XML, the datastream may be fetched with the document() function,
            none of the demo objects can test this,
            however, it has been tested with a managed xml called RIGHTS2 added to demo:10 with fedora-admin -->
       
       <!-- uncomment it, if you wish, it takes time, even if the foxml has no RIGHTS2 datastream.
            <xsl:call-template name="example-of-xml-not-inline"/>
            -->
            
            <!-- This is an example of calling an extension function, see Apache Xalan, may be used for filters.
                 <IndexField IFname="fgs.DS" index="TOKENIZED" store="YES" termVector="NO">
                   <xsl:value-of select="exts:someMethod($pid)"/>
                 </IndexField>
                 -->

                 <!--            <xsl:apply-templates select="foxml:datastream/foxml:datastreamVersion[position() = last() and @MIMETYPE='application/pdf']"/> -->
                 
               </xsl:template>

               <xsl:template match="foxml:datastreamVersion[@MIMETYPE='application/pdf']">

                 <!-- a datastream identified in dsId is fetched, if its mimetype 
                      can be handled, the text becomes the value of the IndexField. -->
                 <IndexField IFname="pdf" index="TOKENIZED" store="NO" termVector="NO">
                   <xsl:attribute name="dsId">
                     <xsl:value-of select="ancestor::foxml:datastream/@ID"/>
                   </xsl:attribute>
                 </IndexField>
               </xsl:template>
               

               <!--	<xsl:template name="example-of-xml-not-inline">
                    <IndexField IFname="uva.access" index="TOKENIZED" store="YES" termVector="NO">
                      <xsl:value-of select="document(concat('http://localhost:8080/fedora/get/', $pid, '/RIGHTS2'))/uvalibadmin:admin/uvalibadmin:adminrights/uvalibadmin:policy/uvalibadmin:access"/>
                    </IndexField>
                  </xsl:template> -->
                  

                  <!-- foxml top-level properties (state, label, cmodel) -->
                  <xsl:template match="foxml:objectProperties/foxml:property">
                    <xsl:variable name="property">
                      <xsl:value-of select="substring-after(@NAME,'#')"/>                      
                    </xsl:variable>

                    <IndexField index="UN_TOKENIZED" store="NO" termVector="NO">
                      <xsl:attribute name="IFname"> 
                      <xsl:value-of select="$property"/>
                    </xsl:attribute>
                    <xsl:value-of select="@VALUE"/>
                  </IndexField>

                  <!-- for date-time properties, also index as date -->
                  <xsl:if test="contains($property, 'Date')">	<!-- created, modified -->
                    <IndexField index="UN_TOKENIZED" store="NO" termVector="NO">
                      <xsl:attribute name="IFname"> 
                      <xsl:value-of select="concat($property, '_YYYYMMDD')"/>
                    </xsl:attribute>
                    <xsl:call-template name="date-to-YYYYMMDD">
                      <xsl:with-param name="date"><xsl:value-of select="@VALUE"/></xsl:with-param>
                    </xsl:call-template>
                  </IndexField>
                    
                  </xsl:if>
                  
                </xsl:template>


                <xsl:template match="oai_dc:dc">
                  <!-- space, label for readability in xml output -->
                  <xsl:text>

                  </xsl:text>
                  <xsl:comment> dublin core fields </xsl:comment>
                  <xsl:apply-templates/>
                </xsl:template>

                <!-- dublin core fields -->
                <xsl:template match="oai_dc:dc/*">
                  <xsl:if test=". != ''">	<!-- don't index empty fields -->
                  <IndexField index="TOKENIZED" store="YES" termVector="YES">
                    <xsl:attribute name="IFname">
                      <xsl:value-of select="concat('dc.', local-name())"/>
                    </xsl:attribute>
                    <xsl:value-of select="text()"/>
                  </IndexField>
                </xsl:if>
              </xsl:template>

              <xsl:template match="mods:mods">
                <!-- space, label for readability in xml output -->
                 <xsl:text>

                </xsl:text>
                <xsl:comment> MODS descriptive metadata</xsl:comment>

                <xsl:apply-templates select="mods:titleInfo"/>
                <xsl:apply-templates select="mods:genre"/>
                <xsl:apply-templates select="mods:name[@type='personal']"/>
                <xsl:apply-templates select="mods:language"/>
                <xsl:apply-templates select="mods:subject"/>
                <xsl:apply-templates select="mods:originInfo/*"/>
                <xsl:apply-templates select="mods:abstract"/>
                <xsl:apply-templates select="mods:tableOfContents"/>
                <xsl:apply-templates select="mods:note"/>
                <xsl:apply-templates select="mods:physicalDescription/mods:extent"/>
                <xsl:apply-templates select="mods:part/mods:extent"/>

                <xsl:apply-templates select="mods:extension/etd:degree/etd:discipline"/>



                <!-- fields to be included in main search box (simple search) -->
                <IndexField IFname="text" index="TOKENIZED" store="NO">
                  <xsl:apply-templates select="mods:titleInfo/mods:title |
                       mods:name[@type='personal']/mods:namePart |
                       mods:name[mods:role/mods:roleTerm = 'author']/mods:affiliation |
                       mods:extension/etd:degree/etd:discipline |
                       mods:subject/mods:topic | mods:abstract | mods:tableOfContents " 
                    mode="textfield"/>
                </IndexField>

              </xsl:template>

              <!-- just display the text - for main text index -->
              <xsl:template match="node()" mode="textfield">
                <xsl:text> </xsl:text>	<!-- force a space between fields -->
                <xsl:apply-templates/>
              </xsl:template>


              <!-- generic mods field : use xml name as index name -->
              <xsl:template match="mods:titleInfo/mods:title | mods:abstract | mods:tableOfContents">
                <IndexField index="TOKENIZED" store="NO" termVector="YES">
                  <xsl:attribute name="IFname"><xsl:value-of select="local-name()"/></xsl:attribute>
                  <xsl:value-of select="text()"/>
                </IndexField>

                <!-- include un-tokenized version of title for sorting purposes -->
                <xsl:if test="local-name() = 'title'">
                  <IndexField IFname="title_sort" index="UN_TOKENIZED" store="YES" termVector="YES">
                    <xsl:value-of select="text()"/>
                  </IndexField>
                </xsl:if>

              </xsl:template>


                 <!-- for ETDs: dissertation, masters thesis, honors thesis -->
                 <xsl:template match="mods:genre">
                   <IndexField index="UN_TOKENIZED" store="YES" termVector="YES">
                     <xsl:attribute name="IFname">document_type</xsl:attribute>
                     <xsl:value-of select="text()"/>
                   </IndexField>
                 </xsl:template>


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

                   <IndexField index="UN_TOKENIZED" store="YES" termVector="YES">
                     <xsl:attribute name="IFname">
                       <xsl:value-of select="$fieldname"/>
                     </xsl:attribute>
                     <xsl:value-of select="$date"/>
                   </IndexField>
                   
                   <!-- key on date issued for pubyear -->
                   <xsl:if test="$fieldname = 'dateIssued'">
                     <IndexField IFname="year" index="UN_TOKENIZED" store="YES" termVector="YES">
                       <xsl:value-of select="substring($date, 1, 4)"/>	<!-- first four digits -->
                     </IndexField>
                   </xsl:if>
                   
                 </xsl:if>
               </xsl:template>




               <!-- template to convert date from YYYY-MM-DDT00:00:00Z or YYYY-MM-DD to 
                    YYYMMDD (Lucene recommended date form)	-->
               <xsl:template name="date-to-YYYYMMDD">
                 <xsl:param name="date"/>

                 <xsl:variable name="year">
                   <xsl:value-of select="substring-before($date, '-')"/>
                 </xsl:variable>
                 <xsl:variable name="afteryear">
                   <xsl:value-of select="substring-after($date, '-')"/>
                 </xsl:variable>
                 
                 <xsl:variable name="month">
                   <xsl:value-of select="substring-before($afteryear, '-')"/>
                 </xsl:variable>
                 <xsl:variable name="aftermonth">
                   <xsl:value-of select="substring-after($afteryear, '-')"/>
                 </xsl:variable>
                 
                 <xsl:variable name="day">
                   <xsl:choose>
                     <xsl:when test="contains($date, 'T')">	<!-- if includes time -->
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
                    <xsl:if test="mods:displayForm != ''">    <!-- Fez adds empty committee members; skip them -->
                    <xsl:variable name="person-type">
                      <xsl:choose>
                        <xsl:when test="mods:role/mods:roleTerm = 'author'">author</xsl:when>
                        <xsl:when test="mods:role/mods:roleTerm = 'Thesis Advisor'">advisor</xsl:when>
                        <xsl:when test="mods:role/mods:roleTerm = 'Committee Member'">committee</xsl:when>
                      </xsl:choose>
                    </xsl:variable>

                    <IndexField index="TOKENIZED" store="NO" termVector="YES">
                      <xsl:attribute name="IFname"><xsl:value-of select="$person-type"/></xsl:attribute>
                      <xsl:apply-templates select="mods:namePart"/>
                    </IndexField>

                    <!-- advisor should additionally be indexed as generic committee member -->
                    <xsl:if test="$person-type = 'advisor'">
                      <IndexField index="TOKENIZED" store="NO" termVector="YES" IFname="committee">
                        <xsl:value-of select="mods:displayForm"/>
                      </IndexField>

                      <!-- advisor should also be indexed in committee member facet -->
                      <IndexField index="UN_TOKENIZED" store="NO" termVector="YES" IFname="committee_lastnamefirst">
                        <xsl:value-of select="mods:displayForm"/>
                      </IndexField>
                    </xsl:if>

                    <!-- untokenized version (lastname, first) for sorting and browsing / facets -->
                    <IndexField index="UN_TOKENIZED" store="NO" termVector="YES">
                        <xsl:attribute name="IFname">
                          <xsl:value-of select="concat($person-type, '_lastnamefirst')"/>
                        </xsl:attribute>
                        <xsl:value-of select="mods:displayForm"/>
                    </IndexField>
                    
                    <xsl:if test="$person-type = 'author'">
                      <xsl:apply-templates select="mods:affiliation"/>
                    </xsl:if>
                    <!-- FIXME: do we care about indexing non-emory committee member affiliations? -->
                    
                  </xsl:if>
                </xsl:template>

                <xsl:template match="mods:namePart">
                  <xsl:apply-templates/>
                  <xsl:text> </xsl:text>	<!-- force a space between first & last names -->
                </xsl:template>

                <!-- author affiliation = department/program -->
                <xsl:template match="mods:name[mods:role/mods:roleTerm = 'author']/mods:affiliation">
                  <IndexField index="UN_TOKENIZED" store="YES" termVector="YES" IFname="program_facet">
                    <xsl:apply-templates/>
                  </IndexField>
                  
                  <!-- untokenized version for user search -->
                  <IndexField index="TOKENIZED" store="NO" termVector="YES" IFname="program">
                    <xsl:apply-templates/>
                  </IndexField>
                </xsl:template>
                  
                  <xsl:template match="mods:language">
                    <IndexField index="UN_TOKENIZED" store="YES" termVector="YES">
                      <xsl:attribute name="IFname">language</xsl:attribute>
                      <xsl:value-of select="mods:languageTerm"/>
                    </IndexField>
                  </xsl:template>
                  
                  <xsl:template match="mods:subject">
                    <xsl:if test="mods:topic != ''">          <!-- skip empty terms  -->
                    <!-- FIXME: how to pick up / store proquest terms vs. keywords? -->
                    <IndexField index="TOKENIZED" store="NO" termVector="YES">
                      <xsl:attribute name="IFname">
                        <xsl:choose>
                          <xsl:when test="@authority = 'proquestresearchfield'">subject</xsl:when>
                          <xsl:when test="@authority = 'keyword'">keyword</xsl:when>
                        </xsl:choose>
                      </xsl:attribute>
                      <xsl:value-of select="mods:topic"/>
                    </IndexField>
                    
                    <IndexField index="UN_TOKENIZED" store="YES" termVector="YES">
                      <xsl:attribute name="IFname">
                        <xsl:choose>
                          <xsl:when test="@authority = 'proquestresearchfield'">subject_facet</xsl:when>
                          <xsl:when test="@authority = 'keyword'">keyword_facet</xsl:when>
                        </xsl:choose>
                      </xsl:attribute>
                      <xsl:value-of select="mods:topic"/>
                    </IndexField>
                    
                     </xsl:if>
                     
                   </xsl:template>

                   <!-- asking PQ to register copyright : yes/no -->
                   <xsl:template match="mods:note[@ID='copyright']">
                     <xsl:variable name="yesno">
                       <xsl:value-of select="substring-after(., 'registering copyright? ')"/>
                     </xsl:variable>

                     <xsl:if test="$yesno != ''">
                       <IndexField index="UN_TOKENIZED" store="NO" IFname="registering_copyright">
                         <xsl:value-of select="$yesno"/>
                       </IndexField>
                     </xsl:if>
                   </xsl:template>
                   
                   <!-- embargo requested : yes/no -->
                   <xsl:template match="mods:note[@ID='copyright']">
                     <xsl:variable name="yesno">
                       <xsl:value-of select="substring-after(., 'embargo requested? ')"/>
                     </xsl:variable>

                     <xsl:if test="$yesno != ''">
                       <IndexField index="UN_TOKENIZED" store="NO" IFname="embargo_requested">
                         <xsl:value-of select="$yesno"/>
                       </IndexField>
                     </xsl:if>
                   </xsl:template>

                   <!-- OLD place where page count was stored -->
                   <xsl:template match="mods:extent[@unit = 'pages']">
                     <xsl:if test=". != ''">	<!-- ignore when blank -->
                     <IndexField index="UN_TOKENIZED" store="YES" termVector="YES">
                       <xsl:attribute name="IFname">num_pages</xsl:attribute>
                       <!-- format with leading zeroes so a range-search will work -->
                       <xsl:value-of select="format-number(mods:total, '00000')"/>
                     </IndexField>
                   </xsl:if>
                 </xsl:template>

                     <!-- NEW page count location -->
                     <xsl:template match="mods:physicalDescription/mods:extent">
                       <xsl:if test=". != ''">	<!-- ignore when blank -->
                       <IndexField index="UN_TOKENIZED" store="YES" termVector="YES">
                         <xsl:attribute name="IFname">num_pages</xsl:attribute>
                         <!-- format with leading zeroes so a range-search will work -->
                         <xsl:value-of select="format-number(substring-before(., ' p.'), '00000')"/>
                       </IndexField>
                     </xsl:if>
                   </xsl:template>


                   <!-- departmental subfield; part of program facet -->
                   <xsl:template match="mods:extension/etd:degree/etd:discipline">
                     <xsl:if test=". != ''">                     <!-- only include if not empty -->
                       <!-- index but as program, but don't return -->
                       <IndexField index="UN_TOKENIZED" store="NO" termVector="YES" IFname="program_facet">
                         <xsl:apply-templates/>
                       </IndexField>
                       
                       <!-- return as subfield -->
                       <IndexField index="UN_TOKENIZED" store="YES" termVector="YES" IFname="subfield">
                         <xsl:apply-templates/>
                       </IndexField>
                     </xsl:if>
                   </xsl:template>


                   <!-- RELS-EXT -->
                   <xsl:template match="rdf:RDF[ancestor::foxml:datastream/@ID='RELS-EXT']">
                     <!-- space, label for readability in xml output -->
                     <xsl:text>
                       
                     </xsl:text>
                     <xsl:apply-templates select="rdf:description/rel:etdStatus"/>
                     <!-- currently not including any rels besides status -->
                   </xsl:template>
                   
                   
                   <xsl:template match="rel:etdStatus">
                     <IndexField IFname="status" index="UN_TOKENIZED" store="NO">
                       <xsl:value-of select="."/>
                     </IndexField>
                   </xsl:template>
                   
                   <!-- other rels? etd id from etdfile ? -->
                   
                 </xsl:stylesheet>	
                    