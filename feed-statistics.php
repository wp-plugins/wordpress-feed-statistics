<?php

/*
Plugin Name: Feed Statistics
Plugin URI: http://www.chrisfinke.com/wordpress/plugins/feed-statistics/
Description: Compiles statistics about who is reading your blog via an RSS feed and what they're reading.
Version: 2.0pre
Author: Christopher Finke
Author URI: http://www.chrisfinke.com/
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
				$sql = "INSERT INTO `".$wpdb->prefix."feed_links` SET `url`='".mysql_real_escape_string($url)."'";
		
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
			if ( ! empty( $_GET['feed-stats-view'] ) && get_option( "feed_statistics_track_postviews" ) ) {
				$wpdb->insert(
					$wpdb->prefix . 'feed_postviews',
					array(
						'post_id' => $_GET['feed-stats-view'],
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
							array( 'url' => $url ),
							array( '%s' )
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
			
			$q = "SELECT * FROM `".$wpdb->prefix."feed_subscribers`
				WHERE `identifier`='".mysql_real_escape_string($identifier)."'
				AND `feed`=''";
			$results = $wpdb->get_results($q);
		
			if (!empty($results)) {
				$q = "UPDATE `".$wpdb->prefix."feed_subscribers`
					SET 
						`subscribers`=".intval($subscribers).", 
						`identifier`='".mysql_real_escape_string($identifier)."', 
						`user_agent`='".mysql_real_escape_string($user_agent)."',
						`feed`='".mysql_real_escape_string($feed)."',
						`date`=NOW() 
					WHERE
						`identifier`='".mysql_real_escape_string($identifier)."'
						AND `feed`=''";
				$wpdb->query($q);
			}
			else {
				$q = "SELECT * FROM `".$wpdb->prefix."feed_subscribers` WHERE `identifier`='".mysql_real_escape_string($identifier)."' AND `feed`='".mysql_real_escape_string($feed)."'";
				$result = $wpdb->query($q);
				
				if ($result == 0) {
					$q = "INSERT INTO `".$wpdb->prefix."feed_subscribers`
						SET 
							`subscribers`=".intval($subscribers).", 
							`identifier`='".mysql_real_escape_string($identifier)."', 
							`user_agent`='".mysql_real_escape_string($user_agent)."',
							`feed`='".mysql_real_escape_string($feed)."',
							`date`=NOW()";
					$wpdb->query($q);
				}
				else {
					$row = $wpdb->last_result[0];
					
					if ($user_agent != $row->user_agent || $subscribers != $row->subscribers){
						$q = "UPDATE `".$wpdb->prefix."feed_subscribers`
							SET
							`date`=NOW(), 
							`user_agent`='".mysql_real_escape_string($user_agent)."',
							`subscribers`=".intval($subscribers)."
							WHERE `identifier`='".mysql_real_escape_string($identifier)."' AND `feed`='".mysql_real_escape_string($feed)."'";
						$wpdb->query($q);
					}
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
		
		$sql = "CREATE TABLE ".$wpdb->prefix."feed_clickthroughs (
  id int(11) NOT NULL AUTO_INCREMENT,
  link_id int(11) NOT NULL DEFAULT '0',
  time datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id)
);

CREATE TABLE ".$wpdb->prefix."feed_links (
  id int(11) NOT NULL AUTO_INCREMENT,
  url varchar(1000) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY  url (url)
);

CREATE TABLE ".$wpdb->prefix."feed_postviews (
  id int(11) NOT NULL AUTO_INCREMENT,
  post_id int(11) NOT NULL DEFAULT '0',
  time datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id)
);

CREATE TABLE ".$wpdb->prefix."feed_subscribers (
  subscribers int(11) NOT NULL DEFAULT '0',
  identifier varchar(255) NOT NULL DEFAULT '',
  feed varchar(120) NOT NULL,
  date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  user_agent varchar(255) DEFAULT NULL,
  PRIMARY KEY  (identifier,feed)
);";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
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
		
		$q = "SELECT
				`subscribers`,
				CASE WHEN `subscribers` = 1 THEN `identifier` ELSE CONCAT(`identifier`, `feed`) END AS `ident`
			FROM `".$wpdb->prefix."feed_subscribers`
			WHERE 
				(
					(`date` > '".date("Y-m-d H:i:s", time() - (60 * 60 * 24 * get_option("feed_statistics_expiration_days")))."') 
					OR 
					(
						LOCATE('###',`identifier`) != 0 AND 
						`date` > '".date("Y-m-d H:i:s", time() - (60 * 60 * 24 * get_option("feed_statistics_expiration_days") * 3))."'
					)
				)
				ORDER BY `ident` ASC, `date` DESC";
		$results = $wpdb->get_results($q);
		
		$s = 0;
		$current_ident = '';
		
		if (!empty($results)) {
			foreach ($results as $row){
				if ($row->ident != $current_ident){
					$s += $row->subscribers;
					$current_ident = $row->ident;
				}
			}
		}
		
		return intval($s);
	}
	
	function add_options_menu() {
		add_menu_page('Feed Options', 'Feed', 8, basename(__FILE__), 'feed_statistics_feed_page');
		add_submenu_page(basename(__FILE__), 'Top Feeds', 'Top Feeds', 8, 'feedstats-topfeeds', 'feed_statistics_topfeeds_page');
		add_submenu_page(basename(__FILE__), 'Feed Readers', 'Feed Readers', 8, 'feedstats-feedreaders', 'feed_statistics_feedreaders_page');
		
		if (get_option("feed_statistics_track_postviews"))
			add_submenu_page(basename(__FILE__), 'Post Views', 'Post Views', 8, 'feedstats-postviews', 'feed_statistics_postviews_page');
		
		if (get_option("feed_statistics_track_clickthroughs"))
			add_submenu_page(basename(__FILE__), 'Clickthroughs', 'Clickthroughs', 8, 'feedstats-clickthroughs', 'feed_statistics_clickthroughs_page');
	}
	
	function clickthroughs_page(){
		global $wpdb;
		?>
			<div class="wrap">
				<p>You currently have clickthrough tracking turned <b>
				<?php
			
				echo (get_option("feed_statistics_track_clickthroughs")) ? "on" : "off";
			
				?></b>.
			</p>
			<br />

			<h2>Most popular links in your feed (last 30 days)</h2>
			<table style="width: 100%;">
				<thead>
					<tr>
						<th>&nbsp;</th>
						<th style="width: 45%;">Outgoing Link</th>
						<th>Clicks</th>
						<th style="width: 35%;">&nbsp;</th>
						</tr></thead>
				<tbody>
		<?php		
		
		$sql = "DELETE FROM `".$wpdb->prefix."feed_clickthroughs` WHERE `time` < '".date("Y-m-d H:i:s", time() - (60 * 60 * 24 * 30))."'";
		$wpdb->get_results($sql);
		
		$sql = "SELECT 
				COUNT(*) AS `clicks`,
				`l`.`url` AS `link`
			FROM `".$wpdb->prefix."feed_clickthroughs` AS `c`
			LEFT JOIN `".$wpdb->prefix."feed_links` AS `l` ON `c`.`link_id`=`l`.`id`
			WHERE `c`.`time` > '".date("Y-m-d H:i:s", time() - (60 * 60 * 24 * 30))."'
			GROUP BY `c`.`link_id`
			ORDER BY `clicks` DESC";
		$results = $wpdb->get_results($sql);
		
		$i = 1;
		
		if (!empty($results)) {
			$max = $results[0]->clicks;
		
			foreach ($results as $row){
				$percentage = ceil($row->clicks / $max * 100);
			
				echo '<tr><td>'.$i++.'.</td><td><a href="'.$row->link.'">'.$row->link.'</a></td><td>'.$row->clicks.'</td>
					<td>
						<div class="graph" style="width: '.$percentage.'%;">&nbsp;</div>
					</td>
					</tr>';
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
			<h2>Your most popular feeds</h2>
			<table style="width: 100%;">
				<thead>
					<tr>
						<th>&nbsp;</th>
						<th style="width: 50%;">Feed URL</th>
						<th>Subscribers</th>
						<th style="width: 35%;">&nbsp;</th>
					</tr>
				</thead>
				<tbody>
		<?php		
		
		$q = "SELECT
			`feed`,
			SUM(`subscribers`) `subscribers`
			FROM `".$wpdb->prefix."feed_subscribers`
			WHERE 
				`feed` != '' 
				AND 
				(
					(`date` > '".date("Y-m-d H:i:s", time() - (60 * 60 * 24 * get_option("feed_statistics_expiration_days")))."') 
					OR 
					(
						LOCATE('###',`identifier`) != 0 AND 
						`date` > '".date("Y-m-d H:i:s", time() - (60 * 60 * 24 * get_option("feed_statistics_expiration_days") * 3))."'
					)
				)
			GROUP BY `feed`
			ORDER BY `subscribers` DESC";
		$results = $wpdb->get_results($q);
		
		$feeds = array();
		
		$i = 1;
		
		if (!empty($results)){
			foreach ($results as $feed) {
				if (!isset($max)) $max = $feed->subscribers;
				
				$percentage = ceil($feed->subscribers / $max * 100);
			
				echo '<tr><td>'.$i++.'.</td><td style="width: 40%;"><a href="'.$feed->feed.'">'.$feed->feed.'</a></td><td style="width: 15%;">'.$feed->subscribers.'</td><td style="width: 40%;"><div class="graph" style="width: '.$percentage.'%;">&nbsp;</div></td></tr>';
			}
		}
		
		echo "</tbody></table>";
	}
	
	function postviews_page(){
		global $wpdb;
		?>
		<div class="wrap">
			<p>You currently have post view tracking turned <b>
			<?php
			
			echo (get_option("feed_statistics_track_postviews")) ? "on" : "off";
			
			?></b>.</p>
			<br />
			<h2>Your most popular posts (last 30 days)</h2>
			<table style="width: 100%;">
				<thead>
					<tr>
						<th>&nbsp;</th>
						<th style="width: 50%;">Post Title</th>
						<th>Views</th>
						<th style="width: 35%;">&nbsp;</th>
					</tr>
				</thead>
				<tbody>
		<?php		
		
		// Delete entries older than 30 days.
		$sql = "DELETE FROM `".$wpdb->prefix."feed_postviews` WHERE `time` < '".date("Y-m-d H:i:s", time() - (60 * 60 * 24 * 30))."'";
		$wpdb->get_results($sql);
		
		$sql = "SELECT 
				COUNT(*) AS `views`,
				`v`.`post_id`,
				`p`.`post_title` `title`,
				`p`.`guid` `permalink`
			FROM `".$wpdb->prefix."feed_postviews` AS `v`
			LEFT JOIN `".$wpdb->prefix."posts` AS `p` ON `v`.`post_id`=`p`.`ID`
			WHERE `v`.`time` > '".date("Y-m-d H:i:s", time() - (60 * 60 * 24 * 30))."'
			GROUP BY `v`.`post_id`
			ORDER BY `views` DESC
			LIMIT 20";
		$results = $wpdb->get_results($sql);
		
		if (!empty($results)) {
			$i = 1;
			$max = $results[0]->views;
			
			foreach ($results as $row) {
				$percentage = ceil($row->views / $max * 100);
				echo '
					<tr>
						<td>'.$i++.'.</td>
						<td><a href="'.$row->permalink.'">'.$row->title.'</a></td>
						<td>'.$row->views.'</td>
						<td>
							<div class="graph" style="width: '.$percentage.'%;">&nbsp;</div>
						</td>
					</tr>';
			}
		}
					
		?>			
				</tbody>
			</table>
		</div>
		<?php
	}
	
	function feedreaders_page(){
		?>
		<div class="wrap">
		<h2>Top Feed Readers</h2>
		<?php 
		
		echo FEED_STATS::reader_stats();
		
		?>
		</div>
		<?php
	}
	
	function feed_page() {
		if (isset($_POST["feed_statistics_update"])){
			update_option("feed_statistics_expiration_days",intval($_POST["feed_statistics_expiration_days"]));
			update_option("feed_statistics_track_clickthroughs",intval(isset($_POST["feed_statistics_track_clickthroughs"])));
			update_option("feed_statistics_track_postviews",intval(isset($_POST["feed_statistics_track_postviews"])));
		} 
		?>
		<div class="wrap">
			<h2>Feed Options</h2>
			<form method="post" style="width: 100%;">
				<fieldset>
					<input type="hidden" name="feed_statistics_update" value="1"/>
					<p>Count users who have requested a feed within the last <input type="text" size="2" name="feed_statistics_expiration_days" value="<?php echo get_option("feed_statistics_expiration_days"); ?>" /> days as subscribers. You currently have <b><?php feed_subscribers(); ?></b>. </p>
					<p>
						<input type="checkbox" name="feed_statistics_track_clickthroughs" value="1" <?php if (get_option("feed_statistics_track_clickthroughs")) { ?>checked="checked"<?php } ?>>
						Track which links your subscribers click<br />
						This requires Wordpress to route all links in your posts back through your site so that clicks can be recorded.  The user shouldn't notice a difference.
					</p>
					<p>
						<input type="checkbox" name="feed_statistics_track_postviews" value="1" <?php if (get_option("feed_statistics_track_postviews")) { ?>checked="checked"<?php } ?>>
						Track individual post views<br />
						This is done via an invisible tracking image and will track views of posts by users that use feed readers that load images from your site.
					</p>
					<input type="submit" name="Submit" value="<?php _e('Update Options') ?> &raquo;" />
				</fieldset>	
			</form>
		</div>
		<?php
	}
	
	function reader_stats() {
		global $wpdb;
		
		$expiration_days = get_option("feed_statistics_expiration_days");
		
		$sql = "DELETE FROM `".$wpdb->prefix."feed_subscribers` WHERE `date` < '".date("Y-m-d H:i:s", time() - (60 * 60 * 24 * get_option("feed_statistics_expiration_days") * 3))."'";
		$wpdb->get_results($sql);
		
		$q = "SELECT
				CASE 
					WHEN 
						LOCATE('###',`identifier`) != 0 THEN SUBSTRING(`identifier`, 1, LOCATE(' ',`identifier`))
					ELSE
						`user_agent`
				END AS `reader`,
			SUM(`subscribers`) `readers`
			FROM `".$wpdb->prefix."feed_subscribers`
			WHERE `date` > '".date("Y-m-d H:i:s", time() - (60 * 60 * 24 * get_option("feed_statistics_expiration_days")))."'
			GROUP BY `reader`
			ORDER BY `readers` DESC";
		$results = $wpdb->get_results($q);
		
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
		$rv = '<table style="width: 100%;">';
		$rv .= '<thead><tr><th>&nbsp;</th><th>Reader</th><th>Subscribers</th><th>&nbsp;</th></tr></thead><tbody>';
		
		$i = 1;
		
		foreach ($readers as $reader) {
			$percentage = ceil($reader["readers"] / $max * 100);
			
			$rv .= '<tr><td>'.$i++.'.</td><td style="width: 40%;">'.$reader["reader"].'</td><td style="width: 15%;">'.$reader["readers"].'</td><td style="width: 40%;"><div class="graph" style="width: '.$percentage.'%;">&nbsp;</div></td></tr>';
		}
		
		$rv .= "</tbody></table>";
		
		return $rv;
	}
	
	function widget_register() {
		if (function_exists('register_sidebar_widget')) {
			register_sidebar_widget('Feed Statistics', 'feed_statistics_widget');
		}
	}
	
	function widget($args) {
		extract($args);
		
		echo $before_widget;
		echo '<span class="subscriber_count">';
		feed_subscribers();
		echo '</span>';
		echo $after_widget;
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
	echo $s." feed subscriber";
	if ($s != 1) echo "s";
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

if(function_exists('add_action')){
	add_action('init', array('FEED_STATS','init'));
	add_action('init', array('FEED_STATS','widget_register'));
	add_action('admin_menu', array('FEED_STATS','add_options_menu'));
	add_action('admin_head', array('FEED_STATS','admin_head'));
	
	add_action( 'plugins_loaded', array( 'FEED_STATS', 'db_setup' ) );
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

?>
