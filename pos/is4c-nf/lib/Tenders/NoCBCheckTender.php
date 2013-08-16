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

/**
  @class NoCBCheckTender
  Tender module for checks, no cash back given.  Ever.
*/
class NoCBCheckTender extends TenderModule {

	/**
	  Check for errors
	  @return True or an error message string
	*/
	function ErrorCheck(){
		global $CORE_LOCAL;
		if( ($this->amount - $CORE_LOCAL->get("amtdue") - 0.005) > 0){ 
			return DisplayLib::xboxMsg(_('check tender cannot exceed total purchase amount'));
		}
		return True;
	}
	
	/**
	  Set up state and redirect if needed
	  @return True or a URL to redirect
	*/
	function PreReqCheck(){
		global $CORE_LOCAL;

		if ($CORE_LOCAL->get("enableFranking") != 1)
			return True;

		$ref = trim($CORE_LOCAL->get("CashierNo"))."-"
			.trim($CORE_LOCAL->get("laneno"))."-"
			.trim($CORE_LOCAL->get("transno"));

		// check endorsing
		if ($CORE_LOCAL->get("msgrepeat") == 0){
			$msg = "<br />"._("insert")." ".$this->name_string.
				' for $'.sprintf('%.2f',$this->amount).
				"<br />"._("press enter to endorse");
			$msg .= "<p><font size='-1'>"._("clear to cancel")."</font></p>";
			if ($CORE_LOCAL->get("LastEquityReference") == $ref){
				$msg .= "<div style=\"background:#993300;color:#ffffff;
					margin:3px;padding: 3px;\">
					There was an equity sale on this transaction. Did it get
					endorsed yet?</div>";
			}

			$CORE_LOCAL->set("boxMsg",$msg);
			$CORE_LOCAL->set("endorseType","check");
			$CORE_LOCAL->set("tenderamt",$this->amount);

			return MiscLib::base_url().'gui-modules/boxMsg2.php';
		}

		return True;
	}

	function Add(){
		global $CORE_LOCAL;
		// count rebate and travelers as regular checks
		if ($CORE_LOCAL->get("store")=="wfc" && ($this->tender_code == "TV" || $this->tender_code == "RC")){
			$this->tender_code = "CK";
		}
		parent::Add();
	}

}

?>
