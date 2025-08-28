(function($){
	$(document).ready(function(){
		$('.wp2-theme-health-check').each(function(){
			var $container = $(this);
			var slug = $container.data('slug');
			$container.html('<span class="spinner is-active"></span> Checking...');
			$.post(ajaxurl, {
				action: 'wp2_theme_health_check',
				slug: slug,
				_wpnonce: wp2ThemeAdmin.nonce
			}, function(response){
				$container.html(response.data.html);
			});
		});
	});
})(jQuery);
