if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit;

delete_option( 'feed_statistics_version' );
delete_option( 'feed_statistics_track_clickthroughs' );
delete_option( 'feed_statistics_track_postviews' );
delete_option( 'feed_statistics_expiration_days' );

$wpdb->query( "DELETE TABLE ".$wpdb->prefix."feed_clickthroughs" );
$wpdb->query( "DELETE TABLE ".$wpdb->prefix."feed_links" );
$wpdb->query( "DELETE TABLE ".$wpdb->prefix."feed_postviews" );
$wpdb->query( "DELETE TABLE ".$wpdb->prefix."feed_subscribers" );