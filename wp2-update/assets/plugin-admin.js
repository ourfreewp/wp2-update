(function($){
	$(document).ready(function(){
		$('.wp2-plugin-health-check').each(function(){
			var $container = $(this);
			var slug = $container.data('slug');
			$container.html('<span class="spinner is-active"></span> Checking...');
			$.post(ajaxurl, {
				action: 'wp2_plugin_health_check',
				slug: slug,
				_wpnonce: wp2PluginAdmin.nonce
			}, function(response){
				$container.html(response.data.html);
			});
		});
	});
})(jQuery);
