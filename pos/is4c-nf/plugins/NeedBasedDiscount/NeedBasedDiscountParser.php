<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op

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

class PriceCheckParser extends Parser {
	function check($str){
		if ($str == "FF")
			return True;
		else if (substr($str,0,2)=="FF" && is_numeric(substr($str,2)))
			return False;
		return False;
	}

	function parse($str){
		$ret = $this->default_json();
		
		global $CORE_LOCAL;
		$ret = $this->default_json();

		if ($CORE_LOCAL->get("memberID") == 0){
			$ret['output'] = DisplayLib::boxMsg(_("No member selected")."<br />".
						_("Apply member number first"));
		} else {
			$plugin_info = new NeedBasedDiscount();
			$ret['main_frame'] = $plugin_info->plugin_url().'/NeedBasedDiscountPage.php';
		}
		return $ret;
	}

	function doc(){
		return "<table cellspacing=0 cellpadding=3 border=1>
			<tr>
				<th>Input</th><th>Result</th>
			</tr>
			<tr>
				<td>FF</td>
				<td>Apply Need-Based Discount</td>
			</tr>
			</table>";
	}

}

?>
