<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

    This file is part of IT CORE.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

global $FANNIE_ROOT;
if (!class_exists('FannieAPI'))
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');

/**
*/
class ReverseTransaction extends FanniePlugin {

    /**
      Desired settings. These are automatically exposed
      on the 'Plugins' area of the install page and
      written to ini.php
    */
    public $plugin_settings = array(
    'ReversalEmployee' => array('default'=>'1001','label'=>'Employee #',
            'description'=>'Employee number to use when posting
            reversal transactions'),
    'ReversalLane' => array('default'=>'30','label'=>'Register #',
            'description'=>'Register number to use when posting
            reversal transactions')
    );

    public $plugin_description = 'Plugin for posting reversal transaction. A reversal is
            essentially the original transaction times minus one. Used in correcting
            and cleaning up after mistakes.';
}

?>
