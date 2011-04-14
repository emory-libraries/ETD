#!/bin/sh
set -e

mkdir -p test-results
rm -f test-results/*
php xml_suite.php > test-results/suite.xml
../simpletest/local/clean_simpletest_xml.php test-results/suite.xml 
xsltproc ../simpletest/local/simpletest_to_junit.xsl test-results/suite.xml > test-results/TEST-suite.xml
