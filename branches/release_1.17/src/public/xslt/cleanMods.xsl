<?xml version="1.0" encoding="UTF-8"?>

<!--
    Document   : cleanMods.xsl
    Created on : August 12, 2009, 11:25 AM
    Author     : rsutton
    Description:
        Convert ETD-specific MODS to a cleaned up version suitable for dissemination and harvest
-->

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="2.0"
    xmlns:mods="http://www.loc.gov/mods/v3"
    xmlns:xs = "http://www.w3.org/2001/XMLSchema"
    exclude-result-prefixes="xs" >
    <xsl:output method="xml"/>
    
    <!-- remove partneragencytype notes from output -->
    <xsl:template match="mods:note[@type='partneragencytype']"/>     

    <!-- remove administrative notes from output -->
    <xsl:template match="mods:note[@type='admin']"/>

    <!-- remove embargo end date from output -->
    <xsl:template match="mods:dateOther[@type='embargoedUntil']"/>

    <!-- remove empty names -->
    <xsl:template match="mods:name[@type='personal'][mods:namePart[@type='given'] = ''
        and mods:namePart[@type='family'] = '' and normalize-space(mods:displayForm) = ',']"/>

    <!-- do not disseminate internal person ids -->
    <xsl:template match="mods:name[@type='personal']/@ID"/>


  <xsl:template match="mods:accessCondition[@type='restrictionOnAccess']">

    <xsl:variable name="embargo_end">
      <xsl:choose>
        <!-- some old records have date in W3C format; detect and convert to expected format -->
        <xsl:when test="contains(//mods:dateOther[@type='embargoedUntil'], 'T')">
          <xsl:value-of select="substring-before(//mods:dateOther[@type='embargoedUntil'], 'T')"/>
        </xsl:when>
        <xsl:otherwise>
          <xsl:value-of select="//mods:dateOther[@type='embargoedUntil']"/>
        </xsl:otherwise>
      </xsl:choose>
    </xsl:variable>

        <xsl:choose>
            <!-- if embargo is for 0 days, not set, or embargo end is not set, do nothing;
                 don't output restrictionOnAccess  -->
            <xsl:when test="contains(., '0 days') or . = '' or $embargo_end = ''"/>
            <!-- embargo is non-zero and end date is still in the future -->
            <xsl:when test="current-date() &lt; xs:date($embargo_end)">
                <xsl:copy>
                    <xsl:apply-templates select="@*"/> 
                    <xsl:value-of select="concat('Access to PDF is restricted until ', $embargo_end)"/>
                </xsl:copy>
            </xsl:when>
            <!-- there was an embargo, but it is now over -->
            <xsl:otherwise>
                <xsl:copy>
                    <xsl:apply-templates select="@*"/>
                    <xsl:text>PDF available</xsl:text>
                </xsl:copy>
            </xsl:otherwise>
        </xsl:choose>    
  </xsl:template>

    <xsl:template match="@*|node()">
    <xsl:copy>
      <xsl:apply-templates select="@*"/>
      <xsl:apply-templates/>
    </xsl:copy>
  </xsl:template>
</xsl:stylesheet>
