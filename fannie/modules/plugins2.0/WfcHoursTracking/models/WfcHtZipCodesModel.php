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
  @class WfcHtZipCodesModel
*/
class WfcHtZipCodesModel extends BasicModel
{

    protected $name = "zipcodes";

    protected $columns = array(
    'zip' => array('type'=>'VARCHAR(50)', 'primary_key'=>true),
    );

    /* START ACCESSOR FUNCTIONS */

    public function zip()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["zip"])) {
                return $this->instance["zip"];
            } elseif(isset($this->columns["zip"]["default"])) {
                return $this->columns["zip"]["default"];
            } else {
                return null;
            }
        } else {
            $this->instance["zip"] = func_get_arg(0);
        }
    }
    /* END ACCESSOR FUNCTIONS */
}

