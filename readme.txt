=== WP link or article sharing ===
Contributors: kuaza
Donate link: http://kuaza.com/bagis-yapin
Tags: Wordpress, post link, send link, send content, insert content, send article, submit link, submit article
Requires at least: 3.0
Tested up to: 4.4
Stable tag: 1.2
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Users and visitors on your site, link or article provides sharing.

== Description ==

Quick and easy, Users and visitors on your site, link or article provides sharing.

Testing video: https://www.screenr.com/7sON

Demo for wordpress turkish: http://makaleci.com/icerik-oner

* Share link and article
* reCAPTCHA security
* Link previously added controls
* Select category and added tags
* simple and basic shortcode
* Save out count
* custom theme for post area
* Jquery ajax image upload
* auto Set featured image (upload first image)
* multiple images upload for post
* simple and quick :)
* and and and :)


And more, please look here (turkish - english coming soon) http://kuaza.com/wordpress-eklentileri/wordpress-link-icerik-gonderme-eklentisi

== Screenshots ==
1. Share and submit link page (first page)
2. Submit new article page (first page)
3. Link sharing for next page. Edit or upload for post images
4. Share

== Installation ==

1. Install the plugin like you always install plugins, either by uploading it via FTP or by using the "Add Plugin" function of WordPress. (Upload directory 'wp-link-or-post-submit' to the '/wp-content/plugins/' directory)
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Activate the plugin at the plugin administration page
4. Create reCAPTCHA Public key and private key for your wordpress site here: https://www.google.com/recaptcha/admin#createsite
5. After insert and save reCAPTCHA Public key and private key, wordpress plugins settings page.
6. For settings and testing video: https://www.screenr.com/fTON
7. And more please look this page: (Turkish) http://kuaza.com/wordpress-eklentileri/wordpress-link-icerik-gonderme-eklentisi
8. Easy simple :)

== Frequently Asked Questions ==

= How do I add post/page ? =

Very very easy. Insert this shortcode page/post text area:
<code>
[wlops_form]
</code>

= How do I add for Details post/page ? =

Insert this shortcode and html tags page/post text area:
<code>
<div class="wlops_topcontent">
step 1 [top]: first page top
</div>

	<div class="wlops_topcontent_step2">
	step 2 [top]: next page top
	</div>

		[wlops_form]

	<div class="wlops_bottomcontent_step2">
	step 2 [bottom]: Next page bottom
	</div>

<div class="wlops_bottomcontent">
step 1 [bottom]: first page top
</div>
</code>

= How do I change css style form? =

Go to the wordpress plugins settings page and change style area.

= Example theme pattern codes =

Wlops setting page or post (wlops options) area (for):
<code>
{{content}}
<div class="wlops_devam_linki"><a title="Out for {{post_title}}" href="{{out_redirect_url}}" target="_blank" class="btn btn-readmore wlops_read_more story-title-link story-link" rel="bookmark">
Read more <em>({{out_count}})</em></a> {{direct_source_url}}</div>
<hr>
{{wlops_images}}
</code>

== Changelog ==
= 1.3 =
* Hide categories selected options
* Hide tags selected options
* Hide Image uploads options
= 1.2 =
* list link post page disable for hide link post
* mb_detect_encoding error fix (for convert page character)
= 1.1 =
* Edit php error: HTTP_X_REQUESTED_WITH
* Edit show php error code

= 1.0 =
* First release (released plugins)

== Translations ==

The plugin comes with various translations, please refer to the [WordPress Codex](http://codex.wordpress.org/Installing_WordPress_in_Your_Language "Installing WordPress in Your Language") for more information about activating the translation. If you want to help to translate the plugin to your language, please have a look at the /languages/wlops.pot file which contains all definitions and may be used with a [gettext](http://www.gnu.org/software/gettext/) editor like [Poedit](http://www.poedit.net/) (Windows).