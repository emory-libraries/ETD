<xsl:stylesheet version="2.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
	xmlns:mods="http://www.loc.gov/mods/v3" exclude-result-prefixes="mods"
	xmlns:etd="http://www.ndltd.org/standards/metadata/etdms/1.0/"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:xs="http://www.w3.org/2001/XMLSchema">	
	
	
	<xsl:output method="xml" indent="yes"/>	
	
	
	<xsl:template match="/"/>
	<xsl:strip-space elements="*"/>	
	

<xsl:template match="/">
						<xsl:for-each select="mods:mods">
						<thesis xsi:schemaLocation="http://www.ndltd.org/standards/metadata/etdms/1.0/">						
						<xsl:apply-templates/>						
					</thesis>
				</xsl:for-each>		
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

	<xsl:template match="mods:name">
		<xsl:choose>

			<xsl:when test="mods:role/mods:roleTerm[@type='text']='author' or mods:role/mods:roleTerm[@type='code']='aut' ">

				<creator>
					<xsl:value-of select="mods:displayForm"/>
				</creator>
			</xsl:when>
			
			<xsl:when test="mods:role/mods:roleTerm[@type='text']='Thesis Advisor'">
				<contributor role="thesis advisor">		    
					<xsl:value-of select="mods:displayForm"/>				
					
				</contributor>
			</xsl:when>
		
  
     	<xsl:when test="mods:role/mods:roleTerm[@type='text']='Committee Member'">
				<contributor role="committee_member">		    
				<xsl:value-of select="mods:displayForm"/>				
					
				</contributor>
     	</xsl:when>
			
			     		
     		<xsl:otherwise>
     			<xsl:value-of select="."/>
     		</xsl:otherwise>
				
						
	
			
		</xsl:choose>
	
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

    <xsl:template match="mods:name[@type='corporate']">
        <publisher><xsl:if test="mods:name[@type='corporate']"></xsl:if>
            <xsl:value-of select="mods:namePart"/>

        </publisher>
    </xsl:template>

	
	<xsl:template match="mods:originInfo">
		<xsl:for-each
			select="mods:dateIssued | mods:dateCreated"> 
			<date>

				<xsl:value-of select="."/>

			</date>
		</xsl:for-each>
	</xsl:template>
    
    <xsl:template match="mods:recordInfo"/>
	
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
		<xsl:choose>
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
		</xsl:choose>

	</xsl:template>

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

	<xsl:template match="mods:physicalDescription">


			<xsl:if test="mods:internetMediaType">
			<format>
				<xsl:value-of select="mods:internetMediaType"/>

			</format>
		</xsl:if>

	</xsl:template>

	
	
	<xsl:template match="mods:relatedItem[@type='host']"/>
	
	<xsl:template match="mods:language">
		<language>

			<xsl:value-of select="mods:languageTerm[@type='code']"/>
		</language>
	</xsl:template>

	
	<xsl:template match="mods:accessCondition[@type='useAndReproduction']">
		<rights>

			<xsl:value-of select="."/>
		</rights>
	</xsl:template>

	
	<xsl:template match="mods:accessCondition[@type='restrictionOnAccess']">
		
		<xsl:variable name="embargo_end"><xsl:value-of select="//mods:dateOther[@type='embargoedUntil']"/></xsl:variable>
		<xsl:choose>
			<!-- if embargo is for 0 days, not set, or embargo end is not set, do nothing;
				don't output restrictionOnAccess  -->
			<xsl:when test="contains(., '0 days') or . = '' or $embargo_end = ''"/>

			<!-- embargo is non-zero and end date is still in the future -->
			<xsl:when test="current-date() &lt; xs:date($embargo_end)">
				
					 
					<rights>
						<xsl:value-of select="concat('Access to PDF is restricted until ', $embargo_end)"/>

						</rights>
				
			</xsl:when>
			<!-- there was an embargo, but it is now over -->
			<xsl:otherwise>

				
					<rights>
					<xsl:text>PDF available</xsl:text>
				</rights>
			</xsl:otherwise>

		</xsl:choose>    
	</xsl:template>
	
	
	<xsl:template match="mods:dateOther[@type='embargoedUntil']">
		<xsl:choose> 
			<!-- embargo not yet expired -->

			<xsl:when test="current-date() &lt; xs:date(.)">
				<rights>
				Access restricted  until <xsl:value-of select="."/>
					</rights>

			</xsl:when>
			
			<xsl:otherwise>PDF is available</xsl:otherwise>
			
		</xsl:choose>

		
	</xsl:template>
	
	<xsl:template match="mods:identifier | mods:recordIdentifier | mods:location">
		<xsl:if test="@type='uri'">
			<identifier>
				<xsl:value-of select="."/>			
			</identifier>
		</xsl:if>
	</xsl:template>
	
	
	
	<xsl:template match="mods:extension">		
				
<degree>

		<name>
			<xsl:value-of select="etd:degree/etd:name"/>
		</name>	
			<level>				
				<xsl:value-of select="etd:degree/etd:level"/> 				
			</level>	
		<grantor>
			<xsl:text>Emory University</xsl:text>
		</grantor>
		
		<discipline>									
				<xsl:value-of select="etd:discipline"/>
									
		</discipline>
</degree>
	</xsl:template>
	
		
	

	<xsl:template match="mods:note"/>

	
			
</xsl:stylesheet>


