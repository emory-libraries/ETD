<?php
require_once("../bootstrap.php");
require_once('models/charts.php');

class TestCharts extends UnitTestCase {

  function setUp() {
  }

  function tearDown() {
  }

  function testOpenFlashChartCreation() {

    $title = new title( "Title of Test Flash Chart" );

    $bar = new bar();
    $bar->set_values( array(9,8,7,6,5,4,3,2,1) );

    $chart = new open_flash_chart();
    $chart->set_title( $title );
    $chart->add_element( $bar );
    
    $output = $chart->toPrettyString();
    
    // $chart->toPrettyString() returns:
    // { "elements": [ { "type": "bar", "values": [ 9, 8, 7, 6, 5, 4, 3, 2, 1 ] } ], "title": { "text": "Title of Test Flash Chart" } }';
    $this->assertPattern('/elements/', $output, "Test for 'elements' pattern");     
    $this->assertPattern('/bar/', $output, "Test for 'bar' pattern"); 
    $this->assertPattern('/title/', $output, "Test for 'title' pattern");    
    $this->assertPattern('/Title of Test Flash Chart/', $output, "Test for title pattern");
  }
}

runtest(new TestCharts());
?>
