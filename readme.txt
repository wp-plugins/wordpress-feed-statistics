=== Feed Statistics ===
Contributors: cfinke
Donate link: http://www.chrisfinke.com/wordpress/plugins/feed-statistics/
Tags: rss, statistics, feeds, newsfeed, stats
Requires at least: 3.7
Tested up to: 4.0
Stable tag: 4.0.1

Compiles statistics about who is reading your blog via a feed reader and what posts they're reading.

== Description ==

Feed Statistics is a plugin that tracks statistics for your RSS/Atom feeds, including the number of subscribers, which feed readers they're using, which posts they're viewing and which links they're clicking on.

You have two ways to display your subscriber count. You can either add the following code to your theme (in your sidebar, for example):

`<?php feed_subscribers(); ?>`

This will display a simple "123 feed subscribers" line of text that you can markup however you want. If your theme is widget-compatible, you can instead use the Feed Statistics widget, which will display the same text, but inside of an easy-to-manage widget. (If you want to style the widget text, it is inside a tag.)

The plugin also adds a "Feed Statistics" menu to your dashboard. This section has four subsections: Feed Statistics (Options), Feed Readers, Post Views, and Clickthroughs.

It will take a few days for the subscriber count to become accurate, so you may want to wait a day or two between installing/activating the plugin and publicly displaying your subscriber count.

== Screenshots ==

1. Displays a list of the feeds most often requested by your subscribers. Note: a subscriber that follows multiple feeds will only be counted once in the total subscriber count, but all of the feeds that they subscribe to will appear on this page.
2. Displays which feed readers your subscribers are using.
3. If you have post view tracking turned on (it's enabled by default), this page will show the posts in your RSS feed that are most popular.
4. If you have clickthrough tracking turned on (it's disabled by default), this page will show the links in your posts that your feed subscribers are clicking on.

== Changelog ==

= 4.0 =
* Fixed bug allowing Feed Statistics to be used as an open redirect
* Fixed bug that didn't track clickthroughs on links with uppercase anchor tags
* Fixed bug causing some link clickthroughs to be attributed to the wrong URL