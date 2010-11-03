<?php
/**
 * PHP Integration of Open Flash Chart
 * Copyright (C) 2008 John Glazebrook <open-flash-chart@teethgrinder.co.uk>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */

require_once('OFC/Charts/OFC_Charts_Bar.php');

class OFC_Charts_Bar_Stack_Value
{
  function OFC_Charts_Bar_Stack_Value( $val, $colour )
    {
    $this->val = $val;
    $this->colour = $colour;
  }
}

class OFC_Charts_Bar_Stack extends OFC_Charts_Bar
{
  function OFC_Charts_Bar_Stack()
    {
    parent::OFC_Charts_Bar();

    $this->type      = 'bar_stack';
  }

  function append_stack( $v )
    {
    $this->append_value( $v );
  }
  
  // This was not part of the original download of php5-ofc-library obtained from:
  // http://sourceforge.net/projects/openflashchart/files/
  // open-flash-chart-2-Lug-Wyrm-Charmer.zip (2009-07-27)
  // Added this set_keys function to allow legend key to appear that define the stacks.  
  function set_keys( $keys )
  {
    $this->keys = $keys;
  }
}

// This was not part of the original download of php5-ofc-library obtained from:
// http://sourceforge.net/projects/openflashchart/files/
// open-flash-chart-2-Lug-Wyrm-Charmer.zip (2009-07-27)
// Added this class to allow legend key to appear that define the stacks.
class bar_stack_key
{
  function bar_stack_key( $colour, $text, $font_size )
  {
    $this->colour = $colour;
    $this->text = $text;
    $tmp = 'font-size';
    $this->$tmp = $font_size;
  }
}

