<?php
/**
* WP link or article sharing
* class for plugin
*/
	 
class WLOPS {

	public function __construct(){
	
		global $current_user;
			
			//Fix: http://david-coombes.com/wordpress-get-current-user-before-plugins-loaded/
			if(!function_exists('wp_get_current_user'))
				require_once(ABSPATH . "wp-includes/pluggable.php"); 
				
			wp_cookie_constants();
			$current_user = $this->user = wp_get_current_user();
		
			$this->user_roles = $current_user->roles;

			$this->kullanici_level = array_shift($this->user_roles);

	
			// giris yapan kullanici admin, editor, yazar ise true doner, degil ise false
			$this->direk_yayinlansin_mi = $this->kullanici_level == "administrator" || $this->kullanici_level == "editor" || $this->kullanici_level == "author" ? true : false;

			/*
			Yönetici: Blogun tamamı üzerinde kontrole ve yetkiye sahiptir, tüm ayarları değiştirebilir, tüm yazılara ve kullanıcılara müdahale edebilir, php dosyalarını dahi değiştirebilir. Blogunuzu kurarken oluşturduğunuz admin hesabına otomatik olarak Yönetici rolü atanır.
			Editör: Blogdaki tüm yazılar üzerinde yetki sahibidir. Yazıların kategorisini, etiketini, içeriğini, başlığını değiştirebilir, yazıları yayına alabilir, silebilir veya yayından kaldırabilir. Diğer ayarlara erişim yetkisi yoktur.
			Yazar: Blogda sadece kendi yazıları üzerinde kontrol ve yetki sahibidir. Kendi yazılarına, Editör rolünün getirdiği tüm yetkileri (yayına alma, yayından kaldırma, içeriğini/kategorisini/etiketlerini değiştirme, yazıyı silme) uygulayabilir. Diğer yazarlara ait yazılara ve blog ayarlarına müdahale etme yetkisi yoktur.
			
			İçerik Sağlayıcı: Bu yetkiye sahip kullanıcı kendi yazılarına müdahale etme yetkisine sahiptir, ama yazılarını yayına alamaz veya yayından kaldıramaz. Yazılarının yayınlanması için, Yönetici’nin ya da Editör’ün yazıyı onaylaması gerekir.
			Abone: Bu yetkiye sahip kullanıcı, yönetici tarafından başka bir hak verilmedikçe blog yazılarına veya ayarlarına müdahale edemez. Sadece yorum yazabilme yetkisine sahiptir.

			contributor : icerik saglayici
			subscriber : Abone
			author : Yazar
			editor : Editor
			administrator = Yönetici
			*/
		
			// wordpress icin gerekli kancalari yukler
			self::kancalari_yukle();
			
			$this->onayli_dosya_turleri = array('image/jpg','image/jpeg','image/gif','image/png');
			
			$this->kullanici_ID = get_current_user_id() ? get_current_user_id() : "100";

			// form kismindaki kategoriler listelerken kullanacagimiz config ayarlari.
			$this->form_kategori_args = array(
			'show_option_all'    => '',
			'show_option_none'   => '',
			'orderby'            => 'name', 
			'order'              => 'ASC',
			'show_count'         => 1,
			'hide_empty'         => 0, 
			'child_of'           => 0,
			'exclude'            => '',
			'echo'               => 1,
			'selected'           => 0,
			'hierarchical'       => 1, 
			'name'               => 'kategoriler',
			'id'                 => 'kategoriler',
			'class'              => 'yuzdeyuzyap',
			'depth'              => 0,
			'tab_index'          => 0,
			'taxonomy'           => 'category',
			'hide_if_empty'      => false,
			);

	}
			
	/**
	 * Initializes WordPress hooks
	 */
	private function kancalari_yukle() {
	
		// admin ayar sayfasi icin link ve menuleri ekleriz
		add_action('admin_menu', array($this, 'kuazaWlops_index__sayfa'));

		// dil dosyasini yukleriz
		add_action('plugins_loaded', array(&$this, 'wlops_plugins_loaded_lang'));

		// eger yazilar icin link formati yoksa diye ekleriz
		add_theme_support( 'post-formats', array( 'link','video','gallery' ) );
		
		add_shortcode( 'wlops_form', array( $this, 'wlopsform_func' ) );
		add_action('wp_head', array( $this, 'wlops_eklenti_css'));
		
		add_filter('query_vars',array( $this, 'kuaza_wlops_upload_plugin_add_trigger'));
		add_action('template_redirect', array( $this, 'plugin_trigger_check'));

		//wp_enqueue_script('jquery');
		add_action( 'wp_enqueue_scripts', array( $this, 'file_upload_form') );
		
		// yazi altinda yuklenen resimleri gosterme
		add_filter('the_content', array( $this, 'wlops_yazi_ici_otomatik'));
		
		// yazi basligina link atamak icin kullanacagiz // sadece link turu icerikler icin
		// http://redactweb.com/filter-a-page-posts-title-only-on-that-page-post/
		add_action('loop_start',array( $this, 'condition_filter_title'));
		
		add_filter( 'post_link', array( $this, 'append_query_string'), 10, 3 );

		// Ana sorgulardan link konularini kaldirmaya yarar
		add_action('pre_get_posts', array( $this, 'wlops_link_icerikleri_kaldirma_loopdan'));
		
			if ( $this->kullanici_level == "administrator" || $this->kullanici_level == "editor" ) {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
			add_action( 'save_post', array( $this, 'save_meta_options' ) );
			}
			
		if(!empty($this->kullanici_ID)){	
		add_filter('manage_posts_columns', array( $this, 'wlops_stun_ust'));
		add_action('manage_posts_custom_column', array( $this, 'wlops_stun_icerik'), 10, 2);	
		}		
	}

	// yazilar listelenirken extra stun ekleriz, cikis sayilari icin
	public function wlops_stun_ust($defaults) {
		$defaults['wlops_out_count'] = __('Wlops out click count','wlops');
		return $defaults;
	}
	 
	// cikis sayilarini stune ekleyelim.
	public function wlops_stun_icerik($column_name, $post_ID) {
		if ($column_name == 'wlops_out_count') {
			$wlops_kac_kere_ziyaret_edildi = $this->wlops_konu_cikis_sayisi($post_ID);
			if ($wlops_kac_kere_ziyaret_edildi) {
				echo $wlops_kac_kere_ziyaret_edildi;
			}else{
			echo "0";
			}
		}
	}
	
### Dil ozelligini aktif edelim.
public function wlops_plugins_loaded_lang() {
	load_plugin_textdomain("wlops", false, dirname( plugin_basename( __FILE__ ) ).'/languages/');
}
 
/*
*
*
*
* admin setting baslangic
*
*
*
*
*/
public function kuazaWLOPS_index__sayfa() {
	add_menu_page(__('WordPress link and content submit plugins (same pligg)','wlops'), __('Wlops','wlops'), "manage_options", 'wlops', array($this, 'kuazaWLOPSadminindex'));

}

// islemleri yonlendiren fonksiyonumuz..		
public function kuazaWLOPSadminindex(){
$islem = isset($_GET["islem"]) ? $_GET["islem"] : "";

switch($islem):

	case 'icerikleriguncelle':
	//kuaza_social_icerikleriguncelle();
	break;
	
	case 'ayarlariguncelle':
	$this->kuaza_wlops_ayarlariguncelle();
	break;
	/* sonraki versiyonlar icin
	case 'tablosil':
	echo "tablosil ffddfdfddf";
	break;
	
	case 'sayaclariguncelle':
	echo "sayaclariguncelle dfdf";
	break;
	*/	
	default;
		$this->kuaza_WLOPS_index();
	break;
endswitch;
}


/*
 * @desc	kuaza social / index Sayfası
 * @author	selcuk kilic
*/
public function kuaza_WLOPS_index(){
echo $this->kuaza_wlops_menuolustur();
?>

<div>

<div style="margin-right:30px;padding-right:10px;float:left;border-right:1px solid #ccc;width:30%">
<?php _e("<h3>Plugin info</h3>","wlops"); ?>
<?php _e("<strong>Plugin name:</strong> WP link or article sharing.","wlops"); ?><br /><br />
<?php _e("<strong>Plugin description:</strong> Pligg similar link and share content plugin. I also was inspired by digg.com","wlops"); ?><br /><br />
<?php _e("<strong>Plugin version:</strong> v1.1","wlops"); ?><br /><br />
<?php _e("<strong>Plugin author:</strong> Selcuk kilic (kuaza)","wlops"); ?><br /><br />
<?php _e("<strong>Plugin Widget support:</strong> no but, I think for the next version.","wlops"); ?><br /><br />
<?php echo sprintf(__("<strong>Plugin support:</strong> <a href='%s' target='_blank'>Support plugins release page (comments)</a> or this email: kuaza.ca@gmail.com","wlops"),"http://kuaza.com/wordpress-eklentileri/wordpress-link-icerik-gonderme-eklentisi"); ?><br /><br />
<?php _e("<strong>Author social profiles:</strong>","wlops"); ?><br />

<a href="https://www.facebook.com/kuaza.ca" target="_blank">Facebook</a>,
<a href="https://plus.google.com/u/0/+Kuaza61" target="_blank">Google</a>,
<a href="https://twitter.com/kuaza" target="_blank">Twitter</a>,
<a href="https://www.linkedin.com/profile/view?id=111819421&trk=nav_responsive_tab_profile" target="_blank">LinkedIn</a>,
<a href="http://piclect.com/kuaza" target="_blank">Piclect</a>
</div>

<div style="margin-right:30px;padding-right:10px;float:left;border-right:1px solid #ccc;width:30%">
<?php _e("<h3>Donate for support</h3>","wlops"); ?>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="RJVUX7HSHHHMG">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/fr_FR/i/scr/pixel.gif" width="1" height="1">
</form>
<hr />
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="JR2BL8Y7QU2P8">
<input type="image" src="https://www.paypalobjects.com/tr_TR/TR/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - Online ödeme yapmanın daha güvenli ve kolay yolu!">
<img alt="" border="0" src="https://www.paypalobjects.com/fr_FR/i/scr/pixel.gif" width="1" height="1">
</form>

<?php _e("<h4>Thanks for support donation..</h4>","wlops"); ?>

<?php _e("<h3><em>Please support add my project link your website</em></h3>","wlops"); ?>

<?php _e("<h3>My other project</h3>","wlops"); ?>
<?php echo sprintf(__("<a href='%s'>Latest news and articles. (Turkish)</a>","wlops"),"http://kuaza.com"); ?><hr />
<?php echo sprintf(__("<a href='%s'>Modafo</a>","wlops"),"http://www.modafo.com"); ?>
<?php echo sprintf(__("<a href='%s'>Image upload, create collections and share</a>","wlops"),"http://piclect.com"); ?><hr />
</div>

<div style="float:left;width:30%">
<?php _e("<h3>Change log</h3>","wlops"); ?>
<?php _e("Please look plugin page","wlops"); ?>
</div>

</div>


<?php
}


/*
 * ayarlari duzeltme Sayfası
 * 
*/
public function kuaza_wlops_ayarlariguncelle(){

echo $this->kuaza_wlops_menuolustur();

if(isset($_POST["ayarlariguncelle"]) && $_POST["ayarlariguncelle"] == "evet"){

$kuaza_wlops_css = isset($_POST["kuaza_wlops_css"]) ? $_POST["kuaza_wlops_css"] : "";
$wlops_konu_temasi = isset($_POST["wlops_konu_temasi"]) ? $_POST["wlops_konu_temasi"] : "";
$wlops_default_resimler_nasil_gorunsun = isset($_POST["wlops_default_resimler_nasil_gorunsun"]) ? $_POST["wlops_default_resimler_nasil_gorunsun"] : "";
$kuaza_wlops_loop_direk = !empty($_POST["kuaza_wlops_loop_direk"]) ? "yes" : "no";
$kuaza_wlops_hide_cat = !empty($_POST["kuaza_wlops_hide_cat"]) ? "yes" : "no";
$kuaza_wlops_hide_tag = !empty($_POST["kuaza_wlops_hide_tag"]) ? "yes" : "no";
$kuaza_wlops_hide_upload = !empty($_POST["kuaza_wlops_hide_upload"]) ? "yes" : "no";
$kuaza_wlops_guvenlik_public = isset($_POST["kuaza_wlops_guvenlik_public"]) ? $_POST["kuaza_wlops_guvenlik_public"] : "";
$kuaza_wlops_guvenlik_private = isset($_POST["kuaza_wlops_guvenlik_private"]) ? $_POST["kuaza_wlops_guvenlik_private"] : "";
$kuaza_wlops_guvenlik_tema = isset($_POST["kuaza_wlops_guvenlik_tema"]) ? $_POST["kuaza_wlops_guvenlik_tema"] : "";

update_option( "kuaza_wlops_css", $kuaza_wlops_css );
update_option( "wlops_konu_temasi", $wlops_konu_temasi );
update_option( "wlops_default_resimler_nasil_gorunsun", $wlops_default_resimler_nasil_gorunsun );
update_option( "kuaza_wlops_loop_direk", $kuaza_wlops_loop_direk );
update_option( "kuaza_wlops_hide_cat", $kuaza_wlops_hide_cat );
update_option( "kuaza_wlops_hide_tag", $kuaza_wlops_hide_tag );
update_option( "kuaza_wlops_hide_upload", $kuaza_wlops_hide_upload );
update_option( "kuaza_wlops_guvenlik_public", $kuaza_wlops_guvenlik_public );
update_option( "kuaza_wlops_guvenlik_private", $kuaza_wlops_guvenlik_private );
update_option( "kuaza_wlops_guvenlik_tema", $kuaza_wlops_guvenlik_tema );
echo "<div style='color:green'>".__('Update settings for plugin','wlops')."</div>";
}

?>

<hr>
<?php _e("Add this short code post/page area: [wlops_form]","wlops"); ?>
<hr>

<form method="POST" action="">
<table class="form-table">

<tr valign="top">
	<th><label for="kuaza_wlops_guvenlik_public"><?php _e("Public Key for reCAPTCHA","wlops"); ?></label></th>
	<td><input class="yuzdeyuzyap" id="kuaza_wlops_guvenlik_public" name="kuaza_wlops_guvenlik_public" type="text" value="<?php echo get_site_option('kuaza_wlops_guvenlik_public'); ?>" /></td>
<td><?php _e(" Create for key: https://www.google.com/recaptcha/admin#createsite","wlops"); ?></td>
</tr>

<tr valign="top">
	<th><label for="kuaza_wlops_guvenlik_private"><?php _e("Private Key for reCAPTCHA","wlops"); ?></label></th>
	<td><input class="yuzdeyuzyap" id="kuaza_wlops_guvenlik_private" name="kuaza_wlops_guvenlik_private" type="text" value="<?php echo get_site_option('kuaza_wlops_guvenlik_private'); ?>" /></td>
<td><?php _e(" Create for key: https://www.google.com/recaptcha/admin#createsite","wlops"); ?></td>
</tr>

<tr valign="top">
	<th><label for="kuaza_wlops_guvenlik_tema"><?php _e("Select theme for reCAPTCHA","wlops"); ?></label></th>
	<td>
	
	<select id="kuaza_wlops_guvenlik_tema" name="kuaza_wlops_guvenlik_tema">
	<option value="clean" <?php echo (get_site_option('kuaza_wlops_guvenlik_tema') == "clean" || get_site_option('kuaza_wlops_guvenlik_tema') == '' ? "selected" : ""); ?>><?php _e("Clean (default)","wlops"); ?></option>
	<option value="red" <?php echo (get_site_option('kuaza_wlops_guvenlik_tema') == "red" ? "selected" : ""); ?>><?php _e("Red","wlops"); ?></option>
	<option value="white" <?php echo (get_site_option('kuaza_wlops_guvenlik_tema') == "white" ? "selected" : ""); ?>><?php _e("White","wlops"); ?></option>
	<option value="blackglass" <?php echo (get_site_option('kuaza_wlops_guvenlik_tema') == "blackglass" ? "selected" : ""); ?>><?php _e("blackglass","wlops"); ?></option>
	</select>
	
	</td>
<td><?php echo sprintf(__("look themes: <a href='%s' target='_blank'>http://piclect.com/142894</a>","wlops"),'http://piclect.com/142894'); ?></td>
</tr>


<tr>
<td>
<hr><hr>
</td>
<td>
<hr><hr>
</td>
<td>
<hr><hr>
</td>
</tr>
<tr valign="top">
	<th><label for="kuaza_wlops_loop_direk"><?php _e("Title url direct source link for index and or query page (loop)?","wlops"); ?></label></th>
	<td><input class="yuzdeyuzyap" id="kuaza_wlops_loop_direk" name="kuaza_wlops_loop_direk" type="checkbox" value="yes" <?php $direct_link = get_site_option('kuaza_wlops_loop_direk'); if($direct_link && $direct_link == "yes") echo "checked"; ?>/></td>
<td><?php _e("Title url direct source link for index and or query page (loop)?","wlops"); ?></td>
</tr>

<tr valign="top">
	<th><label for="kuaza_wlops_hide_cat"><?php _e("Hide categories a adding page?","wlops"); ?></label></th>
	<td><input class="yuzdeyuzyap" id="kuaza_wlops_hide_cat" name="kuaza_wlops_hide_cat" type="checkbox" value="yes" <?php $hide_cat = get_site_option('kuaza_wlops_hide_cat'); if($hide_cat && $hide_cat == "yes") echo "checked"; ?>/></td>
<td><?php _e("Link or article shared page hide categories options?","wlops"); ?></td>
</tr>

<tr valign="top">
	<th><label for="kuaza_wlops_hide_tag"><?php _e("Hide tags a adding page?","wlops"); ?></label></th>
	<td><input class="yuzdeyuzyap" id="kuaza_wlops_hide_tag" name="kuaza_wlops_hide_tag" type="checkbox" value="yes" <?php $hide_tag = get_site_option('kuaza_wlops_hide_tag'); if($hide_tag && $hide_tag == "yes") echo "checked"; ?>/></td>
<td><?php _e("Link or article shared page hide tags options?","wlops"); ?></td>
</tr>

<tr valign="top">
	<th><label for="kuaza_wlops_hide_upload"><?php _e("Hide image uploads a adding page?","wlops"); ?></label></th>
	<td><input class="yuzdeyuzyap" id="kuaza_wlops_hide_upload" name="kuaza_wlops_hide_upload" type="checkbox" value="yes" <?php $hide_upload = get_site_option('kuaza_wlops_hide_upload'); if($hide_upload && $hide_upload == "yes") echo "checked"; ?>/></td>
<td><?php _e("Link or article shared page hide image uploads options?","wlops"); ?></td>
</tr>

<tr>
<td>
<hr><hr>
</td>
<td>
<hr><hr>
</td>
<td>
<hr><hr>
</td>
</tr>

<tr valign="top">
	<th><label for="wlops_default_resimler_nasil_gorunsun"><?php _e("How display wlops images area ?","wlops"); ?></label></th>
	<td>
	<?php echo '<select id="wlops_default_resimler_nasil_gorunsun" name="wlops_default_resimler_nasil_gorunsun">';
				$this->resim_boyutlarini_listele(get_site_option('wlops_default_resimler_nasil_gorunsun')); 
				echo '
				</select>'; ?>
	</td>
<td><?php _e("How display wlops images area ?","wlops"); ?></td>
</tr>

<tr>
<td>
<hr><hr>
</td>
<td>
<hr><hr>
</td>
<td>
<hr><hr>
</td>
</tr>


<tr valign="top">
	<th><label for="wlops_konu_temasi"><?php _e("Default link post themes","wlops"); ?></label></th>
	<td><textarea style="width:100%; height:250px;" class="yuzdeyuzyap" id="wlops_konu_temasi" name="wlops_konu_temasi">
<?php if(get_site_option( 'wlops_konu_temasi' )) { echo stripslashes(get_site_option( 'wlops_konu_temasi' )); }else{ ?>
{{content}}
<div class="wlops_devam_linki"><a title="<?php _e("Out for {{post_title}}","wlops"); ?>" href="{{out_redirect_url}}" target="_blank" class="wlops_read_more story-title-link story-link" rel="bookmark" itemprop="url">
<?php _e("Read more <em>({{out_count}})</em>","wlops"); ?></a> {{direct_source_url}}</div>
<hr>
{{wlops_images}}
<?php } ?></textarea></td>
<td><?php _e("Wlops Templates label:","wlops"); ?>
<ol>
<li>{{content}} : <?php _e("Default content (Summary)","wlops"); ?></li>
<li>{{out_count}} : <?php _e("Out count","wlops"); ?></li>
<li>{{out_redirect_url}} : <?php _e("Out redirect url (for count out and redirect page)","wlops"); ?></li>
<li>{{wlops_images}} : <?php _e("List added images","wlops"); ?></li>
<li>{{post_id}} : <?php _e("Post ID","wlops"); ?></li>
<li>{{post_title}} : <?php _e("Post title","wlops"); ?></li>
<li>{{site_url}} : <?php _e("site url","wlops"); ?></li>
<li>{{direct_source_url}} : <?php _e("Original source url","wlops"); ?></li>
</ol></td>
</tr>
<tr>
<td>
<hr><hr>
</td>
<td>
<hr><hr>
</td>
<td>
<hr><hr>
</td>
</tr>

<tr valign="top">
	<th><label for="kuaza_wlops_css"><?php _e("css for wlops area","wlops"); ?></label></th>
	<td><textarea style="width:100%; height:250px;" class="yuzdeyuzyap" id="kuaza_wlops_css" name="kuaza_wlops_css">
<?php echo $this->wlops_eklenti_css(); ?>
</textarea></td>
<td><?php _e("Css for wlops area","wlops"); ?></td>
</tr>

<tr>
<td>
<input id="ayarlariguncelle" name="ayarlariguncelle" type="hidden" value="evet" />
<p class="submit"><input type="submit" class="button-primary" value="<?php _e("Update settings","wlops"); ?>" /></p>

</td>
</tr>
<tr>
<td>
<hr><hr>
</td>
<td>
<hr><hr>
</td>
<td>
<hr><hr>
</td>
</tr>
</table>
</form>

<div>
<h3><?php _e("Example wlops submit page ;)","wlops"); ?></h3>
<textarea style="width:100%; height:350px;">
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
</textarea>
</div>
<?php
}

	/*
	 * menu olusturma
	 *
	*/
	public function kuaza_wlops_menuolustur(){
		?>
		<a href="<?php echo get_site_url(); ?>/wp-admin/edit.php?page=wlops">
		<?php _e("<h3>Plugins index</h3>","wlops"); ?>
		<a href="<?php echo get_site_url(); ?>/wp-admin/edit.php?page=wlops&islem=ayarlariguncelle">
		<?php _e("<h3>Settings</h3>","wlops"); ?>
		</a>
		<hr />

		<?php
	}

/*
*
*
*
* admin setting bitis
*
*
*
*
*/




	/**
	 * konu duzenleme sayfasina wlops options alanini ekleriz.
	 */
	public function add_meta_box( $post_type ) {
		global $post;
		$wlops_kontrol = get_post_meta( $post->ID, '_wlops', true );
		
		if(!$wlops_kontrol)
		return false;
		
            $post_types = array('post', 'page');     //limit meta box to certain post types
            if ( in_array( $post_type, $post_types )) {
		add_meta_box(
			'wlops_options'
			,__( 'Wlops options', 'wlops' )
			,array( $this, 'render_wlops_konuda_resim_goster' )
			,$post_type
			,'advanced'
			,'high',
			array("wlops_konu_temasi","wlops_resimler_nasil_gorunecek","wlops_loop_direk","wlops_link")
		);
            }
	}

	/**
	 * Save the meta when the post is saved.
	 * 
	 * @param int $post_id The ID of the post being saved.
	 */
	public function save_meta_options( $post_id ) {
	
		/*
		 * We need to verify this came from the our screen and with proper authorization,
		 * because save_post can be triggered at other times.
		 */

		// Check if our nonce is set.
		if ( ! isset( $_POST['wlops_inner_custom_box_nonce'] ) )
			return $post_id;

		$nonce = $_POST['wlops_inner_custom_box_nonce'];

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $nonce, 'wlops_inner_custom_box' ) )
			return $post_id;

		// If this is an autosave, our form has not been submitted,
                //     so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return $post_id;

		// Check the user's permissions.
		if ( 'page' == $_POST['post_type'] ) {

			if ( ! current_user_can( 'edit_page', $post_id ) )
				return $post_id;
	
		} else {

			if ( ! current_user_can( 'edit_post', $post_id ) )
				return $post_id;
		}

		/* OK, its safe for us to save the data now. */

		// konuyla alakali wlops ayarlarini guncelleriz

		$wlops_data_2 = !empty($_POST['wlops_konu_temasi']) ? ( $_POST['wlops_konu_temasi'] ) : "" ;
		update_post_meta( $post_id, '_wlops_konu_temasi', isset($wlops_data_2) && !empty($wlops_data_2) ? $wlops_data_2 : "" );
		
		$wlops_data_3 = !empty($_POST['wlops_resimler_nasil_gorunecek']) ? sanitize_text_field( $_POST['wlops_resimler_nasil_gorunecek'] ) : "" ;
		update_post_meta( $post_id, '_wlops_resimler_nasil_gorunecek', isset($wlops_data_3) ? $wlops_data_3 : "thumbnail" );	
		
		$wlops_data_4 = !empty($_POST['wlops_loop_direk']) ? sanitize_text_field( $_POST['wlops_loop_direk'] ) : "" ;
		update_post_meta( $post_id, '_wlops_loop_direk', !empty($wlops_data_4) ? "yes" : "no" );		
		
		if(!empty($_POST['wlops_link'])){
		$wlops_data_5 = sanitize_text_field( $_POST['wlops_link'] );
		update_post_meta( $post_id, '_wlops_link', $wlops_data_5 );	
		}
							
	}


	/**
	 * konu duzenleme yada ekleme sayfasinda wlops options bolumunu gosterelim, ayiklayalim..
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_wlops_konuda_resim_goster( $post, $metabox ) {
	
		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'wlops_inner_custom_box', 'wlops_inner_custom_box_nonce' );

		// Use get_post_meta to retrieve an existing value from the database.
		

		foreach($metabox['args'] as $wlops_meta){

			$value = get_post_meta( $post->ID, '_'.$wlops_meta, true );
				$wlops_nedir = get_post_meta( $post->ID, '_wlops', true );
				
			if($wlops_meta == "wlops_konu_temasi"){
			$wlops_aciklama_meta = __("For post themes label <em>(Leave blank for default)</em>","wlops");

				echo '<div class="yuzdeyuzyap">';
				echo '<label for="'.$wlops_meta.'" class="yuzdeyuzyap">'.$wlops_aciklama_meta.'</label>
				<textarea style="width:100%;height:150px;" id="'.$wlops_meta.'" name="'.$wlops_meta.'">'.$value.'</textarea>
				';
				?>
				<?php _e("Wlops Templates label:","wlops"); ?>
				<ol>
				<li>{{content}} : <?php _e("Default content (Summary)","wlops"); ?></li>
				<li>{{out_count}} : <?php _e("Out count","wlops"); ?></li>
				<li>{{out_redirect_url}} : <?php _e("Out redirect url (for count out and redirect page)","wlops"); ?></li>
				<li>{{wlops_images}} : <?php _e("List added images","wlops"); ?></li>
				<li>{{post_id}} : <?php _e("Post ID","wlops"); ?></li>
				<li>{{post_title}} : <?php _e("Post title","wlops"); ?></li>
				<li>{{site_url}} : <?php _e("site url","wlops"); ?></li>
				<li>{{direct_source_url}} : <?php _e("Original source url","wlops"); ?></li>
				</ol>				
				<?php
				echo '</div><br>';


			}elseif($wlops_meta == "wlops_resimler_nasil_gorunecek"){
			$wlops_aciklama_meta = __("How display wlops images area ?","wlops"); 

				echo '<div class="yuzdeyuzyap">
				<select id="'.$wlops_meta.'" name="'.$wlops_meta.'">
				<option value="">'.__("Select","wlops").'</option>
				 ';
				$this->resim_boyutlarini_listele($value); 
				echo '
				</select>
				<label for="'.$wlops_meta.'" class="yuzdeyuzyap">'.$wlops_aciklama_meta.'</label></div><br>';
			
			}elseif($wlops_meta == "wlops_loop_direk" && $wlops_nedir == "link"){
			$wlops_aciklama_meta = __("Title url direct source link for index and or query page (loop)?","wlops"); 
			
			$wlops_direk_kontrol = (!empty($value) ? $value : get_site_option( 'kuaza_wlops_loop_direk' ));
				echo '<div class="yuzdeyuzyap">';
				echo '<input type="checkbox" id="'.$wlops_meta.'" name="'.$wlops_meta.'" size="25" value="yes" '.($wlops_direk_kontrol=="yes" ? "checked" : "").' />'; 
				echo '<label for="'.$wlops_meta.'" class="yuzdeyuzyap">'.$wlops_aciklama_meta.'</label></div><br>';
			
			}elseif($wlops_meta == "wlops_link" && $wlops_nedir == "link"){
			$wlops_aciklama_meta = __("Source url for direct page","wlops"); 
			
				echo '<div class="yuzdeyuzyap">';
				echo '<label for="'.$wlops_meta.'" class="yuzdeyuzyap">'.$wlops_aciklama_meta.'</label></div><br>';
				echo '<input type="text" id="'.$wlops_meta.'" name="'.$wlops_meta.'" size="25" style="width:100%;" value="'.$value.'" />'; 
				
			}else{

			}

		}		
		

	}
	
	/**
	 * sitedeki resim boyutlarini listeler
	 * @public
	 */		
	public function resim_boyutlarini_listele($secim = false){
	 global $_wp_additional_image_sizes;
		$sizes = array();
		foreach( get_intermediate_image_sizes() as $s ){
			$sizes[ $s ] = array( 0, 0 );
			if( in_array( $s, array( 'thumbnail', 'medium', 'large' ) ) ){
				$sizes[ $s ][0] = get_option( $s . '_size_w' );
				$sizes[ $s ][1] = get_option( $s . '_size_h' );
			}else{
				if( isset( $_wp_additional_image_sizes ) && isset( $_wp_additional_image_sizes[ $s ] ) )
					$sizes[ $s ] = array( $_wp_additional_image_sizes[ $s ]['width'], $_wp_additional_image_sizes[ $s ]['height'], );
			}
		}

		foreach( $sizes as $size => $atts ){
			echo '<option value="' . $size . '" '.($secim == $size ? "selected" : "").'>' . $size . ' ' . implode( 'x', $atts ) . ' '.("thumbnail" == $size ? __(" - (side by side)","wlops") : "").'</option>';
		}

	}
	
	/**
	 * Resim yuklemek icin ajax form olayini entegre ederik
	 * @static
	 */	
	public function file_upload_form() {
		wp_enqueue_script(
			'kuaza_wlops_upload',
			plugins_url( '/js/jquery.form.js' , __FILE__ )
		);
	}
	
	/**
	 * single de ve loop icindeki titleyi degistirme fonksiyonu, step 1
	 * @public
	 * // http://redactweb.com/filter-a-page-posts-title-only-on-that-page-post/
	 */	
	public function condition_filter_title($array){
		global $wp_query;
		if($array === $wp_query){
			add_filter( 'the_title', array( $this, 'wlops_link_baslik_degistir'), 10, 2);
		}else{
			remove_filter('the_title',array( $this, 'wlops_link_baslik_degistir'), 10, 2);
		}
	}
	
public function append_query_string( $url, $post, $leavename ) {

	if(is_admin())
	return $url;
	
	// konunun link olup olmadigini cekeriz
	//$konu_link_kontrol = get_post_format( $post->ID );
	// wlops kimligini cekeriz yazinin..
	$wlops_kontrol = get_post_meta( $post->ID, '_wlops', true );
	
	if($wlops_kontrol != "link")
	return $url;
	
	// yaz basligi direk link olsunmu ayarini cekeriz. Loop ve ana sorgular icin..
	$wlops_direk_kontrol = get_post_meta( $post->ID, '_wlops_loop_direk', true );
	$this->wlops_direk_kontrol_2 = (!empty($wlops_direk_kontrol) ? $wlops_direk_kontrol : get_site_option( 'kuaza_wlops_loop_direk' ));
	
 
	if($this->wlops_direk_kontrol_2 == "yes")
		$url = add_query_arg( 'wlops_out', $post->ID, $url );
		
		/*
		// url rewrite uygulanmis ise ona gore url yapisina mudahale ediyoruz.	
		if ( '' != get_option('permalink_structure') ) {
			$url = add_query_arg( 'wlops_out', $post->ID, $url ); //$url = user_trailingslashit( rtrim($url,'/') . '/out/'.$post->ID ); // linkin sonuna cikis baglanti parametremizi ekliyoruz
				} else {
					$url = add_query_arg( 'wlops_out', $post->ID, $url ); // Normal wlops cikis parametresini ekleriz
						}
		*/
		
	return $url;
}
 	
	/**
	 * link baglantilar icin single alaninda basliga direk link ekler, step 2
	 * @public
	 */		
	public function wlops_link_baslik_degistir($title="", $id = null) {
	global $post, $posts;

	//return $posts[0]->ID;
	// fix: bazi temalarda ileri-geri linkler yada bilesenlerdeki looplarda bu filtreye takiliyor, onlari kurtaralim dimi :)
	if($posts[0]->ID != $id)
	return $title;
	
	// konunun link olup olmadigini cekeriz
	//$konu_link_kontrol = get_post_format( $post->ID );
	// wlops kimligini cekeriz yazinin..
	$wlops_kontrol = get_post_meta( $post->ID, '_wlops', true );
	
	// tekil syafa degilse yada link degilse direk title yazdirilir.
	if(!is_single() || $wlops_kontrol != "link")
	return $title;

	return '<a href="'.add_query_arg( 'wlops_out', $post->ID, get_permalink() ).'" target="_blank" class="story-title-link story-link wlops_link" rel="bookmark" itemprop="url">'.$title.'</a>';

	
	}
	
	/**
	 * Sayfadaki cikis linkinin kac kere ziyaret edildigini gosteririz
	 * temada gosterme: wlops_cikis_sayisi()
	 * @public
	 */	
	public function wlops_konu_cikis_sayisi($konuid = ''){
	
		if($konuid ==''){
			global $post;
				$konuid = $post->ID;
		}
	
		$wlops_cikis_sayisi = get_post_meta( $konuid, '_wlops_views', true );
		
	return $wlops_cikis_sayisi ? $wlops_cikis_sayisi : "0";
	}
	
	/**
	 * Site icin shortcode ekler, gonderme alani icin
	 * @public
	 */		
	public function wlops_yazi_ici_otomatik($content){
	global $post;
	
	$wlops_icerik = array();
	
	$wlops_icerik["content"] = $content;
	
	$wlops_kontrol = get_post_meta( $post->ID, '_wlops', true );
	
	if(!is_single() || $wlops_kontrol != "link")
	return $wlops_icerik["content"];

	// wlops konuya ozel konu temasini cekeriz..
	$wlops_konuya_ozel_tema = get_post_meta( $post->ID, '_wlops_konu_temasi', true );
	
	$wlops_icerik["post_id"] = $post->ID;
	$wlops_icerik["post_title"] = $post->post_title;
	// wlops cikis sayisini cekeriz..
	$wlops_icerik["out_count"] = get_post_meta( $post->ID, '_wlops_views', true );
	
	// direk kaynagi cekeriz
	$wlops_icerik["direct_source_url"] = get_post_meta( $post->ID, '_wlops_link', true );
	
 	// konuya yuklenen resimleri listeleriz	
	$wlops_icerik["wlops_images"] = $this->wlops_yaziya_ait_resimleri_listele();
	
	$wlops_icerik["site_url"] = get_site_url();
	
	$redirekt_url = add_query_arg( 'wlops_out', $post->ID, get_permalink() );
	
	$wlops_icerik["out_redirect_url"] = $redirekt_url;
	
	$wlops_icerik["get_permalink"] = get_permalink();

			$wlops_konu_temasi = (!empty($wlops_konuya_ozel_tema) ? $wlops_konuya_ozel_tema : (get_site_option( 'wlops_konu_temasi' ) ? get_site_option( 'wlops_konu_temasi' ) : "{{content}}"));

			if ( !empty($wlops_icerik) && is_array($wlops_icerik) ) :

			foreach ($wlops_icerik as $code => $value)
				$wlops_konu_temasi = str_replace('{{'.$code.'}}', $value, $wlops_konu_temasi);
			
			endif;
			
	return stripslashes($wlops_konu_temasi);

	}
	
	/**
	 * Admin haric ana sorgulardan link baglantilarini cikartmaya yarar (ana sayfa, kategori, arama v.s)
	 * @public
	 * ---
	 * ana sorgulardan link baglantilari cikartmaya calisir..
	 * http://www.josscrowcroft.com/2011/code/wordpress-exclude-post-formats-aside-status-from-rss-feeds/
	 */
	public function wlops_link_icerikleri_kaldirma_loopdan($query) {
		
		// link yazilarinin listelendigi sayfada islemi yapmayiz. Yoksa link turundeki konulari gostermez
		if ( is_tax( 'post_format', 'post-format-link' ) )
		return;
		
		if ( is_admin() || is_single() || ! $query->is_main_query() )
			return;

			// Array of post formats to exclude, by slug,
			// e.g. "post-format-{format}"
			$post_formats_to_exclude = array(
				'post-format-link'
			);
			
			// Extra query to hack onto the $wp_query object:
			$extra_tax_query = array(
				'taxonomy' => 'post_format',
				'field' => 'slug',
				'terms' => $post_formats_to_exclude,
				'operator' => 'NOT IN'
			);
			
			 $tax_query = array( $extra_tax_query );
			 
			$query->set( 'tax_query', $tax_query );
			
			return $query;

	}
	
	/**
	 * Site icin shortcode ekler, gonderme alani icin
	 * @public
	 */	
    public function wlops_eklenti_css() {
	if(get_site_option( 'kuaza_wlops_css' )) { echo stripslashes(get_site_option( 'kuaza_wlops_css' )); }else{ /* ?>
<style type="text/css" id="wlops-header-css">

.btn {
display: inline-block;
padding: 6px 12px;
margin-bottom: 0;
font-size: 14px;
font-weight: normal;
line-height: 1.42857143;
text-align: center;
white-space: nowrap;
vertical-align: middle;
cursor: pointer;
-webkit-user-select: none;
-moz-user-select: none;
-ms-user-select: none;
user-select: none;
background-image: none;
border: 1px solid transparent;
border-radius: 4px;
}
.btn:focus,
.btn:active:focus,
.btn.active:focus {
outline: thin dotted;
outline: 5px auto -webkit-focus-ring-color;
outline-offset: -2px;
}
.btn:hover,
.btn:focus, .btn:visited {

text-decoration: none;
}
.btn:active,
.btn.active {
background-image: none;
outline: 0;
-webkit-box-shadow: inset 0 3px 5px rgba(0, 0, 0, .125);
box-shadow: inset 0 3px 5px rgba(0, 0, 0, .125);
color: #222222;
}
.btn.disabled,
.btn[disabled],
fieldset[disabled] .btn {
pointer-events: none;
cursor: not-allowed;
filter: alpha(opacity=65);
-webkit-box-shadow: none;
box-shadow: none;
opacity: .65;
}
a.btn{
text-decoration:none;
color:#222222
}
.btn-readmore, button.btn-readmore {
background: #ddd;
border-radius: 2;
color: #222222
}
.clearfixet{
clear:both;
}
.wlops_cevre_class{
margin-bottom:10px;
width:100%
}
.yuzdeyuzyap {
width: 100%;box-sizing: border-box;-webkit-box-sizing:border-box;-moz-box-sizing: border-box;
}
.kategoriklass {
clear:both;margin-bottom:10px;
width: 100%;box-sizing: border-box;-webkit-box-sizing:border-box;-moz-box-sizing: border-box;
}
.inputcevreklass {
clear:both;
margin-bottom:10px;
width: 100%;
}
.inputcevreklass textarea{
margin-bottom:-5px;
height:100px;
width: 100%;
}		
.eklemebasarili{
color:#f2f2f2;
font-weight:bold;
}
.eklemebasarili em{
color:#CCD9FF;
font-weight:normal;
}
.eklemebasarili em a:visited{
color:#CCD9FF;
font-weight:normal;
}
.ustbilgicevre{
margin-bottom:10px;
background: #F78181;
  border: 1px solid transparent;
border-radius: 2;
padding:10px;
color: #f2f2f2
}
.ustbilgicevre a{
color:#ffffff
}
.ustbilgicevre a:visited{
color:#ffffff
}
#Wlops_kligg { display: block; background: #f6f6f6; border-radius: 3px; padding: 15px }


#wlops_form { width:100%;display: inline-block; margin: 0 auto; background: #F5F6CE; border-radius: 3px; padding: 15px }
.progress { margin:10px auto;position:relative; width:auto; border: 1px solid #ddd; padding: 1px; border-radius: 3px; display:none; }
.bar { background-color: #B4F5B4; width:0%; height:20px; border-radius: 3px; }
.percent { position:absolute; display:inline-block; top:1px; left:48%; }

.hataolustu{
color:#D30221;
font-weight:bold;
}
.upload_resimler_cevre{
width:auto;
margin:auto;

display:inline-block;

}
.upload_resimler_tek{
float:left;
padding:2px;
margin-top:5px;
margin-right:5px;
border:1px solid #ccc;
}

.resim_yukleme_alani_cevre{
margin-top:20px;
width:100%;
}
.resim_yukleme_alani_cevre h3{
font-weight:bold;
font-size:20px;
}
.hemenyukle_secmealani{
float:left;
}
.hemenyukle_buton{
float:right;
}
.yazi_saga_yasla{
text-align:right;
}
#wlops_submit{
}
.link_text_button_cevre{
float:left;
}
.wlops_submit{
float:right;
margin-top:10px;
}
.wlops_floatright{
float:right;
}
.alt_butonlar{
display:inline-block;
width:100%;
margin:auto;
}
.wlops_topcontent{
display:none;
}
.wlops_bottomcontent{
display:none;
}
.wlops_topcontent_step2{
display:none;
}
.wlops_bottomcontent_step2{
display:none;
}
.guvenlik_cevre{
background:#ffffff;
}
</style>
	<?php */
	}
		}
	
	/**
	 * Site icin shortcode ekler, gonderme alani icin
	 * @public
	 */	
    public function wlopsform_func( $atts, $content="" ) {

	 $this->timestamp = time();
	 $this->token = md5('unique_salt' . $this->timestamp);
	 
		//https://developers.google.com/recaptcha/
		require_once(WLOPS_PLUGIN_DIR . 'recaptcha/recaptchalib.php');

		// Get a key from https://www.google.com/recaptcha/admin/create
		$this->publickey = get_site_option( 'kuaza_wlops_guvenlik_public' );
		$this->privatekey = get_site_option( 'kuaza_wlops_guvenlik_private' );

		# the response from reCAPTCHA
		$resp = null;
		
		# the error code from reCAPTCHA, if any
		$this->error = null;
	 
	 	ob_start(); 
	
			// sitep 2 
		if(!empty($_POST)){
		
			if(isset($_POST["islem"]) && $_POST["islem"] == "sonislem"){
			
			//icerik ekleme alani
			$this->icerik_ekleme_durumu = $this->wlops_icerik_ekle();
			
				if(!$this->icerik_ekleme_durumu){
					$this->error = __("Error added content, please try again.","wlops");
				}else{
				
					if($this->direk_yayinlansin_mi){
					$this->sayfa_linki_permalink = get_permalink( $this->post_id );
					$this->sayfa_linki_title = get_the_title( $this->post_id );
					
					//$this->link_url = '<a href="'.$this->sayfa_linki_permalink.'" target="_blank">'.$this->sayfa_linki_title.'</a>';
					
					$this->error = sprintf(__("<div class='eklemebasarili'>Adding content is successful, congratulations.<br /><em><a href='%s' target='_blank'>%s</a></em></div>","wlops"),$this->sayfa_linki_permalink,$this->sayfa_linki_title);
					
					}else{
					$this->error = __("<div class='eklemebasarili'>Adding content is successful, congratulations.<br /><em>Will be published after approval.</em></div>","wlops");
					
					}
				}
			
			// form step 1 - icerik eklendikten sonra ilk form alanini gosteririz.
			$this->wlops_form_alani();
				
			}else{

		// guvenlik kontrolune baslayalim mi? :)
		if (isset($_POST["recaptcha_response_field"])) {
				$resp = recaptcha_check_answer ($this->privatekey,
												$_SERVER["REMOTE_ADDR"],
												$_POST["recaptcha_challenge_field"],
												$_POST["recaptcha_response_field"]);
				// guvenlik kodu dogru ise
				if ($resp->is_valid) {
						$this->guvenligi_gectimi = true;
						
				// guvenlik kodu yanlis ise
				} else {
						# set the error code so that we can display it
						$this->guvenligi_gectimi = false;
						$this->error = $resp->error;
				}
				
		// keyler yanlis yada bos ise
		} else {
                # set the error code so that we can display it
				$this->guvenligi_gectimi = false;
                $this->error = __("Empty reCAPTCHA Public key and Private key","wlops");
        }
				// guvenlik kodu hatali ise yada doldurmamis ise
				if(!$this->guvenligi_gectimi){
					$this->error = sprintf(__("You have not passed through security: %s","wlops"),$this->error);
						
						// form step 1
						$this->wlops_form_alani();
						
				// link gonderilirse
				}elseif(!empty($_POST['wlops_submit_link']) && $this->guvenligi_gectimi){
				 
					$this->url_link_control = $this->wlops_url_kontrol();
					
					if(!$this->url_link_control){
						
						$this->error = __("Not valid url","wlops");
						
						// form step 1
						$this->wlops_form_alani();

					}else{
					
					$this->wlops_submit_link = $_POST['wlops_submit_link'];
		
					
					$meta_bilgi_al = array( 'posts_per_page' => '1', 'meta_key'=> '_wlops_link', 'meta_value' => $this->wlops_submit_link );

						$link_varmi_kontrolu = get_posts( $meta_bilgi_al );

						if($link_varmi_kontrolu){
							$konu = array_shift($link_varmi_kontrolu);

					$sayfa_linki_permalink = get_permalink( $konu->ID );
					$sayfa_linki_title = get_the_title( $konu->ID );
	
					$this->error = sprintf(__("<div class='eklemehatali'>This link was previously added.<br /><em><a href='%s' target='_blank'>%s</a></em></div>","wlops"),$sayfa_linki_permalink,$sayfa_linki_title);
					
						// form step 1
						$this->wlops_form_alani();
							
						}else{
					
							// Yuklenecek resimler icin onceden yazi alani olustururuz.
							$draft_konu_olustur = array(
							  'post_title'    => "wlops coming :)",
							  'post_content'  => "",
							  'post_status'   => 'draft',
							  'post_author'   => $this->kullanici_ID,
							  'post_type' => 'post'
							);

							// Insert the post into the database - /?p=112
							$this->konu_id = wp_insert_post( $draft_konu_olustur );
							
							$this->sayfa_linki_permalink = get_permalink( $this->konu_id );
				 
						 $this->url_content = @file_get_contents($this->wlops_submit_link);

						$this->get_meta_karakter();
						
						if($this->get_meta_karakter != "UTF-8")
						$this->url_content = mb_convert_encoding($this->url_content, 'UTF-8', 
						mb_detect_encoding($this->url_content, 'UTF-8, '.$this->get_meta_karakter, true));
						
						// Sayfadaki title bilgisini ceker.
						$this->url_meta_title = $this->get_meta_title();
						
						// url cozumler ve meta ile baslayan etiketleri array dizgesinde toplar
						$this->url_meta_digerleri = get_meta_tags($this->wlops_submit_link);

						// form step 2 - link icin
						$this->wlops_form_alani_2();

						}
				}
				
				// Yazi gonderilirse
				}elseif(!empty($_POST['wlops_baslik']) && $this->guvenligi_gectimi){
				
						// post title bilgisini degiskene atar.
						$this->url_meta_title = $_POST["wlops_baslik"];	
							
						$this->url_meta_digerleri["description"] = ""; //$_POST["wlops_submit_text"];				
				
					if(!empty($this->url_meta_title)){

						$this->url_meta_digerleri["keywords"] = !empty($_POST["wlops_submit_tags"]) ? $_POST["wlops_submit_tags"] : "";
												
								// Yuklenecek resimler icin onceden yazi alani olustururuz.
								$draft_konu_olustur = array(
								  'post_title'    => $this->url_meta_title,
								  'post_content'  => "",
								  'post_status'   => 'draft',
								  'post_author'   => $this->kullanici_ID,
								  'post_type' => 'post'
								);

								// Insert the post into the database - /?p=112
								$this->konu_id = wp_insert_post( $draft_konu_olustur );
								
								$this->sayfa_linki_permalink = get_permalink( $this->konu_id );

							// form step 2 - link icin
							$this->wlops_form_alani_2();
					}else{
					
						$this->error =  __("Empty title :/","wlops");
						
						// form step 1
						$this->wlops_form_alani();					
					}
				// ikiside bos ise
				}else{
				
				$this->error =  __("Empty progress :/","wlops");
				
				// form step 1
				$this->wlops_form_alani();
				
				}
			
			}
		
		
		// step:1 - post bos ise
		}else{
			
			// form step 1
			$this->wlops_form_alani();

		}
			return ob_get_clean();
	}
	
	
	
	/**
	 * Form 1, HTML -1 kismi
	 * @public
	 */
	public function wlops_form_alani(){
 
		// hata bulunursa formun en ustunde bunu gosteririz..
		if(!empty($this->error)){
		echo '<div id="" class="ustbilgicevre">'.$this->error.'</div>';
		}
	?>
	<script>
	var RecaptchaOptions = {
	   theme : '<?php echo (get_site_option( 'kuaza_wlops_guvenlik_tema' ) != '' ? get_site_option( 'kuaza_wlops_guvenlik_tema' ) : 'clean'); ?>'
	};
	</script>

	<div id="wlops_cevre" class="wlops_cevre_class">
		<form action="" method="post" enctype="multipart/form-data" id="Wlops_kligg">
		<input type="hidden" name="timestamp" id="timestamp" value="<?php echo $this->timestamp; ?>"/>
		<input type="hidden" name="token" id="token" value="<?php echo $this->token; ?>"/>
		<input type="hidden" name="islem" id="islem" value="kontrol"/>

			<div class="link_form" style="display:block;">
				<div class="inputcevreklass">
						<div class="clearfixet alt_butonlar">
							<div class="link_text_button_cevre"><?php _e("<strong>Submit link</strong>","wlops"); ?></div>
							<button class="texticintikla wlops_floatright" type="button"><?php _e("Article","wlops"); ?></button>
						</div>
				<input class="yuzdeyuzyap" type="text" name="wlops_submit_link" id="wlops_submit_link" value="" placeholder="http://" autofocus="autofocus" />
				</div>
			</div>
		
				<div class="text_form" style="display:none;">

					<div class="inputcevreklass">
						<div class="clearfixet alt_butonlar">
							<div class="link_text_button_cevre"><?php _e("<strong>Submit article</strong>","wlops"); ?></div>
							<button class="linkicintikla wlops_floatright" type="button"><?php _e("Link","wlops"); ?></button>
						</div>
					<input class="yuzdeyuzyap" type="text" name="wlops_baslik" id="wlops_baslik" value="" placeholder="<?php _e("Title for content","wlops"); ?>" />
					</div>
				</div>
			<div class="guvenlik_cevre"><?php
			if(empty($this->publickey) || empty($this->privatekey)){
			_e("Please insert reCAPTCHA Public key and Private key there admin wlops setting page..","wlops");
			}else{
			echo recaptcha_get_html($this->publickey, $this->error);
			}
			?></div>		
			<div class="clearfixet alt_butonlar">
				<input id="wlops_submit" class="wlops_submit" type="submit" value="<?php _e("Next step","wlops"); ?>" />
			</div>			
		</form>
	</div>

		<script>
		jQuery(document).ready(function($) {
				jQuery('.wlops_topcontent').fadeIn("slow"); //ilk sayfa form ustunu gosterme
				jQuery('.wlops_bottomcontent').fadeIn("slow");  //ilk sayfa form altini gosterme
				
		jQuery(".linkicintikla").live("click",function(e) {
		e.preventDefault();

		$link = jQuery(".link_form");
		$text = jQuery(".text_form");
		
							$text.hide();
							$link.fadeIn(500, function(){
							
								//jQuery("#yukleniyorikonu").remove();
								//loading = false;
							});	
		});
		jQuery(".texticintikla").live("click",function(e) {
		e.preventDefault();

		$link = jQuery(".link_form");
		$text = jQuery(".text_form");
							
							$link.hide();
							$text.fadeIn(500, function(){
								//jQuery("#yukleniyorikonu").remove();
								//loading = false;
							});	
		});
		});
		</script>
	<?php
	
	}
	
	/**
	 * Form 2, HTML - 2 kismi : link gonderimlerinde 2. kismi olusturur.
	 * @public
	 */
	public function wlops_form_alani_2(){

	if(!$this->guvenligi_gectimi){
		$this->error = __("What are you doing here?","wlops");
							
		// form step 1
		$this->wlops_form_alani();

		return false;
	}
		// hata bulunursa formun en ustunde bunu gosteririz..
		if(!empty($this->error)){
		echo '<div id="" class="Ustbilgicevre">'.$this->error.'</div>';
		}
		
		if(!empty($this->wlops_submit_link)){
		
			if(!empty($this->url_meta_digerleri["description"])){
			
				//aciklama icin karakter cevirisi yaptiririz.
				if($this->get_meta_karakter != "UTF-8")
				$this->url_meta_digerleri["description"] = mb_convert_encoding($this->url_meta_digerleri["description"], 'UTF-8', 
				mb_detect_encoding($this->url_meta_digerleri["description"], 'UTF-8, '.$this->get_meta_karakter, true));
			}else{
				$this->url_meta_digerleri["description"] = "";
			}
			
			if(!empty($this->url_meta_digerleri["keywords"])){
				//etiketler icin karakter cevirisi yaptiririz.
				if($this->get_meta_karakter != "UTF-8")				
				$this->url_meta_digerleri["keywords"] = mb_convert_encoding($this->url_meta_digerleri["keywords"], 'UTF-8', 
				mb_detect_encoding($this->url_meta_digerleri["keywords"], 'UTF-8, '.$this->get_meta_karakter, true));
			}else{
				$this->url_meta_digerleri["keywords"] = "";
			}
		}
	?>
	<div id="wlops_cevre" class="wlops_cevre_class">
		<form action="" method="post" enctype="multipart/form-data" id="Wlops_kligg">
		<input type="hidden" name="timestamp" id="timestamp" value="<?php echo $this->timestamp; ?>" />
		<input type="hidden" name="token" id="token" value="<?php echo $this->token; ?>" />
		<input type="hidden" name="islem" id="islem" value="sonislem" />
			<?php if(!empty($this->wlops_submit_link)){ ?>
				<input type="hidden" name="wlops_submit_link" id="wlops_submit_link" value="<?php echo $this->wlops_submit_link; ?>" />
			<?php } ?>
			
		<input type="hidden" name="wlops_konu_id" id="wlops_konu_id" value="<?php echo $this->konu_id; ?>" />
			
			<?php if(!empty($this->wlops_submit_link)){ ?>
				<div class="link_form inputcevreklass">
				<?php _e("<strong>Source link:</strong> ","wlops"); ?> <?php echo $this->wlops_submit_link; ?>
					<div class="yuzdeyuzyap" style="margin-left:20px;">
						<?php _e("<em>Draft link:</em> ","wlops"); ?> <?php echo $this->sayfa_linki_permalink; ?>
					</div>
				</div>
			<?php } ?>
				<div class="text_form">
				
					<div class="inputcevreklass">
					<?php _e("<strong>Post title</strong>","wlops"); ?>
						<?php if(!empty($this->wlops_submit_link)){ ?>
							<input class="yuzdeyuzyap" type="text" name="wlops_baslik" id="wlops_baslik" value="<?php echo stripslashes($this->url_meta_title); ?>" />
					<div class="yuzdeyuzyap">
					<?php _e("<small><em>Please enter the title of the story you are linking to.</em></small>","wlops"); ?>
					</div>
					<?php }else{ ?>
							<div class="yuzdeyuzyap">
								<?php echo stripslashes($this->url_meta_title); ?>
							</div>
						<?php } ?>
					</div>
 
					<div class="inputcevreklass">
					<?php _e("<strong>Post content</strong>","wlops"); ?> <?php _e("<em>(required)</em>","wlops"); ?>
					<textarea class="yuzdeyuzyap" name="wlops_submit_text" id="wlops_submit_text"><?php echo $this->url_meta_digerleri["description"]; ?></textarea>
					<div class="yuzdeyuzyap">
						<?php if(!empty($this->wlops_submit_link)){ ?>
							<?php _e("<small><em>Write your own description of the news story you are submitting. It should be about 2 to 4 sentences long.</em></small>","wlops"); ?>
						<?php }else{ ?>
							<?php _e("<small><em>Write your own description of the news story you are submitting.</em></small>","wlops"); ?>
						<?php } ?>
					</div>
					</div>
					
					<?php $hide_tag = get_site_option( 'kuaza_wlops_hide_tag' ); if($hide_tag != "yes"){ ?>	
					<div class="inputcevreklass">
					<?php _e("<strong>Tags</strong>","wlops"); ?>
					<input class="yuzdeyuzyap" type="text" name="wlops_submit_tags" id="wlops_submit_tags" value="<?php echo $this->url_meta_digerleri["keywords"]; ?>" />
					<div class="yuzdeyuzyap">
					<?php _e("<small><em>Examples: web, programming, free software</em></small>","wlops"); ?>
					</div>
					</div>
					<?php } ?>
				<?php $hide_cat = get_site_option( 'kuaza_wlops_hide_cat' ); if($hide_cat != "yes"){ ?>	
				<div class="kategoriklass">
				<?php _e("<strong>Category</strong>","wlops"); ?><br />
				<?php wp_dropdown_categories( $this->form_kategori_args ); ?>
					<div class="yuzdeyuzyap">
					<?php _e("<small><em>Please choose the most appropriate category.</em></small>","wlops"); ?>
					</div>
				</div>
				<?php } ?>
				</div>
		
			<div class="clearfixet alt_butonlar">
				<input id="wlops_submit" class="wlops_submit" type="submit" value="<?php _e("Submit","wlops"); ?>" />
			</div>
		</form> 
		
		<?php $hide_upload = get_site_option( 'kuaza_wlops_hide_upload' ); if($hide_upload != "yes"){ ?>
		<div class="resim_yukleme_alani_cevre">
			<h3><?php _e("Upload images for post","wlops"); ?></h3>

			<form id="wlops_form" action="<?php echo get_site_url()."/?kuaza_wlops_upload_progress=1"; ?>" method="post" enctype="multipart/form-data">
			<input type="hidden" name="wlops_konu_id" id="wlops_konu_id" value="<?php echo $this->konu_id; ?>" />
				<input class="hemenyukle_secmealani" type="file" name="wlops_file[]" multiple>
				<input class="hemenyukle_buton" type="submit" value="<?php _e("Upload now","wlops"); ?>">
			</form>
			
			<div class="progress">
				<div class="bar"></div>
				<div class="percent">0%</div>
			</div>
			
			<div id="status"></div>
		</div>
		<?php } ?>
	</div>
		<script>
		jQuery(document).ready(function($) {
		
		jQuery('.wlops_topcontent_step2').fadeIn("slow"); // form ustundeki alani gosterme
		jQuery('.wlops_bottomcontent_step2').fadeIn("slow"); // form altindaki alani gosterme
		
		<?php $hide_upload = get_site_option( 'kuaza_wlops_hide_upload' ); if($hide_upload =! "yes"){ ?>
		var bar = $('.bar');
		var percent = $('.percent');
		var status = $('#status');
		var progress = $('.progress');
			
		$('#wlops_form').ajaxForm({
			beforeSend: function() {
				status.empty();
				progress.show();
				var percentVal = '0%';
				bar.width(percentVal)
				percent.html(percentVal);
			},
			uploadProgress: function(event, position, total, percentComplete) {
				var percentVal = percentComplete + '%';
				bar.width(percentVal)
				percent.html(percentVal);
				//console.log(percentVal, position, total);
			},
			success: function() {
				var percentVal = '100%';
				bar.width(percentVal)
				percent.html(percentVal);
			},
			complete: function(xhr) {
			document.getElementById("wlops_form").reset(); // upload sonrasi formu temizleyelim, ayni resmi tekrar yuklemesinler :)
				status.html(xhr.responseText);
				progress.hide();
			}
		}); 
		<?php } ?>
		});       
		</script>
	<?php
	}
	
	
	/**
	 * Gelen bilgiler dogrultusunda wordpress'e icerik ekler
	 * @public
	 */
	private function wlops_icerik_ekle(){

	//$this->wlops_baslik = $_POST["wlops_baslik"];
	$this->wlops_content = $_POST["wlops_submit_text"];
	$this->wlops_tags = $_POST["wlops_submit_tags"];
	$this->wlops_kategori = $_POST["kategoriler"];
	$this->linkmi_acaba = (!empty($_POST["wlops_submit_link"]) ? true : false);
	$this->wlops_konu_id = $_POST["wlops_konu_id"];
	
	$eklenecek_icerik_bilgileri = array(
	  'ID' => $this->wlops_konu_id,
	  'post_title'    => !empty($_POST["wlops_baslik"]) ? wp_strip_all_tags($_POST["wlops_baslik"]) : get_the_title($this->wlops_konu_id),
	  'post_content'  => $this->wlops_content,
	  'post_status'   => $this->direk_yayinlansin_mi ? "publish" : "pending",
	  'post_author'   => $this->kullanici_ID,
	  'tags_input'   => $this->wlops_tags,
	  'post_category' => array($this->wlops_kategori) //array(8,39)
	);

	$this->post_id = wp_update_post( $eklenecek_icerik_bilgileri );

	add_post_meta( $this->post_id, '_wlops_views', "0", true );
	
	if($this->linkmi_acaba){
	
	if($this->kullanici_level == "administrator" || $this->kullanici_level == "editor"){ }else{
	wp_set_post_terms( $this->post_id, "post-format-link",'post_format' ); }
	
	add_post_meta( $this->post_id, '_wlops_link', $_POST["wlops_submit_link"], true );
	add_post_meta( $this->post_id, '_wlops', "link", true );
	}else{
	add_post_meta( $this->post_id, '_wlops', "post", true );
	
	}

	if(!$this->post_id)
	return false;

	return true;
	}
	
	/**
	* Trigger for progress..
	*/
	public function kuaza_wlops_upload_plugin_add_trigger($vars) {
		$vars[] = 'kuaza_wlops_upload_progress';
		$vars[] = 'wlops_out';
		return $vars;
	}
	 
	/**
	* Wlops icin ozel tema ve fonksiyon alani
	*/
	public function plugin_trigger_check() {
	
		/**
		* Wlops resim yukleme bolumu
		* Resimleri konu adina ekler
		*/
		if(intval(get_query_var('kuaza_wlops_upload_progress')) == 1) {

			$this->konu_id = (!empty($_POST["wlops_konu_id"]) && is_numeric($_POST["wlops_konu_id"])) ? $_POST["wlops_konu_id"] : null;
			
			if(!empty($_FILES)){

				for($xx=0;$xx<count($_FILES["wlops_file"]["name"]);$xx++)
				{

					// If the upload field has a file in it
					if($_FILES["wlops_file"]["size"][$xx] > 0) 
					{

						// Get the type of the uploaded file. This is returned as "type/extension"
						$arr_file_type = wp_check_filetype(basename($_FILES["wlops_file"]["name"][$xx]));
						$dosya_turu = $arr_file_type['type'];

						if(in_array($dosya_turu, $this->onayli_dosya_turleri))
						{

							$array_file["wlops_file"] = array(
							"name" => $this->yaziismicevir($_FILES["wlops_file"]["name"][$xx]),
							"type" => $_FILES["wlops_file"]["type"][$xx],
							"tmp_name" => $_FILES["wlops_file"]["tmp_name"][$xx],
							"size" => $_FILES["wlops_file"]["size"][$xx],
							"error" => $_FILES["wlops_file"]["error"][$xx]
							);

								if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );


							$yuklenecek_dosya_bilgileri = $array_file['wlops_file'];
							$upload_overrides = array( 'test_form' => false );
							$dosyayi_yukle = wp_handle_upload( $yuklenecek_dosya_bilgileri, $upload_overrides );
							
								if ( isset($dosyayi_yukle['file']) ) 
								{
									$dosya_turu_nedir = $dosyayi_yukle['type'];
									$dosya_ismi = $dosyayi_yukle['file'];

									$wp_upload_et_bakalim = wp_upload_dir();
									
										$attachment_bilgileri = array(
										'guid' => $wp_upload_et_bakalim['url'] . '/' . basename( $dosya_ismi ),
										'post_mime_type' => $dosya_turu_nedir,
										'post_title' => trim(strip_tags($_FILES["wlops_file"]["name"][$xx])),
										'post_content' => trim(strip_tags($_FILES["wlops_file"]["name"][$xx])),
										'post_status' => 'attachment'
										);
									
									$attach_id = wp_insert_attachment( $attachment_bilgileri, $dosya_ismi, $this->konu_id);
									
									require_once( ABSPATH . 'wp-admin/includes/image.php' );

									$attach_data = wp_generate_attachment_metadata( $attach_id, $dosya_ismi );
									wp_update_attachment_metadata( $attach_id, $attach_data );

								}		 

						}else{
						echo __("<div class='hataolustu'>This file type is not supported.</div>","wlops");
						}
					}
				}
				
				// konuya yuklenen resimleri listeleriz
				echo $this->wlops_yaziya_ait_resimleri_listele(true);		
									
			}else{
			
				echo __("<div class='hataolustu'>Please select the first file image or images :/</div>","wlops");
				
				// konuya yuklenen resimleri listeleriz
				echo $this->wlops_yaziya_ait_resimleri_listele(false);			
			} 	
				
			exit;
		}elseif(intval(get_query_var('wlops_out')) && is_numeric(get_query_var('wlops_out')) && get_query_var('wlops_out') != '') {

	// konuya eklenen ozel alani cekeriz, hani link olan varya :)
	$wlops_direk_link = get_post_meta( get_query_var('wlops_out'), '_wlops_link', true );
	
		// link mevcut ise out yapilacak meta guncelleriz sonrada o sayfaya yonlendirme yapariz.
		if($wlops_direk_link){
		
			// cikis sayisini guncelleme alani
			$wlops_direk_link_cikis_sayisi = get_post_meta( get_query_var('wlops_out'), '_wlops_views', true ); // cikis sayisini cekeriz
				$wlops_direk_link_cikis_sayisi_yeni = $wlops_direk_link_cikis_sayisi + 1; // cikis sayisina 1 ekleriz :)
					update_post_meta(get_query_var('wlops_out'), '_wlops_views', $wlops_direk_link_cikis_sayisi_yeni); // yeni cikis sayisini guncelleriz
			

			// yonlendirme alani 
			/*if (headers_sent()){
				die('<script type="text/javascript">setTimeout(function(){window.location.href="' . $wlops_direk_link . '";}, 0);</script>');
			}else{
				header('Refresh: 0; URL='.$wlops_direk_link);
			}*/
			
			header('HTTP/1.1 302 Moved Temporarily');
			header("Content-Type: text/html");
			header('Date: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
			header("Location: ".$wlops_direk_link);
	
		
		// eger link mevcut degilse ana sayfaya yonlendirme yaptiririz, ufakda bir uyari eklettiririz..
		}else{
		
			header("HTTP/1.0 404 Not Found");
			header("HTTP/1.1 404 Not Found");
			header("Status: 404 Not Found");
			header("Cache-Control: no-cache");
			header("Pragma: no-cache");
			header("Expires: -1");
		
			echo '<div class="yonlendirme_hata">'.sprintf(__("Not found post or this post for out link. <a href='%s'>Return home page 10 seconds after..</a>","wlops"),get_site_url()).'</div>';
			
			// yonlendirme alani 
			if (headers_sent()){
				die('<script type="text/javascript">setTimeout(function(){window.location.href="' . get_site_url() . '";}, 1000);</script>');
			}else{
				header('Refresh: 10; URL='.get_site_url());
			}

		}
		
		
		exit;
		}
	}
	
	/**
	 * Yaziya ait resimleri listeler..
	 * true olursa ilk resmi one cikan resim yapar, false ise bu isi ele almaz..
	 * @public
	 */	
	public function wlops_yaziya_ait_resimleri_listele($resim_one_cikan_yap = false){
	
	if(empty($this->konu_id)){
	global $post;
	
		if(!$post->ID)
		return false;
	
	$this->konu_id = $post->ID;
	}
	
	$konu_id_resimleri = get_attached_media( 'image',$this->konu_id );
	
	$wlops_resimler_nasil_gorunecek = get_post_meta( $this->konu_id, '_wlops_resimler_nasil_gorunecek', true );
	$default_resim_boyutu = get_site_option( 'wlops_default_resimler_nasil_gorunsun' );
	
	$wlops_resimler_nasil_gorunecek_yeni = !empty($wlops_resimler_nasil_gorunecek) ? $wlops_resimler_nasil_gorunecek : $default_resim_boyutu;
	
	if(!$resim_one_cikan_yap && count($konu_id_resimleri) < 1)
	return false;
	
	ob_start(); 
	
	if($konu_id_resimleri){
		echo '<div class="upload_resimler_cevre">';

			$i = "0"; foreach($konu_id_resimleri as $resim){
			$i++;
				// ilk yuklenen resmi one cikan resim olarak belirleriz (ilk fonksiyon parametresi true ise)
				if($resim_one_cikan_yap && $i == "1") set_post_thumbnail( $this->konu_id, $resim->ID);
				
				$resim_bilgileri = wp_get_attachment_image_src( $resim->ID,$wlops_resimler_nasil_gorunecek_yeni ); // secilen boyuta gore resim bilgilerini ceker..
				
			echo "<div class='upload_resimler_tek'><a class='wlops_resim_href_tek' title='".$resim->post_title."' href='".get_attachment_link( $resim->ID )."' ".(!is_single() ? "target='_blank'" : "")."><img class='wlops_resim_img_tek' width='".$resim_bilgileri[1]."' height='".$resim_bilgileri[2]."' alt='".$resim->post_title."' src='".$resim_bilgileri[0]."'></a></div>";

			}

		echo '</div>';
	}

	return ob_get_clean();
	}
	
	/**
	 * Yazi ismini degisik karakterlerden temizler
	 * @public
	 */		
	public function yaziismicevir($filename){
	
	$filename = remove_accents($filename);
		if (function_exists('mb_strtolower')) {
			$filename = mb_strtolower($filename, 'UTF-8');
		}
	$filename = utf8_uri_encode($filename);
	
	return $filename;
	}
	
	/**
	 * Kodlarin icerisinde title arar ve dondurur..
	 * @public
	 */
	public function get_meta_title(){
	  $pattern = "|<[\s]*title[\s]*>([^<]+)<[\s]*/[\s]*title[\s]*>|Ui";
	  if(preg_match($pattern, $this->url_content, $match))
		return trim($match[1]);
	  else
		return false;
	}
	
	/**
	 * html icerik icinden sayfa karakter kodunu alma..
	 * @public
	 */
	public function get_meta_karakter($url_link = false){
	
	if(!$url_link)
		$url_link = $this->wlops_submit_link;
	
		$this->url_header_bilgileri = get_headers($url_link, 1);

		if(!$this->url_header_bilgileri){
			
				// header bilgileri alinamiyorsa elle belirleriz
				$get_meta_karakter = "UTF-8";

		// eger array ise ayristirmayi farkli yollardan yapariz.
		}elseif(is_array($this->url_header_bilgileri["Content-Type"])){

			// 3. parametre: Content-Type => text/html; charset=iso-8859-1
			// preg_match ile karakter kodunu aliriz
			preg_match( '/charset=([^\'"]+)/i' , $this->url_header_bilgileri["Content-Type"][0], $match );
			$get_meta_karakter = array_pop($match);

			if(empty($get_meta_karakter)){
				preg_match( '/charset=([^\'"]+)/i' , $this->url_header_bilgileri["Content-Type"][1], $match );
				$get_meta_karakter = array_pop($match);
				
				if(empty($get_meta_karakter))
				$get_meta_karakter = "UTF-8";
				
			}
			
		}else{		
			
			// 3. parametre: Content-Type => text/html; charset=iso-8859-1
			// preg_match ile karakter kodunu aliriz
			preg_match( '/charset=([^\'"]+)/i' , $this->url_header_bilgileri["Content-Type"], $match );
			$get_meta_karakter = array_pop($match);
		}

		// hala bos ise yine default'u belirleriz
		if(empty($get_meta_karakter))
		$get_meta_karakter = "UTF-8";

		$this->get_meta_karakter = $get_meta_karakter;

	return true;
	}
	
	/**
	 * post ile gelen linkin gecerli olup olmadigini kontrol eder..
	 * @public
	 */	
	public function wlops_url_kontrol()
	{
		if(empty($_POST['wlops_submit_link']))
		return false;
		
			if (@fclose(@fopen( $_POST['wlops_submit_link'],  "r "))) {
			 return true;
			} else {
			 return false;
			}

	}
		 
	/**
	 * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook()
	 * @static
	 */
	public static function plugin_activation() {

	// yeni konu eklenirken resimler nasil gorunecek ayari: belirtilmezse thumbnail olur
	add_option( "wlops_default_resimler_nasil_gorunsun", "thumbnail" );	
	}

	/**
	 * Removes all connection options
	 * @static
	 */
	public static function plugin_deactivation( ) {
		//tidy up
	}
	/**
	 * Removes all connection options
	 * @static
	 */
	public static function plugin_delete( ) {

   delete_option( "wlops_default_resimler_gosterilsinmi" );
   delete_option( "kuaza_wlops_css" );
   delete_option( "wlops_konu_temasi" );
   delete_option( "wlops_default_resimler_nasil_gorunsun" );
   delete_option( "kuaza_wlops_loop_direk" );
   delete_option( "kuaza_wlops_guvenlik_public" );
   delete_option( "kuaza_wlops_guvenlik_private" );
   delete_option( "kuaza_wlops_guvenlik_tema" );

   delete_site_option( "wlops_default_resimler_gosterilsinmi" );
   delete_site_option( "kuaza_wlops_css" );
   delete_site_option( "wlops_konu_temasi" );
   delete_site_option( "wlops_default_resimler_nasil_gorunsun" );
   delete_site_option( "kuaza_wlops_loop_direk" );
   delete_site_option( "kuaza_wlops_guvenlik_public" );
   delete_site_option( "kuaza_wlops_guvenlik_private" );
   delete_site_option( "kuaza_wlops_guvenlik_tema" );


	}
}

	 $wlops = new WLOPS();
