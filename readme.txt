=== WP Blogmesh ===
Contributors: roytanck
Tags: social,social media,blogging,RSS,reader,blogroll
Requires at least: 4.9
Tested up to: 4.9.8
Stable tag: 0.8
License: GPL-3

Turn you WordPress blog into a Blogmesh node. Follow friends (and RSS feeds), and turn your homepage into a timeline of all their updates.

== Description ==
Blogmesh aims te make blogging more social, and in doing so create an alternative for existing social networks. The main idea is to connect existing blogs in a way that resembles other social networks, like Twitter. Simply follow your friends and see a timeline of their updates.

Because blogs are usually self-hosted, this means Blogmesh has the potential to become a decentralized network that belongs to its users, and where every user owns their own content.

Blogmesh uses existing, well-established standards like RSS. This means that many existing sites are already Blogmesh-ready.

More info on [blogmesh.org](https://blogmesh.org)

== Installation ==
1. Install the plugin from the plugins screen in wp-admin.
2. Activate the plugin.
3. Start adding friends.
4. Visit you blog's homepage (when logged in) to see their updates.

== Frequently Asked Questions ==

= Will this work with any theme? =

WP Blogmesh was designed to work with any theme, but your luck may vary. Blogging-oriented themes will likely work best. Please check WordPress's settings under "Reading" to see where your blog timeline is displayed. If you're setting up a new blog for this, I find that Twenty Fifteen works well, as does Colorlib's 'Sparkling' theme.

= How do I uninstall WP Blogmesh? =

When you deactivate WP Blogmesh on the plugins screen in wp-admin, you'll no longer see the friends section, and your homepage will contain just your posts. The current version of the plugin does not remove cached posts from the database. If you'd like to completely remove all Blogmesh content from the database, please use the ['Custom Post Type Cleanup' plugin](https://wordpress.org/plugins/custom-post-type-cleanup/) to completely delete the following post types.

* wpbm_friends
* wpbm_rss_cache

== Screenshots ==
 
1. The "friends" section, where you manage which blogs and sites you'd like to follow.
 
== Changelog ==

= 0.8 =

* Initial release.
