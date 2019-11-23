var $ = jQuery;

$(function(){
	//p_cf_
	
	var p_cf_token_name = p_cf_get_token_name();
	var p_cf_data = {
		action: 'is_user_logged_in'
	};
	
	p_cf_data[p_cf_token_name] = p_cf_get_token();
	p_cf_data.url = window.location.href;
	
	var p_cf_ajaxUrl = p_cf_ajax['ajax_url'];
	
	if(p_cf_isPublic() && p_cf_get_token() != '')
	{
		$.post(p_cf_ajaxUrl, p_cf_data, function(data_1) {
			
			//is_logged
			if(data_1.is_logged === true)
			{
				console.log(data_1);
				
				if(data_1.nonce_verified === false && data_1.hasOwnProperty('c_p_nonce_new'))
				{
					p_cf_data[p_cf_token_name] = data_1.c_p_nonce_new;
					
					$.post(p_cf_ajaxUrl, p_cf_data, function(data_2){
						
						if(data_2.is_logged === true && data_2.nonce_verified === false)
						{
							console.log(data_2);
						}
					});
				}
			}
		});		
	}
	else if(p_cf_isPublic() === false)
	{
		p_cf_new_token(p_cf_ajax);
		console.log(p_cf_get_token());
	}
});

function p_cf_new_token(p_cf_ajax)
{
	var p_cf_domain = btoa(window.location.hostname);
	p_cf_domain.substring(0, 10);
	localStorage.setItem(p_cf_get_token_name(), p_cf_ajax['token']);	
}
function p_cf_get_token()
{
	var p_cf_domain = btoa(window.location.hostname);
	return localStorage.getItem(p_cf_get_token_name());
}
function p_cf_get_token_name()
{
	var p_cf_domain = btoa(window.location.hostname);
	return 'p_cf_u_' + p_cf_domain.substring(0, 10);
}

function p_cf_isPublic()
{
	var output = false;
	
	if(!$('body').hasClass('wp-admin'))
	{
		output = true;
	}
	
	return output;
}

function p_cf_hasAdminBar()
{
	var output = false;
	
	if($('body').hasClass('admin-bar'))
	{
		output = true;
	}

	return output;	
}