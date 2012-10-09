<?php

/*
Plugin Name: Feed Statistics
Plugin URI: http://www.chrisfinke.com/wordpress/plugins/feed-statistics/
Description: Compiles statistics about who is reading your blog via a feed reader and what posts they're reading.
Version: 2.0pre
Author: Christopher Finke
Author URI: http://www.chrisfinke.com/
License: GPL2
*/

define( 'FEED_STATISTICS_VERSION', '2.0pre' );

if (preg_match("/feed\-statistics\.php$/", $_SERVER["PHP_SELF"])) {
	/**
	 * Deprecated. Versions before 2.0 sent requests directly to this file, which
	 * required hacky ways of loading some of the WordPress core stuff. Version 2
	 * does everything in an init action to catch these views and redirects.
	 * This code will be removed in the next version; it's left in only to catch
	 * views and redirects from stored feeds.
	 */
	
	if (!defined('DB_NAME')) {
		$root = __FILE__;
		$i = 1;
		
		while ($root = dirname($root)) {
			if (file_exists($root . "/wp-load.php")) {
				require_once($root . "/wp-load.php");
				break;
			}
			else if (file_exists($root . "/wp-config.php")) {
				require_once($root . "/wp-config.php");
				break;
			}
			
			if ($root == '/') {
				if (isset($_GET["url"])){
					$url = base64_decode($_GET["url"]);
					header("Location: ".$url);
					return;	
				}
				else if (isset($_GET["view"])) {
					header("Content-Type: image/gif");
					echo base64_decode("R0lGODlhAQABAIAAANvf7wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==");
					return;
				}
				
				die;
			}
		}
	}
	
	if (isset($_GET["view"])){
		if (!empty($_GET["post_id"]) && get_option("feed_statistics_track_postviews")){
			global $wpdb;
			
			$sql = "INSERT INTO `".$wpdb->prefix."feed_postviews`
				SET 
					`post_id`=".intval($_GET["post_id"]).",
					`time`=NOW()";
			$wpdb->query($sql);
		}
	
		header("Content-Type: image/gif");
		echo base64_decode("R0lGODlhAQABAIAAANvf7wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==");
		return;
	}
	
	if (isset($_GET["url"])){
		$url = base64_decode($_GET["url"]);
		
		if (get_option("feed_statistics_track_clickthroughs")){
			if (trim($url) == '') die;

			global $wpdb;
			$link_id = 0;
		
			$wpdb->hide_errors();
			$sql = "SELECT `id` FROM `".$wpdb->prefix."feed_links` WHERE `url`='".mysql_real_escape_string($url)."'";
			$result = $wpdb->query($sql);
		
			if ($result) {
				$link_id = $wpdb->last_result[0]->id;
			}
			else {
				$sql = "INSERT INTO `".$wpdb->prefix."feed_links` SET `url`='".mysql_real_escape_string($url)."', `url_hash`='".md5( $url ) ."'";
		
				if ($wpdb->query($sql)) {
					$link_id = $wpdb->insert_id;
				}
			}
		
			if ($link_id) {
				$sql = "INSERT INTO `".$wpdb->prefix."feed_clickthroughs` SET
					`link_id`=".intval($link_id).",
					`time`=NOW()";
				$wpdb->query($sql);
			}
		}
	
		$wpdb->show_errors();
	
		header("Location: ".$url);
		return;
	}
}

class FEED_STATS {
	static function init(){
		global $wpdb;
		
		if ( isset( $_GET['feed-stats-post-id'] ) ) {
			if ( ! empty( $_GET['feed-stats-post-id'] ) && get_option( "feed_statistics_track_postviews" ) ) {
				$wpdb->insert(
					$wpdb->prefix . 'feed_postviews',
					array(
						'post_id' => $_GET['feed-stats-post-id'],
						'time' => date( 'Y-m-d H:i:s' )
					),
					array(
						'%d',
						'%s'
					)
				);
			}

			header("Content-Type: image/gif");
			echo base64_decode("R0lGODlhAQABAIAAANvf7wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==");
			die();
		}

		if ( isset( $_GET['feed-stats-url'] ) ) {
			$url = trim( base64_decode( $_GET['feed-stats-url'] ) );
			
			if ( ! $url ) die;
			
			if ( get_option( 'feed_statistics_track_clickthroughs' ) ) {
				$link_id = 0;

				$wpdb->hide_errors();
				
				$link_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM " . $wpdb->prefix . "feed_links WHERE url=%d",
						$url
					)
				);
				
				if ( ! $link_id ) {
					if (
						$wpdb->insert(
							$wpdb->prefix . 'feed_links',
							array(
								'url' => $url,
								'url_hash' => md5( $url )
							),
							array( '%s', '%s' )
						)
					) {
						$link_id = $wpdb->insert_id;
					}
				}

				if ( $link_id ) {
					$wpdb->insert(
						$wpdb->prefix . 'feed_clickthroughs',
						array(
							'link_id' => $link_id,
							'time' => date( 'Y-m-d H:i:s' )
						),
						array(
							'%d',
							'%s'
						)
					);
				}
			}

			$wpdb->show_errors();

			header( 'Location: ' . $url );
			die();
		}
		
		if ( isset( $_POST["feed_statistics_update"] ) ) {
			// Handle settings changes here so that the menus can show the right options.
			update_option( "feed_statistics_expiration_days", intval( $_POST["feed_statistics_expiration_days"] ) );
			update_option( "feed_statistics_track_clickthroughs", intval( isset( $_POST["feed_statistics_track_clickthroughs"] ) ) );
			update_option( "feed_statistics_track_postviews", intval( isset( $_POST["feed_statistics_track_postviews"] ) ) );
		} 
		
		load_plugin_textdomain( 'feed-statistics', false, dirname( __FILE__ ) . '/languages' );
		
		if (FEED_STATS::is_feed_url()){
			$user_agent = $_SERVER["HTTP_USER_AGENT"];
			
			if (!preg_match("/(Mozilla|Opera|subscriber|user|feed)/Uis", $user_agent)){
				if (strlen($user_agent) > 3){
					return;
				}
			}
			
			if (!preg_match("/(readers|subscriber|user|feed)/Uis", $user_agent)){
				if (preg_match("/(slurp|bot|spider)/Uis", $user_agent)){
					return;
				}
			}
	
			$m = array();
			
			if (preg_match("/([0-9]+) subscriber/Uis", $user_agent, $m)) {
				// Not a typo below - should have been replacing $m[1], but screwed it up the first time around, so it's here to stay
				$identifier = str_replace($m[0], "###", $user_agent);
				$subscribers = $m[1];
			}
			else if (preg_match("/users ([0-9]+);/Uis", $user_agent, $m)) {
				// For Yahoo!'s bot
				$identifier = str_replace($m[1], "###", $user_agent);
				$subscribers = $m[1];
			}
			else if (preg_match("/ ([0-9]+) readers/Uis", $user_agent, $m)) {
				// For LiveJournal's bot
				$identifier = str_replace($m[1], "###", $user_agent);
				$subscribers = $m[1];
			}
			else {
				$identifier = $_SERVER["REMOTE_ADDR"];
				$subscribers = 1;
			}
			
			$feed = $_SERVER["REQUEST_URI"];
			
			if (!preg_match("/(\/|\.php|\?.*)$/Uis", $feed)){
				$feed .= "/";
			}
			
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ".$wpdb->prefix."feed_subscribers WHERE identifier=%s AND feed=''", $identifier ) );
		
			if ( ! empty( $results ) ) {
				$wpdb->update(
					$wpdb->prefix . 'feed_subscribers',
					array(
						'subscribers' => $susbcribers,
						'user_agent' => $user_agent,
						'feed' => $feed,
						'date' => date( 'Y-m-d H:i:s' )
					),
					array(
						'identifier' => $identifier,
						'feed' => ''
					),
					array(
						'%d',
						'%s',
						'%s',
						'%s'
					),
					array(
						'%s',
						'%s'
					)
				);
			}
			else {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "feed_subscribers WHERE identifier=%s AND feed=%s", $identifier, $feed ) );
				
				if ( empty( $row ) ) {
					$wpdb->insert(
						$wpdb->prefix . 'feed_subscribers',
						array(
							'subscribers' => $subscribers,
							'identifier' => $identifier,
							'user_agent' => $user_agent,
							'feed' => $feed,
							'date' => date( 'Y-m-d H:i:s' )
						),
						array(
							'%d',
							'%s',
							'%s',
							'%s',
							'%s'
						)
					);
				}
				else if ( $user_agent != $row->user_agent || $subscribers != $row->subscribers ) {
					$wpdb->update(
						$wpdb->prefix . 'feed_subscribers',
						array(
							'date' => date( 'Y-m-d H:i:s' ),
							'user_agent' => $user_agent,
							'subscribers' => $subscribers
						),
						array(
							'identifier' => $identifier,
							'feed' => $feed
						),
						array(
							'%s',
							'%s',
							'%d'
						),
						array(
							'%s',
							'%s'
						)
					);
				}
			}
		}
	}
	
	static function db_setup() {
		$installed_version = get_option( 'feed_statistics_version' );
		
		if ( FEED_STATISTICS_VERSION != $installed_version ) {
			FEED_STATS::sql();
			
			update_option( 'feed_statistics_version', FEED_STATISTICS_VERSION );
			
			add_option( 'feed_statistics_track_clickthroughs', '0' );
			add_option( 'feed_statistics_track_postviews',     '1' );
			add_option( 'feed_statistics_expiration_days',     '3' );
		}
	}
	
	static function sql() {
		global $wpdb;
		
		$last_version = get_option( 'feed_statistics_version' );
		
		switch ( $last_version ) {
			case '1.0':
			case '1.0.1':
			case '1.0.2':
			case '1.0.3':
			case '1.0.4':
				$sql = "ALTER TABLE `".$wpdb->prefix."feed_subscribers` ADD `user_agent` VARCHAR(255) NOT NULL DEFAULT ''";
				$wpdb->query($sql);
				
				$sql = "CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."feed_clickthroughs` (
					`id` INT(11) NOT NULL auto_increment,
					`link_id` INT(11) NOT NULL DEFAULT '0',
					`referrer_id` INT(11) NOT NULL DEFAULT '0',
					`time` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
					PRIMARY KEY (id)
				)";
				$wpdb->query($sql);
				
				$sql = "CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."feed_links` (
					`id` INT(11) NOT NULL auto_increment,
					`url` VARCHAR(255) NOT NULL DEFAULT '',
					PRIMARY KEY (`id`),
					UNIQUE KEY `url` (`url`)
				)";
				$wpdb->query($sql);
				
				$sql = "CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."feed_referrers` (
					`id` INT(11) NOT NULL auto_increment,
					`url` VARCHAR(255) NOT NULL DEFAULT '',
					PRIMARY KEY (`id`),
					UNIQUE KEY `url` (`url`)
				)";
				$wpdb->query($sql);
				
				$sql = "CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."feed_postviews` (
					`id` INT(11) NOT NULL auto_increment,
					`post_id` INT(11) NOT NULL DEFAULT '0',
					`time` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
					PRIMARY KEY (id)
				)";
				$wpdb->query($sql);
				
				update_option("feed_statistics_track_clickthroughs", "0");
				update_option("feed_statistics_track_postviews", "1");
			case '1.1':
			case '1.1.1':
			case '1.1.2':
				$sql = "ALTER TABLE `".$wpdb->prefix."feed_subscribers` ADD `feed` VARCHAR( 120 ) NOT NULL AFTER `identifier`";
				$wpdb->query($sql);

				$sql = "ALTER TABLE `".$wpdb->prefix."feed_subscribers` DROP PRIMARY KEY, ADD PRIMARY KEY (`identifier`, `feed`)";
				$wpdb->query($sql);
			case '1.2':
			case '1.3':
				$sql = "DROP TABLE `".$wpdb->prefix."feed_referrers`";
				$wpdb->query($sql);
				
				$sql = "ALTER TABLE `".$wpdb->prefix."feed_clickthroughs` DROP `referrer_id`";
				$wpdb->query($sql);
			case '1.3.1':
				$sql = "ALTER TABLE `".$wpdb->prefix."feed_subscribers` CHANGE `feed` `feed` VARCHAR(120) NOT NULL";
				$wpdb->query($sql);
			case '1.3.2':
			case '1.4':
			case '1.4.1';
			case '1.4.2':
			case '1.4.3':
			case '1.5':
				// Seeing some errors about a 1000-byte key being too long. Go figure.
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "feed_links DROP KEY url" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "feed_links ADD url_hash VARCHAR(32) NOT NULL" );
				$wpdb->query( "UPDATE " . $wpdb->prefix . "feed_links SET url_hash=MD5(url)" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "feed_links ADD KEY url_hash (url_hash)" );
			break;
			default:
				// Full SQL of current schema.
				
				$wpdb->query( "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."feed_links
					(
						id int(11) NOT NULL AUTO_INCREMENT,
						url_hash varchar(32) NOT NULL,
						url varchar(1000) NOT NULL,
						PRIMARY KEY  (id),
						UNIQUE KEY url_hash (url_hash)
					)"
				);

				$wpdb->query( "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."feed_postviews
					(
						id int(11) NOT NULL AUTO_INCREMENT,
						post_id int(11) NOT NULL DEFAULT '0',
						time datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
						PRIMARY KEY  (id)
					)"
				);

				$wpdb->query( "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."feed_subscribers
					(
						subscribers int(11) NOT NULL DEFAULT '0',
						identifier varchar(255) NOT NULL DEFAULT '',
						feed varchar(120) NOT NULL,
						date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
						user_agent varchar(255) DEFAULT NULL,
						PRIMARY KEY  (identifier,feed)
					)"
				);
				
				$wpdb->query( "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."feed_clickthroughs
					(
						id int(11) NOT NULL AUTO_INCREMENT,
						link_id int(11) NOT NULL DEFAULT '0',
						time datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
						PRIMARY KEY  (id)
					)"
				);
			break;
		}
	}
	
	function is_feed_url() {
		switch (basename($_SERVER['PHP_SELF'])) {
			case 'wp-rdf.php':
			case 'wp-rss.php':
			case 'wp-rss2.php':
			case 'wp-atom.php':
			case 'feed':
			case 'rss2':
			case 'atom':
				return true;
				break;
		}
		
		if (isset($_GET["feed"])) {
			return true;
		}

		if (preg_match("/^\/(feed|rss2?|atom|rdf)/Uis", $_SERVER["REQUEST_URI"])) {
			return true;
		}
		
		if (preg_match("/\/(feed|rss2?|atom|rdf)\/?$/Uis", $_SERVER["REQUEST_URI"])){
			return true;
		}
		
		return false;
	}
	
	function how_many_subscribers() {
		global $wpdb;
		
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					`subscribers`,
					CASE WHEN `subscribers` = 1 THEN `identifier` ELSE CONCAT(`identifier`, `feed`) END AS `ident`
				FROM " . $wpdb->prefix . "feed_subscribers
				WHERE 
					(
						(`date` > %s)
						OR 
						(
							LOCATE('###',`identifier`) != 0 AND 
							`date` > %s
						)
					)
				ORDER BY `ident` ASC, `date` DESC",
				date( "Y-m-d H:i:s", time() - ( 60 * 60 * 24 * get_option( "feed_statistics_expiration_days" ) ) ),
				date( "Y-m-d H:i:s", time() - ( 60 * 60 * 24 * get_option( "feed_statistics_expiration_days" ) * 3 ) )
			)
		);
		
		$s = 0;
		
		if ( ! empty( $results ) ) {
			$current_ident = '';

			foreach ( $results as $row ) {
				if ( $row->ident != $current_ident ) {
					$s += $row->subscribers;
					$current_ident = $row->ident;
				}
			}
		}
		
		return intval( $s );
	}
	
	function add_options_menu() {
		add_menu_page( __( 'Feed Statistics Settings', 'feed-statistics' ), __( 'Feed Statistics', 'feed-statistics' ), 'publish_posts', basename(__FILE__), 'feed_statistics_feed_page' );
		
		add_submenu_page( basename( __FILE__ ), __( 'Top Feeds', 'feed-statistics' ), __( 'Top Feeds', 'feed-statistics' ), 'publish_posts', 'feedstats-topfeeds', 'feed_statistics_topfeeds_page' );
		add_submenu_page( basename( __FILE__ ), __( 'Feed Readers', 'feed-statistics' ), __( 'Feed Readers', 'feed-statistics' ), 'publish_posts', 'feedstats-feedreaders', 'feed_statistics_feedreaders_page' );
		
		if (get_option("feed_statistics_track_postviews"))
			add_submenu_page( basename( __FILE__ ), __( 'Post Views', 'feed-statistics' ), __( 'Post Views', 'feed-statistics' ), 'publish_posts', 'feedstats-postviews', 'feed_statistics_postviews_page' );
		
		if (get_option("feed_statistics_track_clickthroughs"))
			add_submenu_page( basename( __FILE__ ), __( 'Clickthroughs', 'feed-statistics' ), __( 'Clickthroughs', 'feed-statistics' ), 'publish_posts', 'feedstats-clickthroughs', 'feed_statistics_clickthroughs_page' );
	}
	
	function clickthroughs_page(){
		global $wpdb;
		
		?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general">
				<br />
			</div>
			<h2><?php esc_html_e( 'Most popular links in your feed (last 30 days)', 'feed-statistics' ); ?></h2>
			<p>
				<?php

				if ( get_option( 'feed_statistics_track_clickthroughs' ) )
					esc_html_e( 'You currently have clickthrough tracking turned on.', 'feed-statistics' );
				else
					esc_html_e( 'You currently have clickthrough tracking turned off.', 'feed-statistics' );

				?>
			</p>

			<table style="width: 100%;">
				<thead>
					<tr>
						<th>&nbsp;</th>
						<th style="width: 45%; text-align: left;"><?php esc_html_e( 'Outgoing Link', 'feed-statistics' ); ?></th>
						<th><?php esc_html_e( 'Clicks', 'feed-statistics' ); ?></th>
						<th style="width: 35%;">&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<?php

					$wpdb->query(
						$wpdb->prepare(
							"DELETE FROM " . $wpdb->prefix . "feed_clickthroughs WHERE time < %s",
							date( "Y-m-d H:i:s", time() - ( 60 * 60 * 24 * 30 ) )
						)
					);

					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT 
								COUNT(*) AS `clicks`,
								`l`.`url` AS `link`
							FROM " . $wpdb->prefix . "feed_clickthroughs AS `c`
								LEFT JOIN `".$wpdb->prefix."feed_links` AS `l` ON `c`.`link_id`=`l`.`id`
							WHERE `c`.`time` > %s
							GROUP BY `c`.`link_id`
							ORDER BY `clicks` DESC",
							date( 'Y-m-d H:i:s', time() - ( 60 * 60 * 24 * 30 ) )
						)
					);
	
					$i = 1;
	
					if ( ! empty( $results ) ) {
						$max = $results[0]->clicks;
	
						foreach ( $results as $row ) {
							$percentage = ceil( $row->clicks / $max * 100 );
							
							?>
							<tr>
								<td>
									<?php echo $i++; ?>
								</td>
								<td>
									<a href="<?php echo esc_url( $row->link ); ?>"><?php echo esc_url( $row->link ); ?></a>
								</td>
								<td>
									<?php echo $row->clicks; ?>
								</td>
								<td>
									<div class="graph" style="width: <?php echo $percentage; ?>%;">&nbsp;</div>
								</td>
							</tr>
							<?php
						}
					}
				
					?>
				</tbody>
			</table>
		</div>
		<?php
	}
	
	function topfeeds_page(){
		global $wpdb;
		
		?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general">
				<br />
			</div>
			<h2><?php esc_html_e( 'Your most popular feeds', 'feed-statistics' ); ?></h2>
			<table style="width: 100%;">
				<thead>
					<tr>
						<th>&nbsp;</th>
						<th style="width: 50%; text-align: left;"><?php esc_html_e( 'Feed URL', 'feed-statistics' ); ?></th>
						<th style="text-align: left;"><?php esc_html_e( 'Subscribers', 'feed-statistics' ); ?></th>
						<th style="width: 35%;">&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<?php
		
					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT
								`feed`,
								SUM(`subscribers`) `subscribers`
								FROM `".$wpdb->prefix."feed_subscribers`
								WHERE 
									`feed` != '' 
									AND 
									(
										(`date` > %s) 
										OR 
										(
											LOCATE('###',`identifier`) != 0 AND 
											`date` > %s
										)
									)
								GROUP BY `feed`
								ORDER BY `subscribers` DESC",
							date("Y-m-d H:i:s", time() - (60 * 60 * 24 * get_option("feed_statistics_expiration_days"))),
							date("Y-m-d H:i:s", time() - (60 * 60 * 24 * get_option("feed_statistics_expiration_days") * 3))
						)
					);

					$feeds = array();

					$i = 1;

					if ( ! empty( $results ) ) {
						foreach ( $results as $feed ) {
							if ( ! isset( $max ) )
								$max = $feed->subscribers;

							$percentage = ceil( $feed->subscribers / $max * 100 );

							?>
							<tr>
								<td><?php echo $i++; ?></td>
								<td style="width: 40%;">
									<a href="<?php echo esc_url( $feed->feed ); ?>"><?php echo esc_url( $feed->feed ); ?></a>
								</td>
								<td style="width: 15%;"><?php echo $feed->subscribers; ?></td>
								<td style="width: 40%;">
									<div class="graph" style="width: <?php echo $percentage; ?>%;">&nbsp;</div>
								</td>
							</tr>
							<?php
						}
					}
					
					?>
				</tbody>
			</table>
		</div>
		<?php
	}
	
	function postviews_page(){
		global $wpdb;
		
		?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general">
				<br />
			</div>
			<h2><?php esc_html_e( 'Your most popular posts (last 30 days)', 'feed-statistics' ); ?></h2>
			<p>
				<?php
				
				if ( get_option( 'feed_statistics_track_postviews' ) )
					esc_html_e( 'You currently have post view tracking turned on.', 'feed-statistics' );
				else
					esc_html_e( 'You currently have post view tracking turned off.', 'feed-statistics' );
				
				?>
			</p>
			<table style="width: 100%;">
				<thead>
					<tr>
						<th>&nbsp;</th>
						<th style="width: 50%; text-align: left;"><?php esc_html_e( 'Post Title', 'feed-statistics' ); ?></th>
						<th style="text-align: left;"><?php esc_html_e( 'Views', 'feed-statistics' ); ?></th>
						<th style="width: 35%;">&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<?php
		
					// Delete entries older than 30 days.
					$wpdb->query(
						$wpdb->prepare(
							"DELETE FROM " . $wpdb->prefix . "feed_postviews WHERE `time` < %s",
							date("Y-m-d H:i:s", time() - (60 * 60 * 24 * 30))
						)
					);
		
					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT 
								COUNT(*) AS `views`,
								`v`.`post_id`,
								`p`.`post_title` `title`,
								`p`.`guid` `permalink`
							FROM " . $wpdb->prefix . "feed_postviews AS `v`
							LEFT JOIN " . $wpdb->prefix . "posts AS `p` ON `v`.`post_id`=`p`.`ID`
							WHERE `v`.`time` > %s
							GROUP BY `v`.`post_id`
							ORDER BY `views` DESC
							LIMIT 20",
							date("Y-m-d H:i:s", time() - (60 * 60 * 24 * 30))
						)
					);
		
					if ( ! empty( $results ) ) {
						$i = 1;
						$max = $results[0]->views;
			
						foreach ( $results as $row ) {
							$percentage = ceil($row->views / $max * 100);
							
							?>
							<tr>
								<td><?php echo $i++; ?></td>
								<td><a href="<?php echo esc_url( $row->permalink ); ?>"><?php esc_html_e( $row->title ); ?></a></td>
								<td><?php echo $row->views; ?></td>
								<td>
									<div class="graph" style="width: <?php echo $percentage; ?>%;">&nbsp;</div>
								</td>
							</tr>
							<?php
						}
					}
					
					?>
				</tbody>
			</table>
		</div>
		<?php
	}
	
	function feedreaders_page(){
		global $wpdb;
		
		?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general">
				<br />
			</div>
			<h2><?php esc_html_e( 'Top Feed Readers', 'feed-statistics' ); ?></h2>
			<?php 
		
			$expiration_days = get_option("feed_statistics_expiration_days");
		
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM " . $wpdb->prefix . "feed_subscribers WHERE `date` < %s",
					date( "Y-m-d H:i:s", time() - ( 60 * 60 * 24 * get_option( "feed_statistics_expiration_days" ) * 3 ) )
				)
			);
		
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						CASE 
							WHEN 
								LOCATE('###',`identifier`) != 0 THEN SUBSTRING(`identifier`, 1, LOCATE(' ',`identifier`))
							ELSE
								`user_agent`
						END AS `reader`,
					SUM(`subscribers`) `readers`
					FROM " . $wpdb->prefix . "feed_subscribers
					WHERE `date` > %s
					GROUP BY `reader`
					ORDER BY `readers` DESC",
					date("Y-m-d H:i:s", time() - (60 * 60 * 24 * get_option("feed_statistics_expiration_days")))
				)
			);
		
			$readers = array();
		
			if (!empty($results)){
				foreach ($results as $row){
					$reader = $row->reader;
			
					$version = array();
			
					if ($reader == '') {
						$reader = "Unknown (Pending)";
					} 
					else if (preg_match("/Navigator\/([0-9abpre\.]+)/is", $reader, $version)){
						$reader = "Netscape Navigator ".$version[1];
					}
					else if (preg_match("/Opera\/([0-9abpre\.]+)/is", $reader, $version)){
						$reader = "Opera ".$version[1];
					}
					else if (preg_match("/Flock\/([0-9abpre\.]+)/is", $reader, $version)){
						$reader = "Flock ".$version[1];
					}
					else if (preg_match("/(Firefox|BonEcho|GranParadiso|Aurora|Minefield)\/([0-9abpre\.]+)/is", $reader, $version)) {
						$reader = "Mozilla ".$version[1]." ".$version[2];
					}
					else if (preg_match("/MSIE ([0-9abpre\.]+)/is", $reader, $version)){
						$reader = "Internet Explorer ".$version[1];
					}
					else if (preg_match("/RockMelt\/([^\s\.]+)/is", $reader, $version)) {
						$reader = "RockMelt ".$version[1];
					}
					else if (preg_match("/Chrome\/([^\s\.]+)/is", $reader, $version)) {
						$reader = "Chrome ".$version[1];
					}
					else if (preg_match("/Safari/is", $reader)) {
						$reader = "Safari";
					}
					else if (preg_match("/Gecko/Uis", $reader)) {
						$reader = "Other Mozilla browser";
					}
					else if (!preg_match("/Mozilla/Uis", $reader)){
						$reader = preg_replace("/[\/;].*$/Uis", "", $reader);
					}
					else {
						continue;
					}
			
					foreach ($readers as $key => $d) {
						if ($d["reader"] == $reader){
							$readers[$key]["readers"] += $row->readers;
							continue 2;
						}
					}
			
					$readers[] = array("reader" => $reader, "readers" => $row->readers);
				}
			}
		
			function sort_reader_array($a, $b) {
				return $b["readers"] - $a["readers"];
			}
		
			usort($readers, 'sort_reader_array');
		
			$max = $readers[0]["readers"];
		
			ob_start();
		
			?>
			<table style="width: 100%;">
				<thead>
					<tr>
						<th>&nbsp;</th>
						<th style="text-align: left;"><?php esc_html_e( 'Reader', 'feed-statistics' ); ?></th>
						<th style="text-align: left;"><?php esc_html_e( 'Subscribers', 'feed-statistics' ); ?></th>
						<th>&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<?php
				
					$i = 1;
		
					foreach ($readers as $reader) {
						$percentage = ceil($reader["readers"] / $max * 100);
					
						?>
						<tr>
							<td><?php echo $i++; ?></td>
							<td style="width: 40%;"><?php echo esc_html( $reader["reader"] ); ?></td>
							<td style="width: 15%;"><?php echo esc_html( $reader["readers"] ); ?></td>
							<td style="width: 40%;">
								<div class="graph" style="width: <?php echo $percentage; ?>%;">&nbsp;</div>
							</td>
						</tr>
						<?php
					}
				
					?>
				</tbody>
			</table>
		</div>
		<?php
	}
	
	function feed_page() {
		?>
		<div class="wrap">
			<?php if ( ! empty( $_POST['feed_statistics_update'] ) ) { ?>
				<div class="updated"><p><?php esc_html_e( 'Settings have been saved.', 'feed-statistics' ); ?></p></div>
			<?php } ?>
			
			<div class="icon32" id="icon-options-general">
				<br />
			</div>
			<h2><?php esc_html_e( 'Feed Statistics Settings', 'feed-statistics' ); ?></h2>
			<form method="post">
				<input type="hidden" name="feed_statistics_update" value="1"/>
				
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Subscribers', 'feed-statistics' ); ?></th>
							<td>
								<?php printf( esc_html__( 'Count users who have requested a feed within the last %1$s days as subscribers. You currently have %2$s subscribers.' ), '<input type="text" size="2" name="feed_statistics_expiration_days" value="' . intval( get_option("feed_statistics_expiration_days") ) . '" />', number_format_i18n( FEED_STATS::how_many_subscribers() ) ); ?>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Clickthroughs', 'feed-statistics' ); ?></th>
							<td>
								<input type="checkbox" name="feed_statistics_track_clickthroughs" value="1" <?php checked( get_option( 'feed_statistics_track_clickthroughs' ) ); ?> />
								<?php esc_html_e( 'Track which links your subscribers click', 'feed-statistics' ); ?>
								<p class="description">
									<?php esc_html_e( 'This requires Wordpress to route all links in your posts back through your site so that clicks can be recorded.  The user shouldn\'t notice a difference.', 'feed-statistics' ); ?>
								</p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Post views', 'feed-statistics' ); ?></th>
							<td>
								<input type="checkbox" name="feed_statistics_track_postviews" value="1" <?php checked( get_option( 'feed_statistics_track_postviews' ) ); ?> />
								<?php esc_html_e( 'Track individual post views', 'feed-statistics' ); ?>
								<p class="description">
									<?php esc_html_e( 'This is done via an invisible tracking image and will track views of posts by users that use feed readers that load images from your site.', 'feed-statistics' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<input class="button-primary" type="submit" name="Submit" value="<?php esc_attr_e( 'Update Options', 'feed-statistics' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}
	
	function widget_register() {
		wp_register_sidebar_widget( 'feed-statistics-widget', __( 'Feed Statistics', 'feed-statistics' ), array( 'FEED_STATS', 'widget' ) );
	}
	
	function widget($args) {
		echo $args['before_widget'];
		
		echo '<span class="subscriber_count">';
			feed_subscribers();
		echo '</span>';
		
		echo $args['after_widget'];
	}
	
	function clickthrough_replace($content) {
		if (is_feed()) {
			$this_file = __FILE__;
			
			$redirect_url = home_url( '/?feed-stats-url=' );
		
			$content = preg_replace("/(<a[^>]+href=)(['\"])([^'\"]+)(['\"])([^>]*>)/e", "'$1\"'.esc_url('$redirect_url' . base64_encode('\\3') ) . '\"$5'", $content);
		}	
		
		return $content;
	}
	
	function postview_tracker($content) {
		global $id;
		
		if (is_feed()) {
			$content .= ' <img src="' . esc_url( home_url( '/?feed-stats-post-id=' . $id ) ) . '" width="1" height="1" style="display: none;" />';
		}
		
		return $content;
	}
	
	function admin_head() {
		?>
		<style type="text/css">
			div.graph {
				border: 1px solid rgb(13, 50, 79);
				background-color: rgb(131, 180, 216);
			}
		</style>		
		<?php
	}
}

function feed_subscribers(){
	$s = FEED_STATS::how_many_subscribers();
	
	printf( _n( '%s feed subscriber', '%s feed subscribers', $s ), number_format_i18n( $s ) );
}

function feed_statistics_options() {
	FEED_STATS::options();
}

function feed_statistics_widget($args) {
	FEED_STATS::widget($args);
}

function feed_statistics_feed_page() {
	FEED_STATS::feed_page();
}
function feed_statistics_feedreaders_page() {
	FEED_STATS::feedreaders_page();
}
function feed_statistics_clickthroughs_page() {
	FEED_STATS::clickthroughs_page();
}
function feed_statistics_postviews_page() {
	FEED_STATS::postviews_page();
}
function feed_statistics_topfeeds_page() {
	FEED_STATS::topfeeds_page();
}

function feed_statistics_action_links( $links, $file ) {
    if ( $file == plugin_basename( dirname(__FILE__) . '/feed-statistics.php' ) ) {
        $links[] = '<a href="admin.php?page=feed-statistics.php">' . esc_html__( 'Settings', 'feed-statistics' ) .' </a>';
    }

    return $links;
}

if(function_exists('add_action')){
	add_action('init', array('FEED_STATS','init'));
	add_action('init', array('FEED_STATS','widget_register'));
	add_action('admin_menu', array('FEED_STATS','add_options_menu'));
	add_action('admin_head', array('FEED_STATS','admin_head'));
	
	add_action( 'plugins_loaded', array( 'FEED_STATS', 'db_setup' ) );
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'plugin_action_links', 'feed_statistics_action_links', 10, 2 );
}

if(function_exists('get_option')){
	if (get_option("feed_statistics_track_clickthroughs")) {
		add_filter('the_content', array('FEED_STATS','clickthrough_replace'));
	}

	if (get_option("feed_statistics_track_postviews")) {
		add_filter('the_content', array('FEED_STATS','postview_tracker'));
	}
}

if ( function_exists( 'register_activation_hook' ) ) {
	register_activation_hook( __FILE__, array( 'FEED_STATS', 'sql' ) );
}