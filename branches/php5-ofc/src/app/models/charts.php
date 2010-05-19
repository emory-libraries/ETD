<?php

require_once('OFC/OFC_Chart.php');

class stacked_bar_chart  {
  private $chart;
  private $chart_title;
  private $legend_fontsize = 12;
  public $x_legend;
  public $y_legend = 'Number of Records';

  private $style = '{font-size: 14px; color: #333333; font-weight:bold}';
  private $bg_color = "#ffffff";

  /**
   * generate an embargo-request open flash chart
   *
   * @param string $title chart title
   * @param array $x_labels labels for the x axis (embargo durations)
   * @param string $x_legend legend/label for the x-axis
   * @param array $data values for stacked bar chart
   *  - each entry in the array should be an associative array,
   *    where the key is the degree level and the value is the total
   *  - order of arrays should match embargo labels specified
   * @param int $max size of the largest stacked bar
   * @param array doctypes a list of the document types.
   *  - (ie. Dissertation, Master's Thesis, Honors Thesis)
   */
  
  public function __construct($title, $x_labels, $x_legend, $data, $max, $doctypes) {    
    
    $this->chart = new OFC_Chart();
       
    # define the colors for the bar sections
    $bar_colors = array("Dissertation" => "#FF7F00",
        "Master's Thesis" => "#33A02c",
        "Honors Thesis" => "#1F78B4");  

    $this->chart_title = $title = new OFC_Elements_Title($title);
    $this->chart_title->set_style((string)$this->style);
    $this->chart->set_title($this->chart_title);
   
    $this->chart->set_bg_colour((string)$this->bg_color);
    $this->x_legend = $x_legend;
        
    $bar_stack = new OFC_Charts_Bar_Stack();        

    list($bar_data, $max) = $this->segment_barchart_data($data, $max, $doctypes); 
         
    # define color coded bar stack legend/key.
    $bar_stack_keys = array();
    foreach ($doctypes as $key) {
      $bar_stack_keys[] = new bar_stack_key($bar_colors[$key], $key, $this->legend_fontsize);  // last arg is font size
    }
    $bar_stack->set_keys($bar_stack_keys);

    // get the data for each bar stack
    foreach ($bar_data as $data) {
      $stack = array();
      foreach ($doctypes as $key) {
        array_push($stack, new OFC_Charts_Bar_Stack_Value($data[$key], $bar_colors[$key]));
      }
      $bar_stack->append_stack($stack);
    }
 
    $this->chart->add_element($bar_stack);
       
    // set the x and y intervals and labels for the chart.
    $this->set_axes($x_labels, $max);
  }

  /**
   * convert data into format needed for bar chart, segmented by document type
   * @param array $data
   *  - one entry in the array for each document type, each with a list of $num values
   * @param int $num  number of sets of data
   * @return array data, maximum value
   */
  private function segment_barchart_data($data, $num, $doctypes) {
    $max = 0;
    $all_data = array();
    for ($i = 0; $i < $num; $i++) {
      $bar_data = array();
      foreach ($doctypes as $doc_type) {
        // don't add zeroes (messes up the tool tips)
        if ($data[$doc_type][$i])  $bar_data[$doc_type] = $data[$doc_type][$i];
      }
      $current_total = array_sum(array_values($bar_data));
      if ($current_total > $max) $max = $current_total;
      $all_data[] = $bar_data;      
    }
    return array($all_data, $max);
  }

  /**
   * calculate the intervals and labels for the x and y axes of the chart.
   * @param array $x_labels - labels for the X axis.
   * @param int $y_max  - max value of the Y axis.
   */
  private function set_axes($x_labels, $y_max) { 

    if ($y_max > 50) {
      $steps = ceil($y_max / 50);
      $scaled_max = $steps * 50;
    } else {
      $scaled_max = 50;
    }
    
    $chart_x_legend = new OFC_Elements_Legend_X((string)$this->x_legend );
    $chart_x_legend->set_style((string)$this->style);
    $this->chart->set_x_legend($chart_x_legend );
    $chart_y_legend = new OFC_Elements_Legend_Y((string)$this->y_legend );
    $chart_y_legend->set_style((string)$this->style);  
    $this->chart->set_y_legend($chart_y_legend );
    $x = new OFC_Elements_Axis_X();
    $x->set_labels_from_array($x_labels);
    $this->chart->set_x_axis($x); 
    $y = new OFC_Elements_Axis_Y();
    $y->set_range(0, $scaled_max, 50);
    $this->chart->add_y_axis( $y );
  }

  public function toPrettyString() {
    return $this->chart->toPrettyString();
  }

}

