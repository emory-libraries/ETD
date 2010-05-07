<?php

require_once("ofc/php-ofc-library/open-flash-chart.php");

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
   * @param array $bar_data values for stacked bar chart
   *  - each entry in the array should be an associative array,
   *    where the key is the degree level and the value is the total
   *  - order of arrays should match embargo labels specified
   * @param int $max size of the largest stacked bar
   */
  public function __construct($title, $x_labels, $x_legend, $bar_data, $max) {
    $this->chart = new open_flash_chart();
    $this->chart_title = new title($title);
    $this->chart_title->set_style((string)$this->style);
    $this->chart->set_title($this->chart_title);
    $this->chart->set_bg_colour((string)$this->bg_color);
    $this->x_legend = $x_legend;
    
    // color, label
    // FIXME: pull labels from common config somewhere
    $stack_labels = array("#FF7F00" => "Dissertation",
			  "#33A02c" => "Master's Thesis",
			  "#1F78B4" => "Honors Thesis");
    
    $bar_stack = new bar_stack();
    $bar_stack->set_colours(array_keys($stack_labels));
    $bar_stack_keys = array();
    foreach ($stack_labels as $color => $label) {
      $bar_stack_keys[] = new bar_stack_key($color, $label, $this->legend_fontsize);	// last arg is font size
    }
    $bar_stack->set_keys($bar_stack_keys);

    // order of stack labels has to match data;
    // access bar data data by label keys to guarantee display order matches labels
    foreach ($bar_data as $data) {
      $stack = array();
      foreach ($stack_labels as $color => $key) {
	$stack[] = $data[$key];
      }
      $bar_stack->append_stack($stack);
    }
    
    $this->chart->add_element($bar_stack);
    
    $this->set_axes($x_labels, $max);
  }


  private function set_axes($x_labels, $y_max) {
    if ($y_max > 50) {
      $steps = ceil($y_max / 50);
      $scaled_max = $steps * 50;
    } else {
      $scaled_max = 50;
    }
    
    $x_axis_labels = new x_axis_labels();  
    $x_axis_labels->set_vertical();
    $x_axis_labels->set_labels($x_labels);
    $chart_x_legend = new x_legend((string)$this->x_legend );
    $chart_x_legend->set_style((string)$this->style);
    $this->chart->set_x_legend($chart_x_legend );
    $chart_y_legend = new y_legend((string)$this->y_legend );
    $chart_y_legend->set_style((string)$this->style);  
    $this->chart->set_y_legend($chart_y_legend );
    $x = new x_axis();
    $x->set_labels($x_axis_labels);
    $this->chart->set_x_axis($x); 
    $y = new y_axis();
    $y->set_range(0, $scaled_max, 50);
    $this->chart->add_y_axis( $y );
  }

  public function toPrettyString() {
    return $this->chart->toPrettyString();
  }

}