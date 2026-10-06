(function(){
	'use strict';

	document.addEventListener('submit',function(event){
		var form=event.target;
		if(form.classList.contains('hm-mahex-delete')&&!window.confirm(form.dataset.confirm||'')){event.preventDefault();}
	});

	function row(element){return element?element.closest('.form-field,p.form-row'):null;}
	function toggleRow(container,name,visible){
		var element=container.querySelector('[name="'+name.replace(/"/g,'\\"')+'"]');
		var wrapper=row(element);
		if(wrapper){wrapper.hidden=!visible;}
	}
	function refresh(select){
		var name=select.getAttribute('name')||'';
		var suffix=name.substring('_hm_mahex_packaging_mode'.length);
		var container=select.closest('.woocommerce_variation,.woocommerce_options_panel')||document;
		if(name.indexOf('_hm_mahex_packaging_mode')===0){
			toggleRow(container,'_hm_mahex_profile_id'+suffix,select.value==='profile');
			['_hm_mahex_length_mm','_hm_mahex_width_mm','_hm_mahex_height_mm'].forEach(function(field){toggleRow(container,field+suffix,select.value==='custom');});
		}
		if(name.indexOf('_hm_mahex_value_mode')===0){toggleRow(container,'_hm_mahex_custom_value'+name.substring('_hm_mahex_value_mode'.length),select.value==='custom');}
	}
	function refreshAll(){document.querySelectorAll('select[name^="_hm_mahex_packaging_mode"],select[name^="_hm_mahex_value_mode"]').forEach(refresh);}
	document.addEventListener('change',function(event){if(event.target.matches('select[name^="_hm_mahex_packaging_mode"],select[name^="_hm_mahex_value_mode"]')){refresh(event.target);}});
	document.addEventListener('DOMContentLoaded',refreshAll);
	if(window.jQuery){window.jQuery(document.body).on('woocommerce_variations_loaded woocommerce_variations_added',refreshAll);}
}());
