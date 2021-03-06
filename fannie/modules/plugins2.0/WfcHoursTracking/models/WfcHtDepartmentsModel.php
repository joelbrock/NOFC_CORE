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
  @class WfcHtDepartmentsModel
*/
class WfcHtDepartmentsModel extends BasicModel
{

    protected $name = "Departments";

    protected $columns = array(
    'deptID' => array('type'=>'INT', 'primary_key'=>true),
    'name' => array('type'=>'VARCHAR(255)'),
    );

    /* START ACCESSOR FUNCTIONS */

    public function deptID()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["deptID"])) {
                return $this->instance["deptID"];
            } elseif(isset($this->columns["deptID"]["default"])) {
                return $this->columns["deptID"]["default"];
            } else {
                return null;
            }
        } else {
            $this->instance["deptID"] = func_get_arg(0);
        }
    }

    public function name()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["name"])) {
                return $this->instance["name"];
            } elseif(isset($this->columns["name"]["default"])) {
                return $this->columns["name"]["default"];
            } else {
                return null;
            }
        } else {
            $this->instance["name"] = func_get_arg(0);
        }
    }
    /* END ACCESSOR FUNCTIONS */
}

