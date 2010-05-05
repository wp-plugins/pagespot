/*  Copyright 2010 Nick Eby (email:nick@pixelnix.com)

    This file is part of PageSpot.
    
    PageSpot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
    
    PageSpot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    
    You should have received a copy of the GNU General Public License
    along with PageSpot.  If not, see <http://www.gnu.org/licenses/>.
*/


function pagespot_admin_submit(theForm) {
    jQuery(theForm).find("input[type=submit]").hide();
    jQuery("#pagespot_wait").show();
    try {
	    jQuery.post(
	        jQuery(theForm).attr("action"), 
	        {
		        action: "pagespot_save_options",
		        ps_post_templating: jQuery(theForm).find("input[name=ps_post_templating]:checked").val()
	        }, 
	        function(data) {
			    jQuery(theForm).find("input[type=submit]").show();
			    jQuery("#pagespot_wait").hide();
		        if (!data) {
                    alert('Options save failed');
		        }
		        else {
					if (data['errors']) {
					    alert(data['errors']);
					}
					else {
                        alert('Options saved!');
					}
		        }
		    },
		    "json"
	    );
    } catch (e) {
        alert(e);
    }
    
    return false;
}

function del_m2lposition(id) {
    if (confirm('Really delete?')) {
	    try {
	        jQuery.post(
	            jQuery('#m2l_position_form').attr("action"),
	            {
	                action: "m2l_del_position",
	                position_id: id
	            },
	            function(data) {
	                if (data['errors'] != '') {
	                    alert(data['errors']);
	                }
	                else {
	                    jQuery('#m2l_position_tr_'+id).hide('slow');
	                    jQuery('#m2l_position_tr_'+id).remove();
	                }
	            },
	            "json"
	        );
	    } catch (e) {}
    }
    
    return false;
}