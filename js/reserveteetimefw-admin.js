/*
JS for Reserve Tee Time Framework (Backend)
*/
jQuery(document).ready(function() {
	/* Initiating date fields */
	if (jQuery.isFunction(jQuery.fn.datepicker)) {
		jQuery(".reserveteetimefw-datefield").datepicker({
			dateFormat: "dd-mm-yy",
			changeMonth: true,
			changeYear: true,
			yearRange: "-100:+100",
			firstDay: 1
		});
	}
});