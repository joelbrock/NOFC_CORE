<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

    This file is part of Fannie.

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

/**
  @class DrawerOwnerModel
*/
class DrawerOwnerModel extends BasicModel
{

    protected $name = "drawerowner";
    protected $preferred_db = 'op';

    protected $columns = array(
    'drawer_no' => array('type'=>'SMALLINT', 'primary_key'=>true),
    'emp_no' => array('type'=>'SMALLINT'),
	);

    /* START ACCESSOR FUNCTIONS */

    public function drawer_no()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["drawer_no"])) {
                return $this->instance["drawer_no"];
            } elseif(isset($this->columns["drawer_no"]["default"])) {
                return $this->columns["drawer_no"]["default"];
            } else {
                return null;
            }
        } else {
            $this->instance["drawer_no"] = func_get_arg(0);
        }
    }

    public function emp_no()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["emp_no"])) {
                return $this->instance["emp_no"];
            } elseif(isset($this->columns["emp_no"]["default"])) {
                return $this->columns["emp_no"]["default"];
            } else {
                return null;
            }
        } else {
            $this->instance["emp_no"] = func_get_arg(0);
        }
    }
    /* END ACCESSOR FUNCTIONS */
}

