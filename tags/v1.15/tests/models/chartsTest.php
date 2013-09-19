<?php
require_once("../bootstrap.php");
require_once('models/charts.php');

class TestCharts extends UnitTestCase {

  function setUp() {
  }

  function tearDown() {
  }

  function testOpenFlashChartCreation() {

    $bar_stack = new OFC_Charts_Bar_Stack();
    $bar_stack->set_values( array(9,8,7,6,5,4,3,2,1) );

    $chart = new OFC_Chart();
    $chart_title = new OFC_Elements_Title("Title of Test Flash Chart");
    $chart->set_title($chart_title);
    $chart->add_element( $bar_stack );
    
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
