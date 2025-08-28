(function($){
	$(document).ready(function(){
		$('.wp2-daemon-health-check').each(function(){
			var $container = $(this);
			var slug = $container.data('slug');
			$container.html('<span class="spinner is-active"></span> Checking...');
			$.post(ajaxurl, {
				action: 'wp2_daemon_health_check',
				slug: slug,
				_wpnonce: wp2DaemonAdmin.nonce
			}, function(response){
				$container.html(response.data.html);
			});
		});
	});
})(jQuery);
