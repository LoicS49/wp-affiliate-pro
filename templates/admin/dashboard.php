<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php _e( 'WP Affiliate Pro Dashboard', 'wp-affiliate-pro' ); ?></h1>

	<div class="wpap-dashboard-stats">
		<div class="wpap-stat-cards">
			<div class="wpap-stat-card">
				<div class="wpap-stat-icon">
					<span class="dashicons dashicons-groups"></span>
				</div>
				<div class="wpap-stat-content">
					<h3><?php echo number_format( $stats['total_affiliates'] ); ?></h3>
					<p><?php _e( 'Total Affiliates', 'wp-affiliate-pro' ); ?></p>
					<span class="wpap-stat-detail">
						<?php printf( __( '%d Active | %d Pending', 'wp-affiliate-pro' ), $stats['active_affiliates'], $stats['pending_affiliates'] ); ?>
					</span>
				</div>
			</div>

			<div class="wpap-stat-card">
				<div class="wpap-stat-icon">
					<span class="dashicons dashicons-money-alt"></span>
				</div>
				<div class="wpap-stat-content">
					<h3><?php echo wpap_format_amount( $stats['total_commissions'] ); ?></h3>
					<p><?php _e( 'Total Commissions', 'wp-affiliate-pro' ); ?></p>
					<span class="wpap-stat-detail">
						<?php printf( __( '%s Pending | %s Paid', 'wp-affiliate-pro' ), 
							wpap_format_amount( $stats['pending_commissions'] ), 
							wpap_format_amount( $stats['paid_commissions'] ) 
						); ?>
					</span>
				</div>
			</div>

			<div class="wpap-stat-card">
				<div class="wpap-stat-icon">
					<span class="dashicons dashicons-clipboard"></span>
				</div>
				<div class="wpap-stat-content">
					<h3><?php echo wpap_format_amount( $stats['total_payments'] ); ?></h3>
					<p><?php _e( 'Total Payments', 'wp-affiliate-pro' ); ?></p>
					<span class="wpap-stat-detail">
						<?php printf( __( '%s Pending', 'wp-affiliate-pro' ), wpap_format_amount( $stats['pending_payments'] ) ); ?>
					</span>
				</div>
			</div>

			<div class="wpap-stat-card">
				<div class="wpap-stat-icon">
					<span class="dashicons dashicons-chart-line"></span>
				</div>
				<div class="wpap-stat-content">
					<h3><?php echo number_format( ( $stats['active_affiliates'] / max( $stats['total_affiliates'], 1 ) ) * 100, 1 ); ?>%</h3>
					<p><?php _e( 'Approval Rate', 'wp-affiliate-pro' ); ?></p>
					<span class="wpap-stat-detail">
						<?php _e( 'Last 30 days', 'wp-affiliate-pro' ); ?>
					</span>
				</div>
			</div>
		</div>
	</div>

	<div class="wpap-dashboard-widgets">
		<div class="wpap-widget wpap-widget-large">
			<div class="wpap-widget-header">
				<h3><?php _e( 'Commission Overview', 'wp-affiliate-pro' ); ?></h3>
				<select id="wpap-chart-period">
					<option value="last_7_days"><?php _e( 'Last 7 Days', 'wp-affiliate-pro' ); ?></option>
					<option value="last_30_days" selected><?php _e( 'Last 30 Days', 'wp-affiliate-pro' ); ?></option>
					<option value="this_month"><?php _e( 'This Month', 'wp-affiliate-pro' ); ?></option>
				</select>
			</div>
			<div class="wpap-widget-content">
				<canvas id="wpap-commission-chart"></canvas>
			</div>
		</div>

		<div class="wpap-widget">
			<div class="wpap-widget-header">
				<h3><?php _e( 'Recent Affiliates', 'wp-affiliate-pro' ); ?></h3>
				<a href="<?php echo admin_url( 'admin.php?page=wpap-affiliates' ); ?>" class="wpap-widget-link">
					<?php _e( 'View All', 'wp-affiliate-pro' ); ?>
				</a>
			</div>
			<div class="wpap-widget-content">
				<?php if ( ! empty( $recent_affiliates ) ) : ?>
					<table class="wpap-table">
						<thead>
							<tr>
								<th><?php _e( 'Affiliate', 'wp-affiliate-pro' ); ?></th>
								<th><?php _e( 'Status', 'wp-affiliate-pro' ); ?></th>
								<th><?php _e( 'Registered', 'wp-affiliate-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_affiliates as $affiliate ) : 
								$user = get_user_by( 'id', $affiliate->user_id );
							?>
								<tr>
									<td>
										<?php echo esc_html( $user ? $user->display_name : __( 'Unknown', 'wp-affiliate-pro' ) ); ?>
										<br><small><?php echo esc_html( $affiliate->referral_code ); ?></small>
									</td>
									<td>
										<span class="wpap-status wpap-status-<?php echo esc_attr( $affiliate->status ); ?>">
											<?php echo esc_html( ucfirst( $affiliate->status ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( date( 'M j, Y', strtotime( $affiliate->date_registered ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php _e( 'No recent affiliates found.', 'wp-affiliate-pro' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="wpap-widget">
			<div class="wpap-widget-header">
				<h3><?php _e( 'Top Performers', 'wp-affiliate-pro' ); ?></h3>
			</div>
			<div class="wpap-widget-content">
				<?php if ( ! empty( $top_affiliates ) ) : ?>
					<table class="wpap-table">
						<thead>
							<tr>
								<th><?php _e( 'Affiliate', 'wp-affiliate-pro' ); ?></th>
								<th><?php _e( 'Earnings', 'wp-affiliate-pro' ); ?></th>
								<th><?php _e( 'Commissions', 'wp-affiliate-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $top_affiliates as $affiliate ) : 
								$user = get_user_by( 'id', $affiliate->user_id );
							?>
								<tr>
									<td>
										<?php echo esc_html( $user ? $user->display_name : __( 'Unknown', 'wp-affiliate-pro' ) ); ?>
										<br><small><?php echo esc_html( $affiliate->referral_code ); ?></small>
									</td>
									<td><?php echo wpap_format_amount( $affiliate->total_earnings ?: 0 ); ?></td>
									<td><?php echo number_format( $affiliate->total_commissions ?: 0 ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php _e( 'No top performers found.', 'wp-affiliate-pro' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Initialize commission chart
	var ctx = document.getElementById('wpap-commission-chart').getContext('2d');
	var chart = new Chart(ctx, {
		type: 'line',
		data: {
			labels: [],
			datasets: [{
				label: '<?php _e( 'Commission Amount', 'wp-affiliate-pro' ); ?>',
				data: [],
				borderColor: '#0073aa',
				backgroundColor: 'rgba(0, 115, 170, 0.1)',
				borderWidth: 2,
				fill: true
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			scales: {
				y: {
					beginAtZero: true,
					ticks: {
						callback: function(value) {
							return '$' + value.toFixed(2);
						}
					}
				}
			}
		}
	});

	// Load initial chart data
	loadChartData('last_30_days');

	// Handle period change
	$('#wpap-chart-period').change(function() {
		loadChartData($(this).val());
	});

	function loadChartData(period) {
		$.post(ajaxurl, {
			action: 'wpap_dashboard_stats',
			period: period,
			nonce: wpap_admin.nonce
		}, function(response) {
			if (response.success && response.data.chart_data) {
				chart.data.labels = response.data.chart_data.labels;
				chart.data.datasets[0].data = response.data.chart_data.data;
				chart.update();
			}
		});
	}
});
</script>