(function($){
	$(document).ready(function(){
		var healthChecks = [
			{ selector: '.wp2-theme-health-check', action: 'wp2_theme_health_check', nonce: window.wp2ThemeAdmin ? window.wp2ThemeAdmin.nonce : null },
			{ selector: '.wp2-plugin-health-check', action: 'wp2_plugin_health_check', nonce: window.wp2PluginAdmin ? window.wp2PluginAdmin.nonce : null },
			{ selector: '.wp2-daemon-health-check', action: 'wp2_daemon_health_check', nonce: window.wp2DaemonAdmin ? window.wp2DaemonAdmin.nonce : null }
		];
		healthChecks.forEach(function(cfg){
			$(cfg.selector).each(function(){
				var $container = $(this);
				var slug = $container.data('slug');
				$container.html('<span class="spinner is-active"></span> Checking...');
				$.post(ajaxurl, {
					action: cfg.action,
					slug: slug,
					_wpnonce: cfg.nonce
				}, function(response){
					$container.html(response.data.html);
				});
			});
		});

		// Bulk select all
		$('#wp2-bulk-select-all').on('change', function(){
			$('.wp2-bulk-select').prop('checked', $(this).prop('checked'));
		});

		// Bulk update button
		$('#wp2-bulk-update-btn').on('click', function(e){
			e.preventDefault();
			var selected = [];
			$('.wp2-bulk-select:checked').each(function(){
				selected.push($(this).val());
			});
			if(selected.length === 0){
				alert('No packages selected.');
				return;
			}
			$(this).prop('disabled', true).text('Updating...');
			$.post(wp2_updater_ajax.ajax_url, {
				action: 'wp2_bulk_update',
				packages: selected,
				_wpnonce: wp2_updater_ajax.nonce
			}, function(response){
				$('#wp2-bulk-update-btn').prop('disabled', false).text('Bulk Update Selected');
				if(response.success){
					alert('Bulk update completed: ' + response.data.message);
					location.reload();
				}else{
					alert('Bulk update failed: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
				}
			});
		});

		// Scroll and highlight focused package if set
		if(window.wp2DashboardFocus){
			var el = $('[data-slug="' + window.wp2DashboardFocus + '"]');
			if(el.length){
				$('html, body').animate({scrollTop: el.offset().top-100}, 500);
				el.css('box-shadow','0 0 0 2px #007cba');
			}
		}
	});
})(jQuery);
