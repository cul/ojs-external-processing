var $$externalProcessingPlugin;

jQuery(document).ready
(
	function()
	{
		$$externalProcessingPlugin = new ExternalProcessingPlugin();
	}
);

var ExternalProcessingPlugin = function()
{
	this.init = function()
	{
		jQuery("input[name='setCopyeditFile']").appendTo("#externalProcessing-specific-send");
	}
	
	this.init();
}
