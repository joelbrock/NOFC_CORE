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
class MADDiscountParser extends Parser {
	function check($str){
		if ($str == "MA") return True;
		else return False;
	}
	function parse($str){
        global $CORE_LOCAL;
        $ret = $this->default_json();
        
        if ($CORE_LOCAL->get('isMember') !== 1) {
            $ret['output'] =  DisplayLib::boxMsg(_("must be a member to use this discount"));
            return $ret;
        } elseif ($CORE_LOCAL->get('MADDiscountFlag')==1) {
            $ret['output'] = DisplayLib::boxMsg(_("discount already applied"));
            return $ret;
        } else {
            $CORE_LOCAL->set('MADDiscountFlag',1);
            Database::getsubtotals();
            $MADDisc = number_format($CORE_LOCAL->get('discountableTotal') * $CORE_LOCAL->get('MADDiscountPercent'), 2);
            $MADupc = "MADDISCOUNT";
            $MADname = $CORE_LOCAL->get('MADDiscountName');
            TransRecord::addItem("$MADupc", "$MADname", "I", "IC", "C", 0, 1, 
                -1*$MADDisc, -1*$MADDisc, -1*$MADDisc, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 30);
            $ret = $this->default_json();
            $ret['output'] = DisplayLib::lastpage();
            $ret['redraw_footer'] = True;
            return $ret;
        }
	}
}
?>
