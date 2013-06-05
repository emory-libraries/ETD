<xsl:stylesheet version="2.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
  xmlns:mods="http://www.loc.gov/mods/v3"
  xmlns:etd="http://www.ndltd.org/standards/metadata/etdms/1.0/"
  xmlns="http://www.ndltd.org/standards/metadata/etdms/1.0/"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:xs="http://www.w3.org/2001/XMLSchema"
  exclude-result-prefixes="mods etd xsl xs">  


<xsl:output method="xml" indent="yes"/> 

<xsl:strip-space elements="*"/> 

<xsl:template match="/">
  <xsl:apply-templates/>
</xsl:template>

<xsl:template match="mods:mods">
  <thesis xsi:schemaLocation="http://www.ndltd.org/standards/metadata/etdms/1.0/etdms.xsd">
    <!-- schema is order dependent; applying templates in that exact order -->
    <!-- title -->
    <xsl:apply-templates select="mods:titleInfo"/>
    <!-- alternative title (unused) -->
    <!-- creator -->
    <xsl:apply-templates select="mods:name[mods:role/mods:roleTerm[@type='text'] = 'author' 
                                 or mods:role/mods:roleTerm[@type='code'] = 'aut']"/>
    <!-- subject -->
    <xsl:apply-templates select="mods:subject"/>
    <!-- description -->
    <xsl:apply-templates select="mods:abstract | mods:tableOfContents"/>
    <!-- publisher -->
    <xsl:apply-templates select="mods:name[@type='corporate'][mods:role/mods:roleTerm[@type='text'] = 'Degree grantor']"/> 
    <!-- contributor (committee chair/members) -->
    <xsl:apply-templates select="mods:name[mods:role/mods:roleTerm[@type='text'] = 'Thesis Advisor' 
                                 or mods:role/mods:roleTerm[@type='text'] = 'Committee Member']"/>
    <!-- date (only one) -->
    <xsl:apply-templates select="mods:originInfo/mods:dateIssued"/>
    <!-- type -->
    <!-- ETD-MS recommended type to be included for all ETDs -->
    <type><xsl:text>Electronic Thesis or Dissertation</xsl:text></type>
    <xsl:apply-templates select="mods:genre | mods:typeOfResource"/>
    <!-- format -->
    <xsl:apply-templates select="mods:physicalDescription/mods:internetMediaType"/>
    <!-- identifier -->
    <xsl:apply-templates select="mods:identifier | mods:recordIdentifier | mods:location"/>
    <!-- language -->
    <xsl:apply-templates select="mods:language"/>
    <!-- coverage (spatial/temporal); optional, unused -->
    <!-- rights -->
    <xsl:apply-templates select="mods:accessCondition[@type='useAndReproduction']"/>
    <!-- including any unexpired embargo information as rights -->
    <xsl:apply-templates select="mods:originInfo/mods:dateOther[@type='embargoedUntil']"/>
    <!-- degree -->
    <xsl:apply-templates select="mods:extension/etd:degree"/>
    
  </thesis>
</xsl:template> 


<xsl:template match="mods:titleInfo">
  <title>

    <xsl:value-of select="mods:nonSort"/>

    <xsl:if test="mods:nonSort">


      <xsl:text> </xsl:text>
    </xsl:if>
    <xsl:value-of select="mods:title"/>
    <xsl:if test="mods:subTitle">
      <xsl:text>: </xsl:text>

      <xsl:value-of select="mods:subTitle"/>

    </xsl:if>
    <xsl:if test="mods:partNumber">

      <xsl:text>. </xsl:text>
      <xsl:value-of select="mods:partNumber"/>
    </xsl:if>
    <xsl:if test="mods:partName">

      <xsl:text>. </xsl:text>

      <xsl:value-of select="mods:partName"/>
    </xsl:if>
  </title>

</xsl:template>

<xsl:template match="mods:name[mods:role/mods:roleTerm[@type='text']='author' or mods:role/mods:roleTerm[@type='code']='aut']">
  <creator>
    <xsl:value-of select="mods:displayForm"/>
  </creator>
</xsl:template>

<xsl:template match="mods:name[mods:role/mods:roleTerm[@type='text']='Thesis Advisor' or mods:role/mods:roleTerm[@type='text']='Committee Member']">
  <contributor role="thesis advisor">       
  <xsl:attribute name="role">
    <xsl:value-of select="mods:role/mods:roleTerm[@type='text']"/>
  </xsl:attribute>
  <xsl:value-of select="mods:displayForm"/>       
</contributor>
</xsl:template>


<xsl:template match="mods:name[@type='personal'][mods:namePart[@type='given'] = ''
                     and mods:namePart[@type='family'] = '' and normalize-space(mods:displayForm) = ',']"/>

<xsl:template match="mods:subject[mods:topic | mods:name | mods:occupation | mods:geographic | mods:hierarchicalGeographic | mods:cartographics | mods:temporal] ">
  
  <subject>
    <xsl:for-each select="mods:topic">
      <xsl:value-of select="."/>
      
      <xsl:if test="position()!=last()">--</xsl:if>
      
    </xsl:for-each>
    
    
    <xsl:for-each select="mods:occupation">
      <xsl:value-of select="."/>
      <xsl:if test="position()!=last()">--</xsl:if>
      
    </xsl:for-each>
  </subject>  
  
  <xsl:if test="*[1][local-name()='topic'] and *[local-name()!='topic']">
    <subject>
      <xsl:for-each select="*[local-name()!='cartographics' and local-name()!='geographicCode' and local-name()!='hierarchicalGeographic'] ">
        
        <xsl:value-of select="."/>
        <xsl:if test="position()!=last()">--</xsl:if>
        
      </xsl:for-each>
      
    </subject>
  </xsl:if>
</xsl:template>

<xsl:template match="mods:abstract">
  <description>
    <xsl:value-of select="."/>
  </description>
</xsl:template>

<xsl:template match="mods:tableOfContents">
  <description>
    <xsl:value-of select="."/>
  </description>
</xsl:template> 

<xsl:template match="mods:name[@type='corporate'][mods:role/mods:roleTerm[@type='text'] = 'Degree grantor']">
  <publisher>
    <xsl:value-of select="mods:namePart"/>
  </publisher>
</xsl:template>

<xsl:template match="mods:publisher">
  <publisher>
    <xsl:value-of select="."/>
  </publisher>
</xsl:template>

<xsl:template match="mods:dateIssued | mods:dateCreated | mods:dateCaptured">

  <date>
    <xsl:choose>

      <xsl:when test="@point='start'">

        <xsl:value-of select="."/>
        <xsl:text> - </xsl:text>

      </xsl:when>
      <xsl:when test="@point='end'">

        <xsl:value-of select="."/>
      </xsl:when>
      <xsl:otherwise>

        <xsl:value-of select="."/>
      </xsl:otherwise>
    </xsl:choose>

  </date>
</xsl:template>

<xsl:template match="mods:genre">
  <type>
    <xsl:apply-templates/>
  </type>
</xsl:template>

<!-- FIXME: is any of this older logic still needed? 

    <xsl:when test="@authority='dct'">
      <type>
        <xsl:value-of select="."/>
      </type>
      <xsl:for-each select="mods:typeOfResource">
        <type>
          <xsl:value-of select="."/>
        </type>
      </xsl:for-each>
    </xsl:when>
    <xsl:otherwise>
      <type>
        <xsl:text>Electronic Thesis or Dissertation</xsl:text>
      </type>
    </xsl:otherwise>
  </xsl:choose> -->


<xsl:template match="mods:typeOfResource">

  <xsl:if test="@collection='yes'">
    <type>Collection</type>
  </xsl:if>

  <xsl:if test=". ='software' and ../mods:genre='database'">
    <type>DataSet</type>

  </xsl:if>

  <xsl:if test=".='software' and ../mods:genre='online system or service'">
    <type>Service</type>

  </xsl:if>
  <xsl:if test=".='software'">

    <type>Software</type>
  </xsl:if>

  <xsl:if test=".='cartographic material'">
    <type>Image</type>
  </xsl:if>
  <xsl:if test=".='multimedia'">

    <type>InteractiveResource</type>

  </xsl:if>
  <xsl:if test=".='moving image'">

    <type>MovingImage</type>
  </xsl:if>
  <xsl:if test=".='three-dimensional object'">
    <type>PhysicalObject</type>

  </xsl:if>

  <xsl:if test="starts-with(.,'sound recording')">

    <type>Sound</type>

  </xsl:if>
  <xsl:if test=".='still image'">
    <type>StillImage</type>
  </xsl:if>
  <xsl:if test=". ='text'">

    <type>Text</type>

  </xsl:if>

  <xsl:if test=".='notated music'">
    <type>Text</type>
  </xsl:if>
</xsl:template>

<xsl:template match="mods:physicalDescription/mods:internetMediaType">
    <format>
      <xsl:apply-templates/>
    </format>
</xsl:template>

<xsl:template match="mods:relatedItem[@type='host']"/>

<xsl:template match="mods:language">
  <language>
    <xsl:value-of select="mods:languageTerm[@type='code']"/>
  </language>
</xsl:template>

<xsl:template match="mods:accessCondition[@type='useAndReproduction']">
  <rights>
    <xsl:apply-templates/>
  </rights>
</xsl:template>

<xsl:template match="mods:dateOther[@type='embargoedUntil']">
  <!-- if there is an embargo still in effect, include that information -->
  <xsl:variable name="embargo_end">
    <xsl:choose>
      <!-- some old records have date in W3C format; detect and convert to expected format -->
      <xsl:when test="contains(., 'T')">
        <xsl:value-of select="substring-before(., 'T')"/>
      </xsl:when>
      <xsl:otherwise>
        <xsl:value-of select="."/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:variable>

  <xsl:if test=". != '' and current-date() &lt; xs:date($embargo_end)">
    <rights>
      <xsl:value-of select="concat('Access to PDF restricted until ', $embargo_end)"/>
    </rights>
  </xsl:if>
</xsl:template>

<xsl:template match="mods:identifier | mods:recordIdentifier | mods:location">
  <xsl:if test="@type='uri'">
    <identifier>
      <xsl:value-of select="."/>      
    </identifier>
  </xsl:if>
</xsl:template>



<xsl:template match="mods:extension/etd:degree">    
<degree>
  <xsl:apply-templates select="etd:name"/>
  <xsl:apply-templates select="etd:level"/> 
  <xsl:apply-templates select="etd:discipline"/>
  <!-- pick up degree grantor from corporate name -->
  <grantor>
    <xsl:value-of select="//mods:name[@type='corporate'][mods:role/mods:roleTerm[@type='text'] = 'Degree grantor']/mods:namePart"/>
  </grantor>
</degree>
</xsl:template>

<xsl:template match="etd:degree/etd:name">
  <name><xsl:apply-templates/></name>
</xsl:template>

<xsl:template match="etd:degree/etd:level">
  <level>
    <xsl:choose>
      <!-- clean up parenthetical degree names if present -->
      <xsl:when test="contains(., '(')">
        <xsl:value-of select="normalize-space(substring-before(., '('))"/>
      </xsl:when>
      <xsl:otherwise>
        <xsl:value-of select="."/>
      </xsl:otherwise>
    </xsl:choose>
  </level>
</xsl:template> 

<xsl:template match="etd:degree/etd:discipline">
  <discipline>
    <!-- pick up main program name from author affiliation -->
    <xsl:value-of select="//mods:name[mods:role/mods:roleTerm[@type='text'] = 'author']/mods:affiliation"/>
    <!-- if there is any subfield, include it also -->
    <xsl:if test=". != ''">
      <xsl:value-of select="concat('; ', .)"/>
    </xsl:if>
  </discipline>
</xsl:template>

<!-- suppress all notes -->
<xsl:template match="mods:note"/>

</xsl:stylesheet>
