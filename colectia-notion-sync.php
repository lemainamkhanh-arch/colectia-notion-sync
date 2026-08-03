<?php
/**
 * Plugin Name: COLECTIA Notion Sync
 * Plugin URI:  https://colectia.vn
 * Update URI:  https://github.com/lemainamkhanh-arch/colectia-notion-sync
 * Description: Đồng bộ sản phẩm từ Notion database "Furniture Design" sang WooCommerce. Tick "Đăng lên web" trong Notion — plugin tạo/cập nhật sản phẩm và ghi ngược WP Product ID + link về Notion.
 * Version:     1.41.4
 * Author:      COLECTIA
 * License:     GPLv2 or later
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Colectia_Notion_Sync {

	const NOTION_VERSION = '2022-06-28';
	const CRON_HOOK      = 'cns_cron_sync';
	const OPT_TOKEN      = 'cns_notion_token';
	const OPT_DB         = 'cns_notion_db';
	const OPT_AUTO       = 'cns_auto_sync';
	const OPT_LOG        = 'cns_last_log';
	const OPT_DB_MAT     = 'cns_db_material';
	const OPT_DB_MAT_COL = 'cns_db_material_collections';
	const OPT_DB_COL     = 'cns_db_collection';
	const MAT_CAT        = 'VẬT LIỆU';
	const BRAND_TAX      = 'product_brand';
	const EL_VERSION     = '3.32.4';
	const EL_WIDTH       = '800';
	const OPT_TABW       = 'cns_tab_width';
	const CPT_MAT        = 'colectia_material';
	const TAX_MAT_TYPE   = 'material_type';
	const TAX_MAT_COL    = 'material_collection';
	const OPT_CATALOG    = 'cns_catalog_mode';
	const DEFAULT_DB     = 'b2415ce27a5c4f08bdf2f9d5c0ca18e3'; // Furniture Design
	const MAX_IMAGES     = 6;
	const GH_OWNER       = 'lemainamkhanh-arch';
	const GH_REPO        = 'colectia-notion-sync';
	const OPT_GH_BRANCH  = 'cns_gh_branch';
	const OPT_GH_TOKEN   = 'cns_gh_token';
	const OPT_RW_VER     = 'cns_rw_ver';
	const TR_GH          = 'cns_gh_latest';
	const PLUGIN_VERSION = '1.41.4'; // Tăng số này để buộc đồng bộ lại toàn bộ, kể cả trang không đổi

	// Tên property trong Notion (phải khớp với database)
	const P_TITLE    = 'Name';
	const P_PUBLISH  = 'Đăng lên web';
	const P_WPID     = 'WP Product ID';
	const P_WPLINK   = 'WP Link';
	const P_SYNC     = 'Trạng thái sync';
	const P_CAT      = 'Category Furniture';
	const P_DESIGNER = 'Designer';
	const P_FILES    = 'Files & media';
	const P_MATERIAL = 'Vật liệu';
	const P_SYNCWEB  = 'Đồng bộ Web';
	const S_EDIT     = 'Chỉnh sửa';
	const S_DONE     = 'Cập nhật';
	const P_SHORT    = 'COLECTIA';
	const P_DESC     = 'Product Fact Sheet';
	const P_3D       = '3D Model';
	const P_PRICE    = 'Giá bán';
	const P_SKU      = 'SKU / Mã sản phẩm';
	const P_STOCK    = 'Tình trạng kho';
	const P_WEIGHT   = 'Trọng lượng (kg)';
	const P_LENGTH   = 'Dài (cm)';
	const P_WIDTH    = 'Rộng (cm)';
	const P_HEIGHT   = 'Cao (cm)';

	private $log = array();
	private $title_cache = array();

	public static function init() {
		static $inst = null;
		if ( null === $inst ) { $inst = new self(); }
		return $inst;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_post_cns_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_cns_sync_now', array( $this, 'handle_sync_now' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( self::CRON_HOOK, array( $this, 'cron_sync' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_styles' ), 20 );
		add_filter( 'woocommerce_product_tabs', array( $this, 'material_tab' ), 98 );
		add_action( 'add_meta_boxes_product', array( $this, 'add_product_material_box' ) );
		add_action( 'save_post_product', array( $this, 'save_product_material_box' ), 20, 3 );
		add_action( 'wp_footer', array( $this, 'material_tab_button_script' ), 99 );
		add_action( 'init', array( $this, 'register_material_cpt' ) );
		add_shortcode( 'colectia_materials', array( $this, 'materials_shortcode' ) );
		add_action( 'admin_post_cns_migrate_materials', array( $this, 'handle_migrate_materials' ) );
		if ( '1' === get_option( self::OPT_CATALOG, '1' ) ) { $this->catalog_mode_hooks(); }
		add_filter( 'template_include', array( $this, 'material_template' ), 99 );
		add_filter( 'elementor/query/cns_materials', array( $this, 'el_query_materials' ) );
		add_filter( 'elementor/query/cns_materials_all', array( $this, 'el_query_materials' ) );
		add_filter( 'option_elementor_cpt_support', array( $this, 'el_cpt_support' ) );
		add_filter( 'default_option_elementor_cpt_support', array( $this, 'el_cpt_support' ) );
		add_filter( 'pre_get_posts', array( $this, 'material_archive_query' ) );
		add_filter( 'update_plugins_github.com', array( $this, 'gh_update_check' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'gh_fix_dir' ), 10, 4 );
		add_filter( 'plugins_api', array( $this, 'gh_plugin_info' ), 10, 3 );
		add_action( 'admin_post_cns_gh_check', array( $this, 'handle_gh_check' ) );
		add_action( 'upgrader_process_complete', array( $this, 'gh_after_update' ), 10, 2 );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 999 );
	}

	/* ---------------- Cron ---------------- */

	public function cron_schedules( $s ) {
		$s['cns_15min'] = array( 'interval' => 900, 'display' => 'Mỗi 15 phút (Notion Sync)' );
		return $s;
	}

	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'cns_15min', self::CRON_HOOK );
		}
		$i = new self();
		$i->register_material_cpt();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function cron_sync() {
		if ( '1' !== get_option( self::OPT_AUTO, '1' ) ) { return; }
		$this->run_sync();
	}

	/* ---------------- Admin UI ---------------- */

	public function admin_menu() {
		add_options_page( 'Notion Sync', 'Notion Sync', 'manage_options', 'cns', array( $this, 'render_page' ) );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$token = get_option( self::OPT_TOKEN, '' );
		$db    = get_option( self::OPT_DB, self::DEFAULT_DB );
		$auto  = get_option( self::OPT_AUTO, '1' );
		$catalog = get_option( self::OPT_CATALOG, '1' );
		$log   = get_option( self::OPT_LOG, array() );
		?>
		<div class="wrap">
			<h1>COLECTIA Notion Sync</h1>
			<?php $this->gh_admin_card(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cns_save' ); ?>
				<input type="hidden" name="action" value="cns_save" />
				<table class="form-table">
					<tr>
						<th><label for="cns_token">Notion Integration Token</label></th>
						<td><input type="password" id="cns_token" name="cns_token" class="regular-text" value="<?php echo esc_attr( $token ); ?>" placeholder="ntn_..." autocomplete="off" />
						<p class="description">Tạo tại notion.so/profile/integrations, sau đó share database Furniture Design với integration này.</p></td>
					</tr>
					<tr>
						<th><label for="cns_db">Notion Database ID</label></th>
						<td><input type="text" id="cns_db" name="cns_db" class="regular-text" value="<?php echo esc_attr( $db ); ?>" />
						<p class="description">Mặc định đã trỏ sẵn vào database Furniture Design.</p></td>
					</tr>
					<tr>
						<th>Tự động đồng bộ</th>
						<td><label><input type="checkbox" name="cns_auto" value="1" <?php checked( $auto, '1' ); ?> /> Chạy mỗi 15 phút (WP-Cron)</label></td>
					</tr>
					<tr>
						<th>Chế độ catalog</th>
						<td><label><input type="checkbox" name="cns_catalog" value="1" <?php checked( $catalog, '1' ); ?> /> Ẩn giá, ẩn nút mua hàng và bỏ script giỏ hàng</label>
						<p class="description">Website chỉ trưng bày sản phẩm, không bán trực tuyến. Cột "Giá bán" trong Notion sẽ không được đồng bộ.</p></td>
					</tr>
				</table>
				<?php submit_button( 'Lưu cài đặt' ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
				<?php wp_nonce_field( 'cns_sync_now' ); ?>
				<input type="hidden" name="action" value="cns_sync_now" />
				<?php submit_button( 'Đồng bộ ngay', 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;" onsubmit="return confirm('Chuyển toàn bộ sản phẩm trong danh mục VẬT LIỆU sang mục Vật liệu? Sản phẩm cũ sẽ chuyển về nháp.');">
				<?php wp_nonce_field( 'cns_migrate_materials' ); ?>
				<input type="hidden" name="action" value="cns_migrate_materials" />
				<?php submit_button( 'Chuyển sản phẩm vật liệu sang mục Vật liệu', 'secondary', 'submit', false ); ?>
			</form>
			<p class="description" style="margin-top:10px;">Chèn lưới vật liệu vào bất kỳ trang hoặc HTML block nào bằng shortcode:<br />
			<code>[colectia_materials]</code> — tất cả vật liệu<br />
			<code>[colectia_materials type="NUBUCK" columns="4"]</code> — lọc theo nhóm<br />
			<code>[colectia_materials group="yes"]</code> — gộp theo nhóm vật liệu<br />
			<code>[colectia_materials collection="Nabuck 2026"]</code> — lọc theo bộ sưu tập<br />
			Bộ lọc thư viện: <code>/thu-vien-vat-lieu/?loai=…&amp;bst=…</code></p>
			<h2 style="margin-top:24px;">Log lần đồng bộ gần nhất</h2>
			<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:400px;overflow:auto;"><?php echo esc_html( implode( "\n", (array) $log ) ); ?></pre>
		</div>
		<?php
	}

	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Không có quyền.' ); }
		check_admin_referer( 'cns_save' );
		if ( isset( $_POST['cns_token'] ) ) {
			update_option( self::OPT_TOKEN, sanitize_text_field( wp_unslash( $_POST['cns_token'] ) ), false );
		}
		if ( isset( $_POST['cns_db'] ) ) {
			$db = strtolower( preg_replace( '/[^a-fA-F0-9]/', '', wp_unslash( $_POST['cns_db'] ) ) );
			update_option( self::OPT_DB, $db ? $db : self::DEFAULT_DB, false );
		}
		update_option( self::OPT_AUTO, empty( $_POST['cns_auto'] ) ? '0' : '1', false );
		update_option( self::OPT_CATALOG, empty( $_POST['cns_catalog'] ) ? '0' : '1', false );
		wp_safe_redirect( admin_url( 'options-general.php?page=cns&saved=1' ) );
		exit;
	}

	public function handle_sync_now() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Không có quyền.' ); }
		check_admin_referer( 'cns_sync_now' );
		$this->run_sync();
		wp_safe_redirect( admin_url( 'options-general.php?page=cns&synced=1' ) );
		exit;
	}

	/* ---------------- Notion API ---------------- */

	private function notion_request( $method, $path, $body = null ) {
		$token = get_option( self::OPT_TOKEN, '' );
		if ( '' === $token ) {
			return new WP_Error( 'cns_no_token', 'Chưa cấu hình Notion Integration Token.' );
		}
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization'  => 'Bearer ' . $token,
				'Notion-Version' => self::NOTION_VERSION,
				'Content-Type'   => 'application/json',
			),
		);
		if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); }
		$res = wp_remote_request( 'https://api.notion.com/v1' . $path, $args );
		if ( is_wp_error( $res ) ) { return $res; }
		$code = wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code >= 300 ) {
			$msg = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : 'unknown';
			return new WP_Error( 'cns_api', 'Notion API ' . $code . ': ' . $msg );
		}
		return $data;
	}

	private function query_pages() {
		$db     = get_option( self::OPT_DB, self::DEFAULT_DB );
		$pages  = array();
		$cursor = null;
		do {
			$body = array(
				'page_size' => 100,
				'filter'    => array(
					'or' => array(
						array( 'property' => self::P_PUBLISH, 'checkbox' => array( 'equals' => true ) ),
						array( 'property' => self::P_WPID, 'number' => array( 'is_not_empty' => true ) ),
						array( 'property' => self::P_SYNCWEB, 'status' => array( 'equals' => self::S_EDIT ) ),
					),
				),
			);
			if ( $cursor ) { $body['start_cursor'] = $cursor; }
			$res = $this->notion_request( 'POST', '/databases/' . $db . '/query', $body );
			if ( is_wp_error( $res ) ) { return $res; }
			$pages  = array_merge( $pages, isset( $res['results'] ) ? $res['results'] : array() );
			$cursor = ! empty( $res['has_more'] ) ? $res['next_cursor'] : null;
		} while ( $cursor );
		return $pages;
	}

	private function plain_text( $rich ) {
		$out = '';
		foreach ( (array) $rich as $r ) {
			if ( isset( $r['plain_text'] ) ) { $out .= $r['plain_text']; }
		}
		return trim( $out );
	}

	private function prop( $props, $name ) {
		return isset( $props[ $name ] ) ? $props[ $name ] : array();
	}

	private function relation_titles( $prop, $prefer_prop = '' ) {
		$out  = array();
		$rels = isset( $prop['relation'] ) ? (array) $prop['relation'] : array();
		foreach ( $rels as $rel ) {
			if ( empty( $rel['id'] ) ) { continue; }
			$id = $rel['id'] . '|' . $prefer_prop;
			if ( ! isset( $this->title_cache[ $id ] ) ) {
				$key = 'cns_title_' . md5( $id );
				$t   = get_transient( $key );
				if ( false === $t ) {
					$t = '';
					$p = $this->notion_request( 'GET', '/pages/' . $rel['id'], null );
					if ( ! is_wp_error( $p ) && isset( $p['properties'] ) ) {
						if ( '' !== $prefer_prop && isset( $p['properties'][ $prefer_prop ]['rich_text'] ) ) {
							$t = $this->plain_text( $p['properties'][ $prefer_prop ]['rich_text'] );
						}
						if ( '' === $t ) {
							foreach ( $p['properties'] as $pp ) {
								if ( isset( $pp['type'] ) && 'title' === $pp['type'] ) {
									$t = $this->plain_text( $pp['title'] );
									break;
								}
							}
						}
					}
					set_transient( $key, $t, 10 * MINUTE_IN_SECONDS );
				}
				$this->title_cache[ $id ] = $t;
			}
			if ( '' !== $this->title_cache[ $id ] ) { $out[] = $this->title_cache[ $id ]; }
		}
		return $out;
	}

	/* ---------------- Nội dung trang (blocks) → HTML ---------------- */

	private function fetch_block_children( $block_id ) {
		$blocks = array();
		$cursor = null;
		do {
			$qs  = 'page_size=100' . ( $cursor ? '&start_cursor=' . rawurlencode( $cursor ) : '' );
			$res = $this->notion_request( 'GET', '/blocks/' . $block_id . '/children?' . $qs, null );
			if ( is_wp_error( $res ) ) { break; }
			$blocks = array_merge( $blocks, isset( $res['results'] ) ? $res['results'] : array() );
			$cursor = ! empty( $res['has_more'] ) ? $res['next_cursor'] : null;
		} while ( $cursor );
		return $blocks;
	}

	private function rich_text_html( $rich ) {
		$html = '';
		foreach ( (array) $rich as $r ) {
			$text = isset( $r['plain_text'] ) ? $r['plain_text'] : '';
			$text = esc_html( $text );
			$ann  = isset( $r['annotations'] ) ? $r['annotations'] : array();
			if ( ! empty( $ann['code'] ) ) { $text = '<code>' . $text . '</code>'; }
			if ( ! empty( $ann['bold'] ) ) { $text = '<strong>' . $text . '</strong>'; }
			if ( ! empty( $ann['italic'] ) ) { $text = '<em>' . $text . '</em>'; }
			if ( ! empty( $ann['strikethrough'] ) ) { $text = '<s>' . $text . '</s>'; }
			if ( ! empty( $ann['underline'] ) ) { $text = '<u>' . $text . '</u>'; }
			$href = isset( $r['href'] ) ? $r['href'] : null;
			if ( $href ) { $text = '<a href="' . esc_url( $href ) . '">' . $text . '</a>'; }
			$html .= $text;
		}
		return $html;
	}

	private function table_to_html( $table_id ) {
		$rows = $this->fetch_block_children( $table_id );
		if ( ! $rows ) { return ''; }
		$html = '<table>';
		foreach ( $rows as $r ) {
			if ( 'table_row' !== $r['type'] ) { continue; }
			$html .= '<tr>';
			foreach ( (array) $r['table_row']['cells'] as $cell ) {
				$html .= '<td>' . $this->rich_text_html( $cell ) . '</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</table>';
		return $html;
	}

	private function blocks_to_html( $blocks, $depth = 0 ) {
		if ( $depth > 4 || ! $blocks ) { return ''; }
		$html      = '';
		$list_open = null;
		foreach ( $blocks as $b ) {
			$type = isset( $b['type'] ) ? $b['type'] : '';
			if ( $list_open && 'bulleted_list_item' !== $type && 'ul' === $list_open ) { $html .= '</ul>'; $list_open = null; }
			if ( $list_open && 'numbered_list_item' !== $type && 'ol' === $list_open ) { $html .= '</ol>'; $list_open = null; }
			$has_kids = ! empty( $b['has_children'] );
			switch ( $type ) {
				case 'paragraph':
					$t = $this->rich_text_html( $b['paragraph']['rich_text'] );
					if ( '' !== trim( wp_strip_all_tags( $t ) ) ) { $html .= '<p>' . $t . '</p>'; }
					break;
				case 'heading_1':
					$html .= '<h2>' . $this->rich_text_html( $b['heading_1']['rich_text'] ) . '</h2>';
					break;
				case 'heading_2':
					$html .= '<h3>' . $this->rich_text_html( $b['heading_2']['rich_text'] ) . '</h3>';
					break;
				case 'heading_3':
					$html .= '<h4>' . $this->rich_text_html( $b['heading_3']['rich_text'] ) . '</h4>';
					break;
				case 'bulleted_list_item':
					if ( 'ul' !== $list_open ) { $html .= '<ul>'; $list_open = 'ul'; }
					$html .= '<li>' . $this->rich_text_html( $b['bulleted_list_item']['rich_text'] );
					if ( $has_kids ) { $html .= $this->blocks_to_html( $this->fetch_block_children( $b['id'] ), $depth + 1 ); }
					$html .= '</li>';
					break;
				case 'numbered_list_item':
					if ( 'ol' !== $list_open ) { $html .= '<ol>'; $list_open = 'ol'; }
					$html .= '<li>' . $this->rich_text_html( $b['numbered_list_item']['rich_text'] );
					if ( $has_kids ) { $html .= $this->blocks_to_html( $this->fetch_block_children( $b['id'] ), $depth + 1 ); }
					$html .= '</li>';
					break;
				case 'to_do':
					$checked = ! empty( $b['to_do']['checked'] );
					$html   .= '<p>' . ( $checked ? '☑' : '☐' ) . ' ' . $this->rich_text_html( $b['to_do']['rich_text'] ) . '</p>';
					break;
				case 'quote':
					$html .= '<blockquote>' . $this->rich_text_html( $b['quote']['rich_text'] ) . '</blockquote>';
					break;
				case 'callout':
					$icon  = isset( $b['callout']['icon']['emoji'] ) ? $b['callout']['icon']['emoji'] . ' ' : '';
					$html .= '<blockquote>' . $icon . $this->rich_text_html( $b['callout']['rich_text'] ) . '</blockquote>';
					break;
				case 'divider':
					$html .= '<hr />';
					break;
				case 'image':
					$url = isset( $b['image']['file']['url'] ) ? $b['image']['file']['url'] : ( isset( $b['image']['external']['url'] ) ? $b['image']['external']['url'] : '' );
					if ( $url ) {
						$cap   = isset( $b['image']['caption'] ) ? $this->rich_text_html( $b['image']['caption'] ) : '';
						$html .= '<figure><img src="' . esc_url( $url ) . '" alt="" loading="lazy" />' . ( $cap ? '<figcaption>' . $cap . '</figcaption>' : '' ) . '</figure>';
					}
					break;
				case 'toggle':
					$html .= '<details><summary>' . $this->rich_text_html( $b['toggle']['rich_text'] ) . '</summary>';
					if ( $has_kids ) { $html .= $this->blocks_to_html( $this->fetch_block_children( $b['id'] ), $depth + 1 ); }
					$html .= '</details>';
					break;
				case 'column_list':
				case 'column':
				case 'synced_block':
					if ( $has_kids ) { $html .= $this->blocks_to_html( $this->fetch_block_children( $b['id'] ), $depth + 1 ); }
					break;
				case 'table':
					$html .= $this->table_to_html( $b['id'] );
					break;
				default:
					// Bỏ qua các loại block chưa hỗ trợ (embed, video, file, bảng biểu phức tạp, v.v.)
					break;
			}
		}
		if ( 'ul' === $list_open ) { $html .= '</ul>'; }
		if ( 'ol' === $list_open ) { $html .= '</ol>'; }
		return $html;
	}

	private function get_page_content_html( $page_id ) {
		$blocks = $this->fetch_block_children( $page_id );
		$html   = $this->blocks_to_html( $blocks );
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) { return ''; }
		// Boc trong wrapper de CSS chuan hoa be rong noi dung (khong tran full man hinh).
		return '<div class="cns-notion-content">' . $html . '</div>';
	}

	/* ---------------- CSS hien thi noi dung san pham ---------------- */

	public function frontend_styles() {
		$is_prod = function_exists( 'is_product' ) && is_product();
		if ( ! $is_prod && ! is_singular( self::CPT_MAT ) && ! is_post_type_archive( self::CPT_MAT ) && ! is_tax( self::TAX_MAT_TYPE ) && ! is_tax( self::TAX_MAT_COL ) && ! is_page() ) { return; }
		$tabw = intval( get_option( self::OPT_TABW, self::EL_WIDTH ) );
		if ( $tabw < 400 ) { $tabw = intval( self::EL_WIDTH ); }
		$tabw = apply_filters( 'cns_tab_content_width', $tabw );
		$css = '
.cns-notion-content{max-width:820px;margin:0 auto;font-size:16px;line-height:1.75;}
.cns-notion-content>*:first-child{margin-top:0;}
.cns-notion-content>*:last-child{margin-bottom:0;}
.cns-notion-content p{margin:0 0 1.1em;}
.cns-notion-content h2{font-size:26px;line-height:1.3;margin:2em 0 .6em;font-weight:600;}
.cns-notion-content h3{font-size:21px;line-height:1.35;margin:1.8em 0 .5em;font-weight:600;}
.cns-notion-content h4{font-size:17px;line-height:1.4;margin:1.6em 0 .5em;font-weight:600;text-transform:uppercase;letter-spacing:.04em;}
.cns-notion-content ul,.cns-notion-content ol{margin:0 0 1.1em;padding-left:1.4em;}
.cns-notion-content li{margin-bottom:.4em;}
.cns-notion-content figure{margin:1.8em 0;text-align:center;}
.cns-notion-content img{display:block;width:100%;height:auto;margin:0 auto;border-radius:4px;}
.cns-notion-content figcaption{margin-top:.7em;font-size:13px;color:#888;font-style:italic;}
.cns-notion-content blockquote{margin:1.6em 0;padding:.2em 0 .2em 1.2em;border-left:3px solid #ddd;color:#555;font-style:italic;}
.cns-notion-content hr{margin:2.2em 0;border:0;border-top:1px solid #e6e6e6;}
.cns-notion-content a{text-decoration:underline;}
.cns-notion-content table{width:100%;border-collapse:collapse;margin:1.6em 0;font-size:15px;}
.cns-notion-content table td,.cns-notion-content table th{border:1px solid #e6e6e6;padding:10px 12px;vertical-align:top;}
.cns-notion-content table tr:first-child td{background:#fafafa;font-weight:600;}
.cns-notion-content details{margin:1.2em 0;padding:.9em 1.1em;background:#fafafa;border-radius:4px;}
.cns-notion-content details summary{cursor:pointer;font-weight:600;}
@media(max-width:768px){.cns-notion-content{font-size:15px;}.cns-notion-content h2{font-size:22px;}.cns-notion-content h3{font-size:19px;}}
.cns-materials{max-width:1100px;margin:0 auto;}
.cns-mat-group{margin-bottom:2.4em;}
.cns-mat-group-title{font-size:13px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#999;margin:0 0 1em;padding-bottom:.6em;border-bottom:1px solid #ececec;}
.cns-mat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:22px;}
.cns-mat-item{text-align:left;}
.cns-mat-thumb{position:relative;display:block;width:100%;aspect-ratio:1/1;overflow:hidden;border-radius:6px;background:#f4f4f4;}
.cns-mat-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .45s ease;}
.cns-mat-item:hover .cns-mat-thumb img{transform:scale(1.05);}
.cns-mat-swatch{width:100%;height:100%;display:block;}
.cns-mat-name{margin:.7em 0 .15em;font-size:14px;font-weight:600;line-height:1.35;}
.cns-mat-code{font-size:12px;color:#999;letter-spacing:.05em;}
.cns-mat-empty{color:#999;font-style:italic;}
.cns-materials-wd{max-width:none;}
.cns-materials-wd .cns-mat-group{margin-bottom:0;}
.cns-materials-wd .cns-mat-group+.cns-mat-group{margin-top:52px;}
.cns-mat-card .product-image-link{display:block;overflow:hidden;background:#f4f4f4;}
.cns-mat-card .product-image-link img{display:block;width:100%;height:auto;aspect-ratio:1/1;object-fit:cover;transition:transform .5s ease;}
.cns-mat-card:hover .product-image-link img{transform:scale(1.04);}
.cns-mat-card .cns-mat-swatch{display:block;width:100%;aspect-ratio:1/1;}
.cns-mat-card .product-element-bottom{padding-top:12px;}
.cns-mat-card .wd-entities-title{margin-bottom:2px;}
.cns-materials-wd .wd-entities-title{font-size:14px!important;line-height:1.45;margin-top:10px!important;font-weight:500!important;}
.cns-mat-collection{margin-bottom:54px;}.cns-mat-collection-head{margin-bottom:28px;padding-bottom:20px;}.cns-materials-wd .wd-products{row-gap:30px!important;}.cns-materials-wd .product-wrapper{padding-bottom:4px;}
.cns-mat-filters{margin:14px 0 30px;display:flex;flex-direction:column;gap:10px;}
.cns-mat-filter-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;}
.cns-mat-filter-label{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:#999;margin-right:6px;}
.cns-chip{display:inline-block;padding:6px 16px;border:1px solid #ddd;border-radius:999px;font-size:12px;color:#333;text-decoration:none;line-height:1.6;transition:border-color .2s,background .2s,color .2s;}
.cns-chip:hover{border-color:#000;color:#000;}
.cns-chip.is-active{background:#000;border-color:#000;color:#fff;}
.cns-mat-collection{margin:0 0 66px;}
.cns-mat-collection-head{display:flex;align-items:center;gap:18px;margin:0 0 22px;padding:0 0 16px;border-bottom:1px solid #e9e9e9;color:#222;text-decoration:none;}
.cns-mat-collection-head img{width:72px;height:72px;object-fit:cover;border-radius:3px;background:#f4f4f4;}
.cns-mat-collection-head span>span,.cns-mat-collection-kicker{display:block;font-size:10px;letter-spacing:.16em;color:#999;margin:0 0 5px;}
.cns-mat-collection-head strong{display:block;font-size:20px;font-weight:500;line-height:1.2;}
.cns-mat-collection-head em{display:block;margin-top:6px;color:#777;font-size:13px;line-height:1.5;font-style:normal;}
@media(max-width:600px){.cns-mat-collection{margin-bottom:46px;}.cns-mat-collection-head strong{font-size:17px;}}
.cns-mat-card .cns-mat-code{font-size:12px;color:#999;letter-spacing:.05em;margin-top:2px;}
.single-product .cns-materials{padding:8px 0 12px;}.single-product .cns-mat-group{margin-bottom:40px;}.single-product .cns-mat-grid{grid-template-columns:repeat(auto-fill,minmax(128px,1fr));gap:18px;}.single-product .cns-mat-name{font-size:12px;letter-spacing:.02em;text-transform:uppercase;}.single-product .cns-mat-thumb{border-radius:2px;}
.cns-mat-wrap{max-width:1200px;margin:0 auto;padding:40px 20px 70px;}
.cns-mat-crumb{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#999;margin-bottom:26px;}
.cns-mat-crumb a{color:#999;text-decoration:none;}
.cns-mat-crumb a:hover{color:#222;}
.cns-mat-hero{display:grid;grid-template-columns:1.05fr 1fr;gap:52px;align-items:start;}
.cns-mat-hero-img{display:block;width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:6px;background:#f4f4f4;}
.cns-mat-eyebrow{font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#999;margin-bottom:12px;}
.cns-mat-title{font-size:32px;font-weight:600;line-height:1.2;margin:0 0 18px;}
.cns-mat-lead{font-size:15px;line-height:1.75;color:#555;}
.cns-mat-specs{width:100%;border-collapse:collapse;margin:6px 0 24px;font-size:14px;}
.cns-mat-specs th{text-align:left;width:42%;font-weight:400;color:#999;padding:10px 0;border-bottom:1px solid #eee;}
.cns-mat-specs td{padding:10px 0;border-bottom:1px solid #eee;color:#222;}
.cns-mat-dot{display:inline-block;width:12px;height:12px;border-radius:50%;margin-right:8px;vertical-align:-1px;border:1px solid rgba(0,0,0,.1);}
.cns-mat-sec{margin-top:66px;}
.cns-mat-sec-title{font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#222;margin-bottom:24px;padding-bottom:12px;border-bottom:1px solid #eee;}
.cns-mat-archive-head{text-align:center;max-width:720px;margin:0 auto 48px;}
.cns-mat-group-title{font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#222;margin:44px 0 20px;}
.cns-mat-group:first-child .cns-mat-group-title{margin-top:0;}
@media(max-width:900px){.cns-mat-hero{grid-template-columns:1fr;gap:28px;}.cns-mat-title{font-size:26px;}.cns-mat-sec{margin-top:44px;}}
@media(max-width:600px){.cns-mat-grid{grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:16px;}}
/* Giới hạn nội dung tất cả tab về khối __TABW__px canh giữa (nav tab vẫn full width) */
.single-product .woocommerce-Tabs-panel>.wc-tab-inner,
.single-product .wd-accordion-content>.wc-tab-inner,
.single-product .wc-tabs-wrapper .wc-tab-inner{max-width:__TABW__px;margin-left:auto;margin-right:auto;}
.single-product .woocommerce-Tabs-panel>*:not(.wc-tab-inner){max-width:__TABW__px;margin-left:auto;margin-right:auto;}
.single-product .wc-tab-inner>.elementor,
.single-product .wc-tab-inner .elementor-section-boxed>.elementor-container{max-width:__TABW__px;margin-left:auto;margin-right:auto;}
.single-product .wc-tab-inner .e-con.e-con-boxed>.e-con-inner{max-width:__TABW__px;}
.single-product .wc-tab-inner img{max-width:100%;height:auto;}
.single-product .wc-tab-inner .cns-notion-content,
.single-product .wc-tab-inner .cns-materials{max-width:__TABW__px;}
.single-product .wc-tab-inner .cns-full,
.single-product .wc-tab-inner .wd-full-width{max-width:none;}
@media(max-width:__TABPAD__px){.single-product .woocommerce-Tabs-panel>.wc-tab-inner,.single-product .wd-accordion-content>.wc-tab-inner{padding-left:15px;padding-right:15px;}}
';
		$css = str_replace( array( '__TABW__', '__TABPAD__' ), array( $tabw, $tabw + 60 ), $css );
		wp_register_style( 'cns-content', false, array(), self::PLUGIN_VERSION );
		wp_enqueue_style( 'cns-content' );
		wp_add_inline_style( 'cns-content', $css );
	}

	/* ---------------- Sync ---------------- */

	public function run_sync() {
		$this->log   = array();
		$this->log[] = 'Bắt đầu đồng bộ: ' . date_i18n( 'Y-m-d H:i:s' );
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->log[] = 'LỖI: WooCommerce chưa kích hoạt.';
			$this->save_log();
			return;
		}
		$pages = $this->query_pages();
		if ( is_wp_error( $pages ) ) {
			$this->log[] = 'LỖI: ' . $pages->get_error_message();
			$this->save_log();
			return;
		}
		$this->log[] = 'Tìm thấy ' . count( $pages ) . ' dòng cần xử lý.';
		foreach ( $pages as $page ) {
			try {
				$this->sync_page( $page );
			} catch ( Exception $e ) {
				$this->log[] = 'LỖI trang ' . $page['id'] . ': ' . $e->getMessage();
			}
		}
		$this->sync_collection_db();
		$this->sync_material_collections_db();
		$this->sync_material_db();
		$this->log[] = 'Xong: ' . date_i18n( 'Y-m-d H:i:s' );
		$this->save_log();
	}

	private function sync_page( $page ) {
		$pid   = $page['id'];
		$props = $page['properties'];

		$title_prop = $this->prop( $props, self::P_TITLE );
		$name       = isset( $title_prop['title'] ) ? $this->plain_text( $title_prop['title'] ) : '';
		if ( '' === $name ) { return; }

		$publish_prop = $this->prop( $props, self::P_PUBLISH );
		$publish      = ! empty( $publish_prop['checkbox'] );
		$wpid_prop    = $this->prop( $props, self::P_WPID );
		$wpid         = isset( $wpid_prop['number'] ) ? intval( $wpid_prop['number'] ) : 0;
		$last_edited  = isset( $page['last_edited_time'] ) ? $page['last_edited_time'] : '';
		$sw_prop      = $this->prop( $props, self::P_SYNCWEB );
		$sw_now       = isset( $sw_prop['status']['name'] ) ? $sw_prop['status']['name'] : '';
		$force        = ( self::S_EDIT === $sw_now );

		// Tìm sản phẩm theo notion_page_id, sau đó theo WP Product ID.
		$product  = null;
		$existing = get_posts( array(
			'post_type'   => 'product',
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_notion_page_id',
			'meta_value'  => $pid,
		) );
		if ( $existing ) { $product = wc_get_product( $existing[0] ); }
		if ( ! $product && $wpid ) { $product = wc_get_product( $wpid ); }
		$is_new = false;
		if ( ! $product ) {
			$product = new WC_Product_Simple();
			$is_new  = true;
		}

		// Bỏ qua nếu không thay đổi từ lần sync trước (trừ khi plugin vừa được nâng cấp lên bản mới hơn — khi đó đồng bộ lại để lấy các trường/nội dung mới thêm vào).
		$synced_ver = $product->get_meta( '_notion_sync_ver' );
		if ( ! $force && ! $is_new && $last_edited && $product->get_meta( '_notion_last_edited' ) === $last_edited && $synced_ver === self::PLUGIN_VERSION ) {
			return;
		}

		$product->set_name( $name );
		$product->set_status( $publish ? 'publish' : 'draft' );

		$short_prop = $this->prop( $props, self::P_SHORT );
		$desc_prop  = $this->prop( $props, self::P_DESC );
		$short      = isset( $short_prop['rich_text'] ) ? $this->plain_text( $short_prop['rich_text'] ) : '';
		$desc_text  = isset( $desc_prop['rich_text'] ) ? $this->plain_text( $desc_prop['rich_text'] ) : '';
		if ( '' !== $short ) { $product->set_short_description( $short ); }

		// Nội dung chi tiết (mô tả dài) lấy từ CHÍNH NỘI DUNG BÊN TRONG trang sản phẩm Notion (page body / blocks).
		// Nếu trang rỗng thì fallback về property "Product Fact Sheet".
		$content_html = $this->get_page_content_html( $pid );
		if ( '' !== trim( wp_strip_all_tags( $content_html ) ) ) {
			$product->set_description( $content_html );
		} elseif ( '' !== $desc_text ) {
			$product->set_description( wpautop( esc_html( $desc_text ) ) );
		}

		// Designer thành attribute hiển thị (theo yêu cầu: KHÔNG đồng bộ Brand).
		$designer = $this->relation_titles( $this->prop( $props, self::P_DESIGNER ) );
		$attrs    = array();
		if ( $designer ) { $attrs['Designer'] = implode( ', ', $designer ); }
		if ( $attrs ) {
			$attributes = array();
			$pos        = 0;
			foreach ( $attrs as $label => $value ) {
				$a = new WC_Product_Attribute();
				$a->set_name( $label );
				$a->set_options( array( $value ) );
				$a->set_position( $pos++ );
				$a->set_visible( true );
				$a->set_variation( false );
				$attributes[] = $a;
			}
			$product->set_attributes( $attributes );
		}

		// Giá, SKU, tồn kho, trọng lượng, kích thước.
		// 1.8.0: không đồng bộ giá nữa — website chạy chế độ catalog (chỉ trưng bày).
		$product->set_regular_price( '' );
		$product->set_sale_price( '' );
		$product->set_price( '' );
		$sku_prop = $this->prop( $props, self::P_SKU );
		$sku      = isset( $sku_prop['rich_text'] ) ? $this->plain_text( $sku_prop['rich_text'] ) : '';
		if ( '' !== $sku ) { $product->set_sku( $sku ); }
		$stock_prop = $this->prop( $props, self::P_STOCK );
		$stock_name = isset( $stock_prop['select']['name'] ) ? $stock_prop['select']['name'] : '';
		if ( 'Còn hàng' === $stock_name ) { $product->set_stock_status( 'instock' ); }
		elseif ( 'Hết hàng' === $stock_name ) { $product->set_stock_status( 'outofstock' ); }
		elseif ( 'Đặt trước' === $stock_name ) { $product->set_stock_status( 'onbackorder' ); }
		$weight_prop = $this->prop( $props, self::P_WEIGHT );
		if ( isset( $weight_prop['number'] ) && null !== $weight_prop['number'] ) { $product->set_weight( (string) $weight_prop['number'] ); }
		$len_prop = $this->prop( $props, self::P_LENGTH );
		$wid_prop = $this->prop( $props, self::P_WIDTH );
		$hei_prop = $this->prop( $props, self::P_HEIGHT );
		if ( isset( $len_prop['number'] ) && null !== $len_prop['number'] ) { $product->set_length( (string) $len_prop['number'] ); }
		if ( isset( $wid_prop['number'] ) && null !== $wid_prop['number'] ) { $product->set_width( (string) $wid_prop['number'] ); }
		if ( isset( $hei_prop['number'] ) && null !== $hei_prop['number'] ) { $product->set_height( (string) $hei_prop['number'] ); }

		$product->save();
		$prod_id = $product->get_id();

		update_post_meta( $prod_id, '_notion_page_id', $pid );
		update_post_meta( $prod_id, '_notion_sync_ver', self::PLUGIN_VERSION );
		if ( $last_edited ) { update_post_meta( $prod_id, '_notion_last_edited', $last_edited ); }
		$p3d = $this->prop( $props, self::P_3D );
		if ( ! empty( $p3d['url'] ) ) {
			update_post_meta( $prod_id, '_notion_3d_model', esc_url_raw( $p3d['url'] ) );
		}

		// Danh mục: dùng tên tiếng Việt từ cột "Vietnamese" trong database Category Furniture (fallback: tên gốc).
		$cats = $this->relation_titles( $this->prop( $props, self::P_CAT ), 'Vietnamese' );
		if ( $cats ) {
			$term_ids = array();
			foreach ( $cats as $c ) {
				$tid = $this->find_or_create_cat( $c );
				if ( $tid ) { $term_ids[] = $tid; }
			}
			if ( $term_ids ) { wp_set_object_terms( $prod_id, $term_ids, 'product_cat' ); }
		}

		// Bộ sưu tập (product_brand) lấy từ relation "Furniture Collection".
		$cols = $this->relation_titles( $this->prop( $props, 'Furniture Collection' ) );
		if ( $cols ) {
			$bids = array();
			foreach ( $cols as $cn ) {
				$bid = $this->find_or_create_brand( $cn );
				if ( $bid ) { $bids[] = $bid; }
			}
			if ( $bids ) { wp_set_object_terms( $prod_id, $bids, self::BRAND_TAX ); }
		}

		// Vật liệu → lưu vào meta để hiển thị ở tab "Vật liệu".
		$this->sync_materials( $props, $prod_id );

		// Ảnh.
		$this->sync_images( $product, $props, $pid );
		$product->save();

		// Ghi ngược về Notion.
		// Dung layout Elementor + do du lieu vao cac truong ACF (giong trang Camaleonda).
		$this->apply_layout( $prod_id, $pid, $props );

		$permalink = get_permalink( $prod_id );
		$wb_props  = array(
			self::P_WPID   => array( 'number' => $prod_id ),
			self::P_WPLINK => array( 'url' => $permalink ),
			self::P_SYNC   => array( 'select' => array( 'name' => 'Đã sync' ) ),
		);
		if ( $force ) { $wb_props[ self::P_SYNCWEB ] = array( 'status' => array( 'name' => self::S_DONE ) ); }
		$wb = $this->notion_request( 'PATCH', '/pages/' . $pid, array( 'properties' => $wb_props ) );
		if ( is_wp_error( $wb ) ) {
			$this->log[] = 'Cảnh báo: không ghi ngược được về Notion cho "' . $name . '": ' . $wb->get_error_message();
		}

		$this->log[] = ( $is_new ? 'Tạo mới: ' : 'Cập nhật: ' ) . $name . ' (#' . $prod_id . ( $publish ? '' : ', draft' ) . ')';
	}

	private function find_or_create_cat( $name ) {
		$term = get_term_by( 'name', $name, 'product_cat' );
		if ( $term ) { return intval( $term->term_id ); }
		$new = wp_insert_term( $name, 'product_cat' );
		if ( is_wp_error( $new ) ) {
			$this->log[] = 'Cảnh báo: không tạo được danh mục "' . $name . '"';
			return 0;
		}
		return intval( $new['term_id'] );
	}

	private function collect_content_images( $blocks, $depth = 0 ) {
		if ( $depth > 4 || ! $blocks ) { return array(); }
		$out = array();
		foreach ( $blocks as $b ) {
			$type = isset( $b['type'] ) ? $b['type'] : '';
			if ( 'image' === $type ) {
				$url = isset( $b['image']['file']['url'] ) ? $b['image']['file']['url'] : ( isset( $b['image']['external']['url'] ) ? $b['image']['external']['url'] : '' );
				if ( $url ) { $out[] = $url; }
			} elseif ( ! empty( $b['has_children'] ) ) {
				$out = array_merge( $out, $this->collect_content_images( $this->fetch_block_children( $b['id'] ), $depth + 1 ) );
			}
		}
		return $out;
	}

	private function sync_images( $product, $props, $page_id ) {
		$files_prop = $this->prop( $props, self::P_FILES );
		$files      = isset( $files_prop['files'] ) ? (array) $files_prop['files'] : array();
		$raw_urls   = array();
		foreach ( $files as $f ) {
			if ( isset( $f['file']['url'] ) ) { $raw_urls[] = $f['file']['url']; }
			elseif ( isset( $f['external']['url'] ) ) { $raw_urls[] = $f['external']['url']; }
		}
		// Nếu property "Files & media" trống, dùng ảnh chèn trong nội dung trang làm phương án dự phòng.
		if ( ! $raw_urls ) {
			$raw_urls = $this->collect_content_images( $this->fetch_block_children( $page_id ) );
		}
		if ( ! $raw_urls ) { return; }

		$urls = array();
		$keys = array();
		foreach ( $raw_urls as $url ) {
			if ( '' === $url ) { continue; }
			$path = wp_parse_url( $url, PHP_URL_PATH );
			$base = $path ? wp_basename( $path ) : md5( $url );
			if ( ! preg_match( '/\\.(jpe?g|png|gif|webp|avif)$/i', $base ) ) { continue; }
			$urls[] = array( 'url' => $url, 'name' => $base );
			$keys[] = $base;
			if ( count( $urls ) >= self::MAX_IMAGES ) { break; }
		}
		if ( ! $urls ) { return; }

		$prod_id  = $product->get_id();
		$existing = get_post_meta( $prod_id, '_notion_media_keys', true );
		if ( is_array( $existing ) && $existing === $keys && $product->get_image_id() ) {
			return; // Ảnh không đổi.
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_ids = array();
		foreach ( $urls as $u ) {
			$aid = $this->sideload( $u['url'], $u['name'], $prod_id );
			if ( $aid ) { $attachment_ids[] = $aid; }
		}
		if ( ! $attachment_ids ) { return; }

		$product->set_image_id( array_shift( $attachment_ids ) );
		$product->set_gallery_image_ids( $attachment_ids );
		update_post_meta( $prod_id, '_notion_media_keys', $keys );
	}

	private function sideload( $url, $filename, $post_id ) {
		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) { return 0; }
		$file = array( 'name' => sanitize_file_name( $filename ), 'tmp_name' => $tmp );
		$aid  = media_handle_sideload( $file, $post_id );
		if ( is_wp_error( $aid ) ) {
			@unlink( $tmp );
			return 0;
		}
		return intval( $aid );
	}

	/* ---------------- Vật liệu ---------------- */

	// Đọc relation "Vật liệu" → lấy chi tiết từng material, tải thumbnail về media library và lưu vào post meta.
	/* ---------------- 1.8.0: Vật liệu là post type riêng ---------------- */

	/* ---------------- 1.9.0: layout Vật liệu + Elementor ---------------- */

	public function material_template( $tpl ) {
		if ( is_singular( self::CPT_MAT ) || is_post_type_archive( self::CPT_MAT ) || is_tax( self::TAX_MAT_TYPE ) || is_tax( self::TAX_MAT_COL ) ) {
			$f = plugin_dir_path( __FILE__ ) . 'templates/cns-material.php';
			if ( file_exists( $f ) ) { return $f; }
		}
		return $tpl;
	}

	public function material_archive_query( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) { return; }
		if ( $q->is_post_type_archive( self::CPT_MAT ) || $q->is_tax( self::TAX_MAT_TYPE ) || $q->is_tax( self::TAX_MAT_COL ) ) {
			$q->set( 'posts_per_page', -1 );
			$q->set( 'orderby', 'title' );
			$q->set( 'order', 'ASC' );
			$tax = array();
			if ( ! empty( $_GET['loai'] ) ) { $tax[] = array( 'taxonomy' => self::TAX_MAT_TYPE, 'field' => 'slug', 'terms' => sanitize_title( wp_unslash( $_GET['loai'] ) ) ); }
			if ( ! empty( $_GET['bst'] ) ) { $tax[] = array( 'taxonomy' => self::TAX_MAT_COL, 'field' => 'slug', 'terms' => sanitize_title( wp_unslash( $_GET['bst'] ) ) ); }
			if ( $tax ) { $q->set( 'tax_query', $tax ); }
		}
	}

	public function el_cpt_support( $v ) {
		if ( ! is_array( $v ) ) { $v = array( 'page', 'post' ); }
		if ( ! in_array( self::CPT_MAT, $v, true ) ) { $v[] = self::CPT_MAT; }
		return $v;
	}

	public function el_query_materials( $query ) {
		if ( ! is_object( $query ) ) { return $query; }
		$query->set( 'post_type', self::CPT_MAT );
		$query->set( 'post_status', 'publish' );
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
		if ( is_tax( self::TAX_MAT_TYPE ) ) {
			$t = get_queried_object();
			if ( $t && isset( $t->term_id ) ) {
				$query->set( 'tax_query', array( array( 'taxonomy' => self::TAX_MAT_TYPE, 'field' => 'term_id', 'terms' => $t->term_id ) ) );
			}
		}
		return $query;
	}

	private function mat_items_from_posts( $posts ) {
		$items = array();
		foreach ( $posts as $po ) {
			$terms = wp_get_object_terms( $po->ID, self::TAX_MAT_TYPE );
			$tl    = ( ! is_wp_error( $terms ) && $terms ) ? get_term_link( $terms[0] ) : '';
			$items[] = array(
				'name'  => get_the_title( $po ),
				'type'  => ( ! is_wp_error( $terms ) && $terms ) ? $terms[0]->name : '',
				'tlink' => is_wp_error( $tl ) ? '' : $tl,
				'color' => get_post_meta( $po->ID, '_cns_mat_color', true ),
				'code'  => get_post_meta( $po->ID, '_cns_mat_code', true ),
				'url'   => get_permalink( $po ),
				'thumb' => get_the_post_thumbnail_url( $po->ID, 'large' ),
			);
		}
		return $items;
	}

	public function render_material_page() {
		if ( is_singular( self::CPT_MAT ) ) { $this->render_single_material(); return; }
		$this->render_material_archive();
	}

	private function render_material_archive() {
		$sel_type = isset( $_GET['loai'] ) ? sanitize_title( wp_unslash( $_GET['loai'] ) ) : '';
		$sel_col  = isset( $_GET['bst'] ) ? sanitize_title( wp_unslash( $_GET['bst'] ) ) : '';
		$obj = get_queried_object();
		if ( is_tax( self::TAX_MAT_TYPE ) && $obj && isset( $obj->slug ) ) { $sel_type = $obj->slug; }
		if ( is_tax( self::TAX_MAT_COL ) && $obj && isset( $obj->slug ) ) { $sel_col = $obj->slug; }
		$tt = '' !== $sel_type ? get_term_by( 'slug', $sel_type, self::TAX_MAT_TYPE ) : false;
		$tc = '' !== $sel_col ? get_term_by( 'slug', $sel_col, self::TAX_MAT_COL ) : false;
		$title = 'Thư viện vật liệu';
		$desc  = '';
		if ( $tt ) { $title = $tt->name; $desc = $tt->description; }
		if ( $tc ) { $title = $tc->name . ( $tt ? ' · ' . $tt->name : '' ); $desc = $tc->description ? $tc->description : $desc; }
		$args  = array( 'post_type' => self::CPT_MAT, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' );
		$tax_q = array();
		if ( $tt ) { $tax_q[] = array( 'taxonomy' => self::TAX_MAT_TYPE, 'field' => 'term_id', 'terms' => $tt->term_id ); }
		if ( $tc ) { $tax_q[] = array( 'taxonomy' => self::TAX_MAT_COL, 'field' => 'term_id', 'terms' => $tc->term_id ); }
		if ( $tax_q ) { $args['tax_query'] = $tax_q; }
		$posts = get_posts( $args );
		echo '<div class="cns-mat-wrap">';
		echo '<div class="cns-mat-archive-head">';
		echo '<div class="cns-mat-eyebrow">COLECTIA MATERIALS</div>';
		echo '<h1 class="cns-mat-title">' . esc_html( $title ) . '</h1>';
		if ( '' !== $desc ) { echo '<div class="cns-mat-lead">' . wp_kses_post( $desc ) . '</div>'; }
		echo '</div>';
		echo $this->render_mat_filters( $tt ? $tt->slug : '', $tc ? $tc->slug : '' );
		if ( $posts ) {
			// Trang thư viện mặc định hiển thị theo từng bộ sưu tập; khi đang lọc thì giữ lưới đơn.
			echo $this->render_materials_by_type( $posts );
		} else {
			echo '<p class="cns-mat-empty">Chưa có vật liệu nào.</p>';
		}
		echo '</div>';
	}

	private function render_mat_filters( $sel_type, $sel_col ) {
		$base  = get_post_type_archive_link( self::CPT_MAT );
		$order = array( 'Vải', 'Đá', 'Da', 'Kim loại', 'Gỗ' );
		$terms = get_terms( array( 'taxonomy' => self::TAX_MAT_TYPE, 'hide_empty' => true ) );
		if ( is_wp_error( $terms ) ) { $terms = array(); }
		usort( $terms, function ( $a, $b ) use ( $order ) {
			$ia = array_search( $a->name, $order, true );
			$ib = array_search( $b->name, $order, true );
			$ia = false === $ia ? 99 : $ia;
			$ib = false === $ib ? 99 : $ib;
			return $ia === $ib ? strcmp( $a->name, $b->name ) : $ia - $ib;
		} );
		$cols = get_terms( array( 'taxonomy' => self::TAX_MAT_COL, 'hide_empty' => true ) );
		if ( is_wp_error( $cols ) ) { $cols = array(); }
		$mk = function ( $type, $col ) use ( $base ) {
			$url = $base;
			if ( '' !== $type ) { $url = add_query_arg( 'loai', $type, $url ); }
			if ( '' !== $col ) { $url = add_query_arg( 'bst', $col, $url ); }
			return $url;
		};
		$html  = '<div class="cns-mat-filters">';
		$html .= '<div class="cns-mat-filter-row"><span class="cns-mat-filter-label">Loại vật liệu</span>';
		$html .= '<a class="cns-chip' . ( '' === $sel_type ? ' is-active' : '' ) . '" href="' . esc_url( $mk( '', $sel_col ) ) . '">Tất cả</a>';
		foreach ( $terms as $t ) {
			$html .= '<a class="cns-chip' . ( $sel_type === $t->slug ? ' is-active' : '' ) . '" href="' . esc_url( $mk( $t->slug, $sel_col ) ) . '">' . esc_html( $t->name ) . '</a>';
		}
		$html .= '</div>';
		// Bộ sưu tập được thể hiện bằng từng section, không cần thanh lọc riêng.
		$html .= '</div>';
		return $html;
	}

	// Render từng bộ sưu tập cùng ảnh đại diện được đồng bộ từ database Notion.
	private function render_materials_by_type( $posts ) {
		$types=array(); foreach($posts as $post){$t=wp_get_object_terms($post->ID,self::TAX_MAT_TYPE);$n=(!is_wp_error($t)&&$t)?$t[0]->name:'Khác';$types[$n][]=$post;}
		$order=array('Vải','Đá','Da','Kim loại','Gỗ','Khác');uksort($types,function($a,$b)use($order){$x=array_search($a,$order,true);$y=array_search($b,$order,true);return(false===$x?99:$x)-(false===$y?99:$y);});
		$html='<div class="cns-mat-type-library">';foreach($types as $type=>$items){$sets=array();$plain=array();foreach($items as $post){$cs=wp_get_object_terms($post->ID,self::TAX_MAT_COL);if(is_wp_error($cs)||!$cs){$plain[]=$post;continue;}foreach($cs as $c){$sets[$c->term_id]['term']=$c;$sets[$c->term_id]['posts'][]=$post;}}$html.='<section class="cns-mat-type-section"><h2 class="cns-mat-type-title">'.esc_html($type).'</h2>';foreach($sets as $set){$term=$set['term'];$thumb=get_term_meta($term->term_id,'_cns_mat_collection_thumb',true);$html.='<div class="cns-mat-collection"><div class="cns-mat-collection-head">'.($thumb?'<img src="'.esc_url($thumb).'" alt="'.esc_attr($term->name).'" loading="lazy" />':'').'<span><span class="cns-mat-collection-kicker">BỘ SƯU TẬP</span><strong>'.esc_html($term->name).'</strong>'.($term->description?'<em>'.esc_html($term->description).'</em>':'').'</span></div>'.$this->render_grid($this->mat_items_from_posts($set['posts']),false,6).'</div>';}$html.= $plain?$this->render_grid($this->mat_items_from_posts($plain),false,6):'';$html.='</section>';}return $html.'</div>';
	}

	private function render_single_material() {
		while ( have_posts() ) {
			the_post();
			$id      = get_the_ID();
			$terms   = wp_get_object_terms( $id, self::TAX_MAT_TYPE, array( 'fields' => 'all' ) );
			$tname   = ( ! is_wp_error( $terms ) && $terms ) ? $terms[0]->name : '';
			$tlink   = ( ! is_wp_error( $terms ) && $terms ) ? get_term_link( $terms[0] ) : '';
			$color   = get_post_meta( $id, '_cns_mat_color', true );
			$code    = get_post_meta( $id, '_cns_mat_code', true );
			$factory = get_post_meta( $id, '_cns_mat_factory', true );
			$colls   = get_post_meta( $id, '_cns_mat_collections', true );
			$thumb   = get_the_post_thumbnail_url( $id, 'large' );
			echo '<div class="cns-mat-wrap cns-mat-single">';
			echo '<div class="cns-mat-crumb"><a href="' . esc_url( get_post_type_archive_link( self::CPT_MAT ) ) . '">Thư viện vật liệu</a>';
			if ( $tname && ! is_wp_error( $tlink ) ) { echo ' <span>/</span> <a href="' . esc_url( $tlink ) . '">' . esc_html( $tname ) . '</a>'; }
			echo '</div>';
			echo '<div class="cns-mat-hero">';
			echo '<div class="cns-mat-hero-media">';
			if ( $thumb ) {
				echo '<img class="cns-mat-hero-img" src="' . esc_url( $thumb ) . '" alt="' . esc_attr( get_the_title() ) . '" />';
			} else {
				echo '<span class="cns-mat-hero-img cns-mat-swatch" style="background:' . esc_attr( $this->color_hex( $color ) ) . ';"></span>';
			}
			echo '</div>';
			echo '<div class="cns-mat-hero-info">';
			if ( $tname ) { echo '<div class="cns-mat-eyebrow">' . esc_html( $tname ) . '</div>'; }
			echo '<h1 class="cns-mat-title">' . esc_html( get_the_title() ) . '</h1>';
			echo '<table class="cns-mat-specs"><tbody>';
			if ( $code )    { echo '<tr><th>Mã vật liệu</th><td>' . esc_html( $code ) . '</td></tr>'; }
			if ( $tname )   { echo '<tr><th>Nhóm vật liệu</th><td>' . esc_html( $tname ) . '</td></tr>'; }
			if ( $color )   { echo '<tr><th>Màu sắc</th><td><span class="cns-mat-dot" style="background:' . esc_attr( $this->color_hex( $color ) ) . ';"></span>' . esc_html( $color ) . '</td></tr>'; }
			if ( $factory ) { echo '<tr><th>Mã nhà máy</th><td>' . esc_html( $factory ) . '</td></tr>'; }
			if ( $colls )   { echo '<tr><th>Bộ sưu tập</th><td>' . esc_html( $colls ) . '</td></tr>'; }
			echo '</tbody></table>';
			$ex = get_the_excerpt();
			if ( $ex ) { echo '<div class="cns-mat-lead">' . wp_kses_post( $ex ) . '</div>'; }
			echo $this->material_moodboard_action( $id );
			echo '</div></div>';
			$content = apply_filters( 'the_content', get_the_content() );
			if ( trim( wp_strip_all_tags( $content ) ) !== '' ) {
				echo '<div class="cns-mat-sec"><div class="cns-mat-sec-title">Mô tả</div><div class="cns-notion-content">' . $content . '</div></div>';
			}
			$this->render_material_products( get_the_title() );
			if ( $tname ) {
				$rel = get_posts( array(
					'post_type' => self::CPT_MAT, 'post_status' => 'publish', 'posts_per_page' => 8,
					'post__not_in' => array( $id ), 'orderby' => 'rand',
					'tax_query' => array( array( 'taxonomy' => self::TAX_MAT_TYPE, 'field' => 'name', 'terms' => $tname ) ),
				) );
				if ( $rel ) {
					echo '<div class="cns-mat-sec"><div class="cns-mat-sec-title">Vật liệu cùng nhóm</div>';
					echo $this->render_grid( $this->mat_items_from_posts( $rel ), false, 4 );
					echo '</div>';
				}
			}
			echo '</div>';
		}
	}

	private function render_material_products( $mat_name ) {
		if ( '' === $mat_name ) { return; }
		$q = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 8,
			'meta_query'     => array( array( 'key' => '_notion_materials', 'value' => $mat_name, 'compare' => 'LIKE' ) ),
		) );
		if ( ! $q->have_posts() ) { wp_reset_postdata(); return; }
		$items = array();
		foreach ( $q->posts as $po ) {
			$items[] = array(
				'name'  => get_the_title( $po ),
				'type'  => '',
				'color' => '',
				'code'  => '',
				'url'   => get_permalink( $po ),
				'thumb' => get_the_post_thumbnail_url( $po->ID, 'medium_large' ),
			);
		}
		wp_reset_postdata();
		echo '<div class="cns-mat-sec"><div class="cns-mat-sec-title">Nội thất sử dụng vật liệu này</div>';
		echo $this->render_grid( $items, false, 4 );
		echo '</div>';
	}

	/* ---------------- GitHub auto-update ---------------- */

	private function gh_api( $path ) {
		$args = array( 'timeout' => 15, 'headers' => array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'colectia-notion-sync',
		) );
		$tok = trim( (string) get_option( self::OPT_GH_TOKEN, '' ) );
		if ( '' !== $tok ) { $args['headers']['Authorization'] = 'Bearer ' . $tok; }
		$r = wp_remote_get( 'https://api.github.com/repos/' . self::GH_OWNER . '/' . self::GH_REPO . $path, $args );
		if ( is_wp_error( $r ) ) { return null; }
		$b = json_decode( wp_remote_retrieve_body( $r ), true );
		return is_array( $b ) ? $b : null;
	}

	public function gh_latest( $force = false ) {
		$cached = get_site_transient( self::TR_GH );
		if ( ! $force && is_array( $cached ) ) { return $cached; }
		$branch = get_option( self::OPT_GH_BRANCH, 'main' );
		$out = array(
			'version' => '',
			'package' => '',
			'url'     => 'https://github.com/' . self::GH_OWNER . '/' . self::GH_REPO,
			'notes'   => '',
			'source'  => '',
			'checked' => time(),
		);
		$rel = $this->gh_api( '/releases/latest' );
		if ( is_array( $rel ) && ! empty( $rel['tag_name'] ) ) {
			$out['version'] = ltrim( $rel['tag_name'], 'vV' );
			$out['notes']   = isset( $rel['body'] ) ? (string) $rel['body'] : '';
			$out['source']  = 'release';
			if ( ! empty( $rel['html_url'] ) ) { $out['url'] = $rel['html_url']; }
			if ( ! empty( $rel['assets'] ) && is_array( $rel['assets'] ) ) {
				foreach ( $rel['assets'] as $a ) {
					if ( ! empty( $a['name'] ) && '.zip' === substr( $a['name'], -4 ) && ! empty( $a['browser_download_url'] ) ) {
						$out['package'] = $a['browser_download_url'];
						break;
					}
				}
			}
			if ( '' === $out['package'] && ! empty( $rel['zipball_url'] ) ) { $out['package'] = $rel['zipball_url']; }
		} else {
			$f = $this->gh_api( '/contents/colectia-notion-sync.php?ref=' . rawurlencode( $branch ) );
			if ( is_array( $f ) && ! empty( $f['content'] ) ) {
				$src = base64_decode( str_replace( array( "\n", "\r" ), '', $f['content'] ) );
				if ( $src && preg_match( '/^\s*\*\s*Version:\s*([0-9][0-9.]*)/mi', $src, $m ) ) {
					$out['version'] = $m[1];
					$out['source']  = 'branch:' . $branch;
					$out['package'] = 'https://github.com/' . self::GH_OWNER . '/' . self::GH_REPO . '/archive/refs/heads/' . $branch . '.zip';
				}
			}
		}
		set_site_transient( self::TR_GH, $out, 3 * HOUR_IN_SECONDS );
		return $out;
	}

	public function gh_update_check( $update, $plugin_data, $plugin_file ) {
		if ( empty( $plugin_data['UpdateURI'] ) || false === strpos( $plugin_data['UpdateURI'], self::GH_REPO ) ) { return $update; }
		$l = $this->gh_latest();
		if ( empty( $l['version'] ) || empty( $l['package'] ) ) { return $update; }
		if ( version_compare( $l['version'], self::PLUGIN_VERSION, '<=' ) ) { return $update; }
		return array(
			'id'           => $plugin_data['UpdateURI'],
			'slug'         => self::GH_REPO,
			'plugin'       => $plugin_file,
			'version'      => $l['version'],
			'url'          => $l['url'],
			'package'      => $l['package'],
			'requires_php' => '7.2',
		);
	}

	public function gh_plugin_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::GH_REPO !== $args->slug ) { return $res; }
		$l = $this->gh_latest();
		$o = new stdClass();
		$o->name          = 'COLECTIA Notion Sync';
		$o->slug          = self::GH_REPO;
		$o->version       = $l['version'] ? $l['version'] : self::PLUGIN_VERSION;
		$o->author        = 'COLECTIA';
		$o->homepage      = $l['url'];
		$o->download_link = $l['package'];
		$o->requires_php  = '7.2';
		$o->sections      = array( 'description' => 'Plugin đồng bộ Notion → WooCommerce cho COLECTIA.', 'changelog' => wpautop( esc_html( (string) $l['notes'] ) ) );
		return $o;
	}

	public function gh_fix_dir( $source, $remote_source, $upgrader = null, $hook_extra = array() ) {
		if ( ! is_array( $hook_extra ) || empty( $hook_extra['plugin'] ) ) { return $source; }
		if ( false === strpos( $hook_extra['plugin'], self::GH_REPO ) ) { return $source; }
		$want = trailingslashit( $remote_source ) . self::GH_REPO;
		if ( untrailingslashit( $source ) === $want ) { return $source; }
		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->move( untrailingslashit( $source ), $want ) ) {
			return trailingslashit( $want );
		}
		return $source;
	}

	public function gh_after_update( $upgrader, $data ) {
		if ( ! is_array( $data ) || empty( $data['type'] ) || 'plugin' !== $data['type'] ) { return; }
		delete_site_transient( self::TR_GH );
		delete_option( self::OPT_RW_VER );
	}

	public function maybe_flush_rewrites() {
		if ( get_option( self::OPT_RW_VER ) === self::PLUGIN_VERSION ) { return; }
		flush_rewrite_rules( false );
		update_option( self::OPT_RW_VER, self::PLUGIN_VERSION, false );
	}

	public function handle_gh_check() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Không đủ quyền' ); }
		check_admin_referer( 'cns_gh_check' );
		delete_site_transient( self::TR_GH );
		$l = $this->gh_latest( true );
		delete_site_transient( 'update_plugins' );
		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'cns_gh', rawurlencode( $l['version'] ? $l['version'] : 'none' ), $back ? $back : admin_url() ) );
		exit;
	}

	public function gh_admin_card() {
		$l   = $this->gh_latest();
		$new = ( ! empty( $l['version'] ) && version_compare( $l['version'], self::PLUGIN_VERSION, '>' ) );
		echo '<div class="card" style="max-width:820px;padding:14px 18px;margin:16px 0;">';
		echo '<h2 style="margin-top:0;">Nguồn code: GitHub</h2>';
		echo '<p>Repo: <a href="https://github.com/' . esc_attr( self::GH_OWNER ) . '/' . esc_attr( self::GH_REPO ) . '" target="_blank">' . esc_html( self::GH_OWNER . '/' . self::GH_REPO ) . '</a></p>';
		echo '<p>Phiên bản đang cài: <b>' . esc_html( self::PLUGIN_VERSION ) . '</b> &nbsp;|&nbsp; Phiên bản trên GitHub: <b>' . esc_html( $l['version'] ? $l['version'] : 'không đọc được' ) . '</b>';
		if ( ! empty( $l['source'] ) ) { echo ' <span style="color:#888;">(' . esc_html( $l['source'] ) . ')</span>'; }
		echo '</p>';
		if ( $new ) {
			echo '<p style="color:#b8860b;"><b>Có bản mới.</b> Vào <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Plugins</a> và bấm "Cập nhật ngay".</p>';
		} else {
			echo '<p style="color:#3c763d;">Đang dùng bản mới nhất.</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0;">';
		echo '<input type="hidden" name="action" value="cns_gh_check" />';
		wp_nonce_field( 'cns_gh_check' );
		submit_button( 'Kiểm tra cập nhật từ GitHub', 'secondary', 'submit', false );
		echo '</form>';
		echo '</div>';
	}

	public function register_material_cpt() {
		register_post_type( self::CPT_MAT, array(
			'labels'       => array(
				'name'          => 'Vật liệu',
				'singular_name' => 'Vật liệu',
				'menu_name'     => 'Vật liệu',
				'add_new'       => 'Thêm mới',
				'add_new_item'  => 'Thêm vật liệu',
				'edit_item'     => 'Sửa vật liệu',
				'search_items'  => 'Tìm vật liệu',
				'all_items'     => 'Tất cả vật liệu',
			),
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-art',
			'menu_position'=> 26,
			'has_archive'  => 'thu-vien-vat-lieu',
			'rewrite'      => array( 'slug' => 'material', 'with_front' => false ),
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
			'taxonomies'   => array( self::TAX_MAT_TYPE, self::TAX_MAT_COL ),
		) );
		register_taxonomy( self::TAX_MAT_TYPE, self::CPT_MAT, array(
			'labels'       => array( 'name' => 'Nhóm vật liệu', 'singular_name' => 'Nhóm vật liệu', 'menu_name' => 'Nhóm vật liệu' ),
			'public'       => true,
			'hierarchical' => true,
			'show_in_rest' => true,
			'show_admin_column' => true,
			'rewrite'      => array( 'slug' => 'nhom-vat-lieu', 'with_front' => false ),
		) );
		register_taxonomy( self::TAX_MAT_COL, self::CPT_MAT, array(
			'labels'       => array( 'name' => 'Bộ sưu tập vật liệu', 'singular_name' => 'Bộ sưu tập vật liệu', 'menu_name' => 'Bộ sưu tập' ),
			'public'       => true,
			'hierarchical' => false,
			'show_in_rest' => true,
			'show_admin_column' => true,
			'rewrite'      => array( 'slug' => 'bo-suu-tap-vat-lieu', 'with_front' => false ),
		) );
	}

	/* ---------------- 1.8.0: chế độ catalog (bỏ giá) ---------------- */

	private function catalog_mode_hooks() {
		add_filter( 'woocommerce_get_price_html', '__return_empty_string', 99 );
		add_filter( 'woocommerce_is_purchasable', '__return_true', 99 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'project_cart_label' ), 99 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'project_cart_label' ), 99 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'redirect_to_project' ), 99 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'store_selected_project_materials' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'show_selected_project_materials' ), 10, 2 );
		add_filter( 'woocommerce_page_title', array( $this, 'project_page_title' ), 99 );
		add_action( 'template_redirect', array( $this, 'disable_checkout_for_project' ), 1 );
		add_action( 'template_redirect', array( $this, 'redirect_legacy_account_endpoints' ), 2 );
		add_action( 'wp', array( $this, 'configure_project_cart' ), 30 );
		add_action( 'wp_head', array( $this, 'project_cart_styles' ), 99 );
		add_action( 'woocommerce_after_cart_table', array( $this, 'render_project_note' ) );
		add_action( 'woocommerce_before_cart_totals', array( $this, 'render_project_overview' ) );
		add_action( 'woocommerce_cart_updated', array( $this, 'save_project_note' ) );
		add_action( 'woocommerce_after_cart_item_name', array( $this, 'render_cart_item_note' ), 20, 2 );
		add_filter( 'woocommerce_product_tabs', array( $this, 'remove_product_reviews_tab' ), 999 );
		add_filter( 'comments_open', array( $this, 'disable_product_reviews' ), 99, 2 );
		add_action( 'wp_footer', array( $this, 'replace_download_with_project_button' ), 120 );
		add_action( 'init', array( $this, 'register_project_account_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_project_account_menu' ), 9999 );
		add_action( 'woocommerce_account_du-an_endpoint', array( $this, 'render_project_account_page' ) );
		add_action( 'woocommerce_account_moodboard_endpoint', array( $this, 'render_moodboard_account_page' ) );
		add_action( 'woocommerce_account_thong-tin_endpoint', array( $this, 'render_information_account_page' ) );
		add_filter( 'wc_add_to_cart_message_html', array( $this, 'project_add_message' ), 99, 3 );
		add_filter( 'woocommerce_cart_item_class', array( $this, 'project_cart_item_class' ), 20, 3 );
		add_action( 'wp_footer', array( $this, 'project_cart_live_script' ), 140 );
		add_action( 'woocommerce_before_cart_totals', array( $this, 'render_project_moodboard' ), 5 );
		add_action( 'wp_footer', array( $this, 'material_moodboard_script' ), 150 );
		add_action( 'wp_footer', array( $this, 'moodboard_export_script' ), 151 );
		add_action( 'wp_ajax_cns_add_moodboard', array( $this, 'ajax_add_moodboard' ) );
		add_action( 'wp_ajax_nopriv_cns_add_moodboard', array( $this, 'ajax_add_moodboard' ) );
	}

	public function project_cart_label() { return 'THÊM VÀO DỰ ÁN'; }
	public function redirect_to_project() { return wc_get_cart_url(); }
	public function project_page_title( $title ) { return function_exists('is_cart') && is_cart() ? 'Dự án' : $title; }
	public function disable_checkout_for_project() { if ( function_exists('is_checkout') && is_checkout() && ! is_order_received_page() ) { wp_safe_redirect( wc_get_cart_url() ); exit; } }
	public function redirect_legacy_account_endpoints() { if(!function_exists('is_account_page')||!is_account_page()||!function_exists('is_wc_endpoint_url'))return;if(is_wc_endpoint_url('orders')||is_wc_endpoint_url('downloads')){wp_safe_redirect(wc_get_page_permalink('myaccount'));exit;} }
	public function project_add_message( $message, $products, $show_qty ) { $message=str_ireplace(array('đã được thêm vào giỏ hàng.','Xem giỏ hàng','View cart','has been added to your cart.'),array('đã được thêm vào dự án.','Xem dự án','Xem dự án','đã được thêm vào dự án.'),$message);return $message; }
	private function project_category_term( $product_id ) { $terms=wp_get_post_terms($product_id,'product_cat');if(is_wp_error($terms)||!$terms)return null;usort($terms,function($a,$b){return count(get_ancestors($b->term_id,'product_cat'))-count(get_ancestors($a->term_id,'product_cat'));});return $terms[0]; }
	public function project_cart_item_class( $class, $cart_item, $cart_item_key ) { $product=isset($cart_item['data'])?$cart_item['data']:null;$term=$product?$this->project_category_term($product->get_id()):null;return $class.($term?' cns-project-cat-'.sanitize_html_class($term->slug):' cns-project-cat-other'); }


	public function store_selected_project_materials( $cart_item_data, $product_id, $variation_id ) {
		$raw=isset($_POST['cns_selected_materials'])?json_decode(wp_unslash($_POST['cns_selected_materials']),true):array();$chosen=array();if(is_array($raw)){foreach($raw as $m){if(empty($m['name']))continue;$chosen[]=array('type'=>sanitize_text_field(isset($m['type'])?$m['type']:''),'name'=>sanitize_text_field($m['name']),'code'=>sanitize_text_field(isset($m['code'])?$m['code']:''),'image'=>esc_url_raw(isset($m['image'])?$m['image']:''));}}if($chosen){$cart_item_data['cns_selected_materials']=$chosen;$cart_item_data['cns_project_key']=md5(wp_json_encode($chosen));}return $cart_item_data;
	}

	public function show_selected_project_materials( $data, $cart_item ) {
		if ( ! empty( $cart_item['cns_selected_materials'] ) && is_array( $cart_item['cns_selected_materials'] ) ) { $names=array(); foreach($cart_item['cns_selected_materials'] as $m){$names[]=trim((!empty($m['type'])?$m['type'].': ':'').$m['name']);} $data[]=array('name'=>'Vật liệu đã chọn','value'=>implode(', ',$names),'display'=>implode(', ',$names)); }
		return $data;
	}
	public function render_cart_item_note( $cart_item, $cart_item_key ) { $note=isset($cart_item['cns_product_note'])?$cart_item['cns_product_note']:''; echo '<div class="cns-item-note"><label for="cns_note_'.esc_attr($cart_item_key).'">Ghi chú sản phẩm</label><textarea id="cns_note_'.esc_attr($cart_item_key).'" name="cns_item_note['.esc_attr($cart_item_key).']" placeholder="Màu sắc, vị trí sử dụng, yêu cầu hoàn thiện...">'.esc_textarea($note).'</textarea></div>'; }
	public function remove_product_reviews_tab( $tabs ) { unset($tabs['reviews']); return $tabs; }
	public function disable_product_reviews( $open, $post_id ) { return 'product'===get_post_type($post_id)?false:$open; }
	public function replace_download_with_project_button() { if(!function_exists('is_product')||!is_product())return; ?><style>.single-product form.cart .quantity{display:none!important}.cns-download-project-form{margin:0!important;width:100%}.cns-download-project-form .single_add_to_cart_button{width:100%;font-family:inherit!important;background:#f1f1f1!important;color:#171717!important;border:1px solid #dedede!important}.cns-download-project-form .single_add_to_cart_button:hover{background:#fff!important;border-color:#777!important}</style><script>(function(){var form=document.querySelector('form.cart');if(!form)return;var target=[].slice.call(document.querySelectorAll('a,button')).find(function(el){var t=(el.textContent||'').trim().toLowerCase();return t==='tải xuống'||t==='download'});if(!target)return;var btn=form.querySelector('.single_add_to_cart_button');if(!btn)return;btn.textContent='THÊM VÀO DỰ ÁN';btn.className=target.className+' single_add_to_cart_button button alt';form.classList.add('cns-download-project-form');target.replaceWith(form)})();</script><?php }
	public function register_project_account_endpoint() { add_rewrite_endpoint('du-an',EP_ROOT|EP_PAGES);add_rewrite_endpoint('moodboard',EP_ROOT|EP_PAGES);add_rewrite_endpoint('thong-tin',EP_ROOT|EP_PAGES); }
	public function add_project_account_menu( $items ) { $menu=array('dashboard'=>'Trang tài khoản','du-an'=>'Dự án','moodboard'=>'Moodboard','thong-tin'=>'Thông tin');if(isset($items['customer-logout']))$menu['customer-logout']=$items['customer-logout'];return $menu; }
	public function render_project_account_page() {
		$cart=(function_exists('WC')&&WC()->cart)?WC()->cart:null;$items=$cart?$cart->get_cart():array();$count=0;foreach($items as $entry)$count+=isset($entry['quantity'])?intval($entry['quantity']):0;$cart_url=function_exists('wc_get_cart_url')?wc_get_cart_url():home_url('/cart/');$shop_url=function_exists('wc_get_page_permalink')?wc_get_page_permalink('shop'):home_url('/');
		echo '<div class="cns-account-project"><header class="cns-project-hero"><div><span class="cns-kicker">Your selection</span><h2>Dự án của bạn</h2><p>Danh sách nội thất và vật liệu bạn đang lưu cho không gian của mình.</p></div><div class="cns-project-hero-count"><strong>'.esc_html($count).'</strong><span>SẢN PHẨM</span></div></header>';
		if(!$items){echo '<section class="cns-project-empty"><span class="cns-empty-mark">—</span><h3>Dự án đang trống</h3><p>Khám phá bộ sưu tập và thêm những sản phẩm phù hợp với không gian của bạn.</p><a class="cns-btn cns-btn-dark" href="'.esc_url($shop_url).'">KHÁM PHÁ SẢN PHẨM</a></section>';}else{echo '<section class="cns-project-list"><div class="cns-project-list-head"><span>DANH SÁCH SẢN PHẨM</span><a href="'.esc_url($cart_url).'">CHỈNH SỬA DỰ ÁN <b>→</b></a></div>';foreach($items as $key=>$item){$product=isset($item['data'])?$item['data']:null;if(!$product)continue;$pid=$product->get_id();$image=get_the_post_thumbnail_url($pid,'medium');if(!$image&&function_exists('wc_placeholder_img_src'))$image=wc_placeholder_img_src('medium');$materials=isset($item['cns_selected_materials'])&&is_array($item['cns_selected_materials'])?$item['cns_selected_materials']:array();echo '<article class="cns-project-row"><a class="cns-project-image" href="'.esc_url(get_permalink($pid)).'">'.($image?'<img src="'.esc_url($image).'" alt="'.esc_attr($product->get_name()).'">':'').'</a><div class="cns-project-product"><span class="cns-project-qty">SL '.esc_html(isset($item['quantity'])?intval($item['quantity']):1).'</span><h3><a href="'.esc_url(get_permalink($pid)).'">'.esc_html($product->get_name()).'</a></h3>';if($materials){echo '<div class="cns-project-materials">';foreach($materials as $mat){if(empty($mat['name']))continue;echo '<span>'.(!empty($mat['type'])?esc_html($mat['type']).': ':'').esc_html($mat['name']).(!empty($mat['code'])?' · '.esc_html($mat['code']):'').'</span>';}echo '</div>';}if(!empty($item['cns_product_note']))echo '<p class="cns-project-item-note"><b>GHI CHÚ</b> '.esc_html($item['cns_product_note']).'</p>';echo '</div><a class="cns-project-arrow" href="'.esc_url(get_permalink($pid)).'" aria-label="Xem sản phẩm">↗</a></article>';}echo '</section><footer class="cns-project-actions"><div><span class="cns-kicker">Next step</span><h3>Hoàn thiện yêu cầu<br>báo giá của bạn.</h3></div><div class="cns-project-action-links"><a class="cns-btn cns-btn-dark" href="'.esc_url($cart_url).'">XUẤT YÊU CẦU BÁO GIÁ</a><a class="cns-btn cns-btn-light" href="'.esc_url($cart_url).'">CHỈNH SỬA DANH SÁCH</a></div></footer>';}$mats=$this->get_project_moodboard_materials();if($mats){echo '<section class="cns-project-mood-preview"><div><span class="cns-kicker">Material palette</span><h3>Moodboard</h3></div><a href="'.esc_url(function_exists('wc_get_account_endpoint_url')?wc_get_account_endpoint_url('moodboard'):$cart_url).'">XEM MOODBOARD →</a></section><div class="cns-project-mood-strip">';foreach(array_slice($mats,0,8) as $mat){echo '<div>'.(!empty($mat['image'])?'<img src="'.esc_url($mat['image']).'" alt="'.esc_attr($mat['name']).'">':'<span></span>').'<small>'.esc_html($mat['name']).'</small></div>';}echo '</div>';}echo '</div><style>.cns-account-project{max-width:980px;margin:0 auto;padding:5px 0 20px;color:#1b1b19}.cns-project-hero{display:flex;justify-content:space-between;align-items:flex-end;padding:8px 0 35px;border-bottom:1px solid #dedbd5}.cns-kicker{display:block;font-size:9px;letter-spacing:.18em;text-transform:uppercase;color:#78756f}.cns-project-hero h2{font-size:34px;line-height:1.1;font-weight:500;letter-spacing:-.025em;margin:9px 0}.cns-project-hero p{font-size:14px;line-height:1.6;color:#77736c;margin:0;max-width:455px}.cns-project-hero-count{text-align:right;min-width:92px}.cns-project-hero-count strong{display:block;font-size:40px;font-weight:400;line-height:1}.cns-project-hero-count span{font-size:9px;letter-spacing:.16em;color:#77736c}.cns-project-list{margin-top:30px}.cns-project-list-head{display:flex;justify-content:space-between;align-items:center;padding:0 0 12px;font-size:9px;letter-spacing:.14em;color:#77736c;border-bottom:1px solid #dedbd5}.cns-project-list-head a{font-weight:600;color:#1b1b19;text-decoration:none}.cns-project-list-head b{font-size:15px;margin-left:4px}.cns-project-row{display:grid;grid-template-columns:88px minmax(0,1fr) 20px;gap:16px;align-items:center;padding:12px 0;border-bottom:1px solid #e7e4de}.cns-project-image{display:block;background:#f1eee8;aspect-ratio:1}.cns-project-image img{display:block;width:100%;height:100%;object-fit:cover}.cns-project-qty{font-size:9px;letter-spacing:.14em;color:#7a766e}.cns-project-product h3{font-size:16px;line-height:1.3;font-weight:500;margin:5px 0 9px}.cns-project-product h3 a{color:#1b1b19;text-decoration:none}.cns-project-materials{display:flex;flex-wrap:wrap;gap:6px}.cns-project-materials span{font-size:9px;line-height:1.3;color:#716d66;border:1px solid #e1ddd6;padding:3px 6px}.cns-project-item-note{font-size:11px;color:#77736c;margin:10px 0 0}.cns-project-item-note b{font-size:8px;letter-spacing:.13em;color:#55514b;margin-right:5px}.cns-project-arrow{font-size:20px;color:#1b1b19;text-decoration:none}.cns-project-actions{background:#efede8;margin-top:36px;padding:30px;display:flex;justify-content:space-between;align-items:flex-end;gap:25px}.cns-project-actions h3{font-size:23px;line-height:1.18;font-weight:500;margin:7px 0 0}.cns-project-action-links{display:flex;flex-wrap:wrap;gap:9px}.cns-btn{display:inline-flex;min-height:42px;padding:0 16px;align-items:center;justify-content:center;text-decoration:none!important;font-size:10px;font-weight:600;letter-spacing:.1em;border:1px solid #1b1b19}.cns-btn-dark{background:#1b1b19;color:#fff}.cns-btn-dark:hover{background:#fff;color:#1b1b19}.cns-btn-light{background:transparent;color:#1b1b19}.cns-btn-light:hover{background:#fff}.cns-project-empty{text-align:center;padding:76px 20px;background:#f7f6f3;margin-top:30px}.cns-empty-mark{font-size:32px;color:#b3aea6}.cns-project-empty h3{font-size:24px;font-weight:500;margin:10px 0}.cns-project-empty p{max-width:380px;margin:0 auto 22px;color:#77736c;font-size:13px;line-height:1.6}.cns-project-mood-preview{display:flex;justify-content:space-between;align-items:end;margin-top:45px;padding-bottom:13px;border-bottom:1px solid #dedbd5}.cns-project-mood-preview h3{font-size:24px;font-weight:500;margin:7px 0 0}.cns-project-mood-preview>a{font-size:9px;letter-spacing:.13em;font-weight:600;color:#1b1b19;text-decoration:none}.cns-project-mood-strip{display:grid;grid-template-columns:repeat(8,1fr);gap:8px;margin-top:13px}.cns-project-mood-strip img,.cns-project-mood-strip span{display:block;width:100%;aspect-ratio:1;object-fit:cover;background:#eeeae4}.cns-project-mood-strip small{display:block;font-size:9px;line-height:1.25;margin-top:5px;color:#625f59}@media(max-width:700px){.cns-project-hero{align-items:flex-start;gap:18px}.cns-project-hero h2{font-size:28px}.cns-project-hero-count strong{font-size:30px}.cns-project-row{grid-template-columns:70px minmax(0,1fr) 18px;gap:12px}.cns-project-product h3{font-size:15px}.cns-project-actions{display:block;padding:24px}.cns-project-action-links{margin-top:20px}.cns-project-action-links .cns-btn{width:100%}.cns-project-mood-strip{grid-template-columns:repeat(4,1fr)}}@media(max-width:430px){.cns-project-hero{display:block}.cns-project-hero-count{text-align:left;margin-top:18px}.cns-project-list-head a{font-size:8px}.cns-project-row{grid-template-columns:60px minmax(0,1fr) 15px}.cns-project-materials span{font-size:9px}}</style>';
	}
	private function get_saved_moodboard_ids() { $ids=array();if(function_exists('WC')&&WC()->session)$ids=(array)WC()->session->get('cns_moodboard_ids',array());if(is_user_logged_in())$ids=array_merge($ids,(array)get_user_meta(get_current_user_id(),'cns_moodboard_ids',true));return array_values(array_unique(array_filter(array_map('absint',$ids)))); }
	

public function render_moodboard_account_page() {
    $materials=$this->get_project_moodboard_materials();
    $archive=get_post_type_archive_link(self::CPT_MAT);
    $export=array();
    foreach($materials as $m){
        $type=isset($m['type'])?(string)$m['type']:'';
        $key=function_exists('mb_strtolower')?mb_strtolower($type,'UTF-8'):strtolower($type);
        $kind='plain';
        if(strpos($key,'vải')!==false||strpos($key,'fabric')!==false||strpos($key,'da')!==false||strpos($key,'leather')!==false||strpos($key,'nubuck')!==false){$kind='soft';}
        elseif(strpos($key,'đá')!==false||strpos($key,'stone')!==false||strpos($key,'marble')!==false){$kind='stone';}
        elseif(strpos($key,'kim loại')!==false||strpos($key,'metal')!==false){$kind='metal';}
        elseif(strpos($key,'gỗ')!==false||strpos($key,'wood')!==false){$kind='wood';}
        $export[]=array('name'=>isset($m['name'])?$m['name']:'','type'=>$type,'code'=>isset($m['code'])?$m['code']:'','image'=>isset($m['image'])?$m['image']:'','kind'=>$kind);
    }
    $encoded=base64_encode(wp_json_encode($export));
    echo '<div class="cns-account-moodboard"><header class="cns-account-section-head"><span>Material palette</span><h2>Moodboard của bạn</h2><p>Bảng cảm hứng vật liệu dành cho không gian và dự án của bạn.</p></header>';
    if(!$materials){
        echo '<div class="cns-account-empty"><span>—</span><h3>Moodboard đang trống</h3><p>Lưu các vật liệu yêu thích để tạo bảng cảm hứng riêng.</p><a class="cns-action cns-action-dark" href="'.esc_url($archive).'">KHÁM PHÁ VẬT LIỆU</a></div>';
    }else{
        echo '<section class="cns-mood-listing"><div class="cns-mood-list-head"><span>DANH SÁCH VẬT LIỆU</span><span>'.esc_html(count($materials)).' VẬT LIỆU</span></div><div class="cns-account-mood-grid">';
        foreach($materials as $m){echo '<article class="cns-account-mood-card">'.(!empty($m['image'])?'<img src="'.esc_url($m['image']).'" alt="'.esc_attr($m['name']).'">':'<span></span>').'<div><small>'.esc_html(isset($m['type'])?$m['type']:'').'</small><h3>'.esc_html($m['name']).'</h3>'.(!empty($m['code'])?'<em>'.esc_html($m['code']).'</em>':'').'</div></article>';}
        echo '</div></section><footer class="cns-mood-actions"><div><span class="cns-next-label">NEXT STEP</span><h3>Lưu lại bảng cảm hứng<br>cho dự án của bạn.</h3></div><div class="cns-mood-action-buttons"><button type="button" class="cns-action cns-action-dark cns-export-moodboard" data-materials="'.esc_attr($encoded).'">XUẤT MOODBOARD</button><button type="button" class="cns-action cns-action-light cns-request-sample" title="Tính năng sẽ sớm được kết nối">YÊU CẦU SAMPLE</button></div></footer>';
    }
    echo '</div><style>.cns-account-moodboard{max-width:980px;margin:0 auto;color:#1b1b19}.woocommerce-MyAccount-navigation-link--orders,.woocommerce-MyAccount-navigation-link--downloads{display:none!important}.cns-account-section-head{padding:5px 0 27px;border-bottom:1px solid #dedbd5;margin-bottom:28px}.cns-account-section-head>span{display:block;font-size:9px;letter-spacing:.18em;text-transform:uppercase;color:#77736c}.cns-account-section-head h2{font-size:32px;line-height:1.1;font-weight:500;letter-spacing:-.025em;margin:8px 0}.cns-account-section-head p{font-size:13px;color:#777;margin:0}.cns-mood-list-head{display:flex;justify-content:space-between;padding:0 0 11px;font-size:9px;letter-spacing:.14em;color:#77736c;border-bottom:1px solid #dedbd5}.cns-account-mood-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;padding-top:14px}.cns-account-mood-card{border:1px solid #e3dfd8;background:#fff}.cns-account-mood-card>img,.cns-account-mood-card>span{display:block;width:100%;aspect-ratio:1;object-fit:cover;background:#eeeae4}.cns-account-mood-card>div{padding:10px}.cns-account-mood-card small{font-size:8px;letter-spacing:.12em;text-transform:uppercase;color:#888}.cns-account-mood-card h3{font-size:12px;line-height:1.25;font-weight:500;margin:4px 0}.cns-account-mood-card em{font-size:10px;color:#777;font-style:normal}.cns-mood-actions{display:flex;justify-content:space-between;align-items:flex-end;gap:25px;margin-top:36px;padding:30px;background:#efede8}.cns-next-label{display:block;font-size:9px;letter-spacing:.18em;color:#77736c}.cns-mood-actions h3{font-size:23px;line-height:1.18;font-weight:500;margin:7px 0 0}.cns-mood-action-buttons{display:flex;flex-wrap:wrap;gap:9px}.cns-action{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border:1px solid #1b1b19;font:600 10px/1 inherit;letter-spacing:.1em;text-decoration:none!important;cursor:pointer}.cns-action-dark{background:#1b1b19;color:#fff}.cns-action-dark:hover{background:#fff;color:#1b1b19}.cns-action-light{background:transparent;color:#1b1b19}.cns-action-light:hover{background:#fff}.cns-account-empty{text-align:center;padding:76px 20px;background:#f7f6f3}.cns-account-empty>span{font-size:32px;color:#b3aea6}.cns-account-empty h3{font-size:24px;font-weight:500;margin:10px 0}.cns-account-empty p{max-width:380px;margin:0 auto 22px;color:#77736c;font-size:13px;line-height:1.6}@media(max-width:700px){.cns-account-mood-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.cns-mood-actions{display:block;padding:24px}.cns-mood-action-buttons{margin-top:20px}.cns-action{width:100%}}@media(max-width:430px){.cns-account-mood-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.cns-account-section-head h2{font-size:28px}}</style>';
}


public function moodboard_export_script() {
    if(!function_exists('is_account_page')||!is_account_page()||!function_exists('is_wc_endpoint_url')||!is_wc_endpoint_url('moodboard')){return;}
    ?>
<script>(function(){var b=document.querySelector('.cns-export-moodboard');if(!b)return;function esc(v){var d=document.createElement('div');d.textContent=v||'';return d.innerHTML}function build(){var items=[];try{var raw=atob(b.dataset.materials||'');var bytes=Uint8Array.from(raw,function(c){return c.charCodeAt(0)});items=JSON.parse(new TextDecoder().decode(bytes))}catch(e){items=[]}if(!items.length)return '';var slots=[[4,5,-2],[51,4,1.5],[20,36,-1],[62,35,2],[37,18,.7],[3,62,1.2],[69,64,-1.6],[40,65,2.2],[13,20,1],[55,20,-2],[25,66,.5],[72,12,1.3]];var cards=items.map(function(m,i){var p=slots[i%slots.length],size=m.kind==='soft'?32:(m.kind==='stone'?27:23),shadow=m.kind==='soft'?'0 7px 14px rgba(35,31,26,.15)':(m.kind==='stone'?'0 17px 28px rgba(35,31,26,.28),0 4px 7px rgba(35,31,26,.16)':'none');return '<figure class="sample" style="left:'+p[0]+'%;top:'+p[1]+'%;width:'+size+'%;transform:rotate('+p[2]+'deg);box-shadow:'+shadow+'">'+(m.image?'<img src="'+esc(m.image)+'">':'<span></span>')+'</figure>'}).join('');var listing=items.map(function(m){return '<article><div class="thumb">'+(m.image?'<img src="'+esc(m.image)+'">':'')+'</div><small>'+esc(m.type)+'</small><b>'+esc(m.name)+'</b>'+(m.code?'<em>'+esc(m.code)+'</em>':'')+'</article>'}).join('');return '<!doctype html><html><head><meta charset="utf-8"><title>Moodboard COLECTIA</title><style>@page{size:A4;margin:12mm}*{box-sizing:border-box}html,body{margin:0;background:#fff;font-family:Arial,Helvetica,sans-serif;color:#1b1b19}.head{display:flex;justify-content:space-between;align-items:flex-end;padding-bottom:8mm;border-bottom:1px solid #d8d4cd}.eyebrow{font-size:8px;letter-spacing:.18em;color:#777}.title{font-size:25px;font-weight:500;margin:7px 0 0}.date{font-size:9px;color:#777}.visual{position:relative;height:158mm;margin-top:8mm;background:#fff;overflow:hidden}.sample{position:absolute;margin:0;background:#fff}.sample img,.sample span{display:block;width:100%;aspect-ratio:1;object-fit:cover;background:#f4f2ee}.list-head{display:flex;justify-content:space-between;padding:0 0 3mm;border-bottom:1px solid #d8d4cd;font-size:7px;letter-spacing:.14em;color:#777}.listing{display:grid;grid-template-columns:repeat(4,1fr);gap:4mm;margin-top:4mm}.listing article{break-inside:avoid}.thumb{width:100%;aspect-ratio:1;background:#f3f1ed;margin-bottom:2mm}.thumb img{width:100%;height:100%;object-fit:cover}.listing small,.listing b,.listing em{display:block}.listing small{font-size:6px;letter-spacing:.1em;text-transform:uppercase;color:#888}.listing b{font-size:8px;font-weight:500;margin-top:1mm}.listing em{font-size:7px;color:#777;font-style:normal;margin-top:.5mm}.foot{margin-top:8mm;padding-top:4mm;border-top:1px solid #d8d4cd;font-size:7px;color:#777}@media print{html,body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}</style></head><body><header class="head"><div><span class="eyebrow">COLECTIA · MATERIAL PALETTE</span><h1 class="title">MOODBOARD</h1></div><span class="date">'+new Date().toLocaleDateString('vi-VN')+'</span></header><section class="visual">'+cards+'</section><div class="list-head"><span>DANH SÁCH VẬT LIỆU</span><span>'+items.length+' VẬT LIỆU</span></div><section class="listing">'+listing+'</section><footer class="foot">COLECTIA VIETNAM · colectia.vn</footer></body></html>'}
function waitImages(doc,cb){var imgs=doc.images?Array.prototype.slice.call(doc.images):[];var left=imgs.length;if(!left){cb();return}var done=false;function fin(){if(done)return;done=true;cb()}imgs.forEach(function(im){if(im.complete){if(!--left)fin();return}im.addEventListener('load',function(){if(!--left)fin()});im.addEventListener('error',function(){if(!--left)fin()})});setTimeout(fin,2500)}
function printViaIframe(html){var old=document.getElementById('cns-print-frame');if(old)old.parentNode.removeChild(old);var f=document.createElement('iframe');f.id='cns-print-frame';f.setAttribute('aria-hidden','true');f.style.cssText='position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';document.body.appendChild(f);var d=f.contentWindow.document;d.open();d.write(html);d.close();waitImages(d,function(){try{f.contentWindow.focus();f.contentWindow.print()}catch(e){printViaPopup(html)}})}
function printViaPopup(html){var w=window.open('','_blank');if(!w){alert('Vui lòng cho phép cửa sổ bật lên (popup) để xuất Moodboard, sau đó thử lại.');return}w.document.open();w.document.write(html);w.document.close();waitImages(w.document,function(){try{w.focus();w.print()}catch(e){}})}
b.addEventListener('click',function(){var w=window.open('','_blank');if(!w){alert('Trình duyệt đang chặn cửa sổ bản in. Vui lòng cho phép popup cho colectia.vn rồi thử lại.');return}var html=build();if(!html){w.close();return}w.document.open();w.document.write(html);w.document.close();var printed=false;function go(){if(printed)return;printed=true;try{w.focus();w.print()}catch(e){}}w.onload=go;setTimeout(go,900)})})();</script>
<?php
}

public function render_information_account_page() { echo '<div class="cns-account-information"><section class="cns-account-profile"><h2>Thông tin tài khoản</h2>';wc_get_template('myaccount/form-edit-account.php',array('user'=>get_user_by('id',get_current_user_id())));echo '</section><section class="cns-account-addresses"><h2>Địa chỉ</h2>';wc_get_template('myaccount/my-address.php',array('customer_id'=>get_current_user_id()));echo '</section></div><style>.cns-account-information>section+section{margin-top:42px;padding-top:34px;border-top:1px solid #dedbd5}.cns-account-information>section>h2{font-size:22px;font-weight:500;margin:0 0 22px}</style>'; }

	public function configure_project_cart() { remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 ); add_action( 'woocommerce_proceed_to_checkout', array( $this, 'project_quote_button' ), 20 ); }

	public function project_cart_live_script() { if(!function_exists('is_cart')||!is_cart())return;?><script>(function(){function clean(){document.querySelectorAll('.cart_totals h2,#cart-totals h2,.cart-collaterals h2').forEach(function(h){if((h.textContent||'').trim().toLowerCase().indexOf('tổng cộng giỏ hàng')!==-1)h.remove()})}function sync(){clean();document.querySelectorAll('.cns-project-overview li[data-cat]').forEach(function(row){var slug=row.dataset.cat,total=0;document.querySelectorAll('.cart_item.cns-project-cat-'+CSS.escape(slug)+' input.qty').forEach(function(q){total+=parseInt(q.value||0,10)||0});var out=row.querySelector('strong');if(out&&total)out.textContent=total})}document.addEventListener('DOMContentLoaded',sync);document.addEventListener('input',function(e){if(e.target.matches('.cart_item input.qty'))sync()});if(window.jQuery)jQuery(document.body).on('updated_wc_div',sync)})();</script><?php }

	public function project_cart_styles() { if ( ! function_exists('is_cart') || ! is_cart() ) { return; } echo '<style>.woocommerce-cart-form .product-price,.woocommerce-cart-form .product-subtotal,.cart-collaterals .cart-subtotal,.cart-collaterals .order-total{display:none!important}.wd-checkout-steps{display:block!important;list-style:none!important;margin:0 0 36px!important;padding:0!important;text-align:center}.wd-checkout-steps li{display:none!important}.wd-checkout-steps{background:#232323!important;padding:34px 0!important}.wd-checkout-steps:after{content:"PRODUCT LIST";display:block;font-size:22px;font-weight:500;letter-spacing:.2em;color:#fff!important}.cart_totals>h2,.cart_totals .shop_table,.cart_totals table{display:none!important}.cns-project-overview{margin:0 0 25px}.cns-project-overview h2{display:block!important;font-size:13px!important;letter-spacing:.12em!important;text-transform:uppercase!important;margin:0 0 15px!important}.cns-project-overview ul{list-style:none;margin:0;padding:0;border-top:1px solid #d9d5cf}.cns-project-overview li{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #d9d5cf;font-size:13px}.cns-project-overview strong{font-size:14px;font-weight:600}.woocommerce-cart-form{border-top:1px solid #e4e0da}.woocommerce-cart-form__cart-item td{padding:24px 12px!important;border-bottom:1px solid #e4e0da}.woocommerce-cart-form__cart-item .product-name a{font-size:16px;font-weight:500;color:#1d1d1b}.woocommerce-cart-form__cart-item .variation{margin-top:9px;font-size:12px;color:#777}.woocommerce-cart-form__cart-item .quantity input{width:58px!important;height:34px!important;border:1px solid #d9d5cf!important;font:12px inherit!important}.woocommerce-cart-form__cart-item .product-quantity{display:table-cell!important}.cns-item-note{margin-top:13px}.cns-item-note label{display:block;font-size:9px;letter-spacing:.1em;text-transform:uppercase;color:#777;margin-bottom:5px}.cns-item-note textarea{width:100%;min-height:64px;padding:9px 10px;border:1px solid #ddd8d0;background:#fff;font:12px/1.5 inherit;resize:vertical}.woocommerce-cart-form__cart-item .product-quantity:before{content:"Số lượng";display:block;font-size:9px;letter-spacing:.1em;text-transform:uppercase;color:#777;margin-bottom:5px}.cart-collaterals{background:#f5f3ef;padding:28px}.cart-collaterals .cart_totals>h2,.cart-collaterals .cart_totals>table{display:none!important}.cns-project-note{margin:28px 0 4px;padding:22px;border:1px solid #e3dfd8;background:#faf9f7}.cns-project-note label{display:block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;margin-bottom:9px}.cns-project-note textarea{display:block;width:100%;min-height:100px;padding:12px;border:1px solid #d9d5cf;background:#fff;font:13px/1.6 inherit;resize:vertical}.cns-project-quote{background:#1b1b19!important;border:1px solid #1b1b19!important;color:#fff!important;padding:15px 20px!important;font-size:12px!important;letter-spacing:.08em;text-transform:uppercase}.cns-project-quote:hover{background:transparent!important;color:#1b1b19!important}.cart-collaterals .cns-project-moodboard{margin:0 0 20px;padding:22px;border:1px solid #e3dfd8;background:#faf9f7}.cns-moodboard-head span{font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:#777}.cns-moodboard-head h2{font-size:20px;font-weight:500;margin:5px 0 20px}.cns-moodboard-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:14px}.cns-mood-card{background:#fff;border:1px solid #e2ded7}.cns-mood-card>img,.cns-mood-placeholder{display:block;width:100%;aspect-ratio:1;object-fit:cover;background:#e9e6df}.cns-mood-card>div{padding:10px}.cns-mood-card small,.cns-mood-card strong,.cns-mood-card em{display:block}.cns-mood-card small{font-size:9px;letter-spacing:.1em;text-transform:uppercase;color:#777}.cns-mood-card strong{font-size:12px;line-height:1.4;margin-top:3px}.cns-mood-card em{font-size:10px;color:#777;font-style:normal;margin-top:3px}.cns-moodboard-empty{font-size:13px;color:#777}</style>'; }

	public function render_project_note() { $note=(function_exists('WC')&&WC()->session)?WC()->session->get('cns_project_note',''):''; echo '<div class="cns-project-note"><label for="cns_project_note">Ghi chú cho dự án</label><textarea id="cns_project_note" name="cns_project_note" placeholder="Chia sẻ yêu cầu, không gian sử dụng hoặc thời gian dự kiến...">'.esc_textarea($note).'</textarea></div>'; }

	public function save_project_note() { if(!function_exists('WC')||!WC()->session)return;if(isset($_POST['cns_project_note']))WC()->session->set('cns_project_note',sanitize_textarea_field(wp_unslash($_POST['cns_project_note'])));if(isset($_POST['cns_item_note'])&&is_array($_POST['cns_item_note'])&&WC()->cart){foreach(wp_unslash($_POST['cns_item_note']) as $key=>$note){if(isset(WC()->cart->cart_contents[$key]))WC()->cart->cart_contents[$key]['cns_product_note']=sanitize_textarea_field($note);}WC()->cart->set_session();} }

	private function get_project_moodboard_materials() {
		$all=array();if(function_exists('WC')&&WC()->cart){foreach(WC()->cart->get_cart() as $item){foreach((array)(isset($item['cns_selected_materials'])?$item['cns_selected_materials']:array()) as $m){if(empty($m['name']))continue;$all[]=array('name'=>$m['name'],'type'=>isset($m['type'])?$m['type']:'','code'=>isset($m['code'])?$m['code']:'','image'=>isset($m['image'])?$m['image']:'');}}}
		$ids=$this->get_saved_moodboard_ids();foreach($ids as $id){if(self::CPT_MAT!==get_post_type($id))continue;$types=wp_get_object_terms($id,self::TAX_MAT_TYPE,array('fields'=>'names'));$all[]=array('name'=>get_the_title($id),'type'=>(!is_wp_error($types)&&$types)?$types[0]:'','code'=>get_post_meta($id,'_cns_mat_code',true),'image'=>get_the_post_thumbnail_url($id,'medium'));}
		$out=array();foreach($all as $m){$key=sanitize_title($m['name'].'-'.$m['code']);$out[$key]=$m;}return array_values($out);
	}
	public function render_project_moodboard() { $materials=$this->get_project_moodboard_materials();echo '<section class="cns-project-moodboard"><div class="cns-moodboard-head"><span>Material palette</span><h2>Moodboard</h2></div>';if(!$materials){echo '<p class="cns-moodboard-empty">Chưa có vật liệu trong Moodboard.</p>';}else{echo '<div class="cns-moodboard-grid">';foreach($materials as $m){echo '<article class="cns-mood-card">'.(!empty($m['image'])?'<img src="'.esc_url($m['image']).'" alt="'.esc_attr($m['name']).'">':'<span class="cns-mood-placeholder"></span>').'<div><small>'.esc_html($m['type']).'</small><strong>'.esc_html($m['name']).'</strong>'.(!empty($m['code'])?'<em>'.esc_html($m['code']).'</em>':'').'</div></article>';}echo '</div>';}echo '</section>'; }
	private function material_moodboard_action( $id ) { $added=in_array(absint($id),$this->get_saved_moodboard_ids(),true);$mood_url=function_exists('wc_get_account_endpoint_url')?wc_get_account_endpoint_url('moodboard'):wc_get_page_permalink('myaccount');return '<div class="cns-material-moodboard-action"><span class="cns-moodboard-label">Lưu vào bảng cảm hứng</span><div class="cns-moodboard-buttons"><button type="button" class="cns-add-moodboard'.($added?' is-added':'').'" data-material="'.esc_attr($id).'">'.($added?'ĐÃ CÓ TRONG MOODBOARD':'THÊM VÀO MOODBOARD').'</button><a class="cns-go-moodboard" href="'.esc_url($mood_url).'">TỚI MOODBOARD</a></div></div>'; }
	public function material_moodboard_script() { if(!is_singular(self::CPT_MAT))return;$nonce=wp_create_nonce('cns_moodboard');?><style>.cns-mat-hero-info .cns-material-moodboard-action{margin:28px 0 0;padding-top:20px;border-top:1px solid #e8e5df}.cns-moodboard-label{display:block;margin-bottom:9px;font-size:9px;letter-spacing:.15em;text-transform:uppercase;color:#7c7871}.cns-moodboard-buttons{display:flex;align-items:stretch;gap:8px}.cns-add-moodboard,.cns-go-moodboard{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 15px;border:1px solid #1b1b19;font:600 10px/1.2 inherit;letter-spacing:.09em;text-decoration:none!important;cursor:pointer}.cns-add-moodboard{background:#1b1b19;color:#fff}.cns-add-moodboard:hover,.cns-add-moodboard.is-added{background:#fff;color:#1b1b19}.cns-go-moodboard{background:#fff;color:#1b1b19}.cns-go-moodboard:hover{background:#1b1b19;color:#fff}@media(max-width:575px){.cns-moodboard-buttons{flex-direction:column}.cns-add-moodboard,.cns-go-moodboard{width:100%}}</style><script>(function(){var b=document.querySelector('.cns-add-moodboard');if(!b)return;b.addEventListener('click',function(){if(b.classList.contains('is-added'))return;var d=new URLSearchParams({action:'cns_add_moodboard',nonce:'<?php echo esc_js($nonce); ?>',material_id:b.dataset.material});fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:d.toString()}).then(function(r){return r.json()}).then(function(r){if(r.success){b.classList.add('is-added');b.textContent='ĐÃ CÓ TRONG MOODBOARD'}})})})();</script><?php }
	public function ajax_add_moodboard() { check_ajax_referer('cns_moodboard','nonce');$id=isset($_POST['material_id'])?absint($_POST['material_id']):0;if(!$id||self::CPT_MAT!==get_post_type($id))wp_send_json_error();if(!function_exists('WC')||!WC()->session)wp_send_json_error();$ids=$this->get_saved_moodboard_ids();$ids[]=$id;$ids=array_values(array_unique(array_map('absint',$ids)));WC()->session->set('cns_moodboard_ids',$ids);if(is_user_logged_in())update_user_meta(get_current_user_id(),'cns_moodboard_ids',$ids);wp_send_json_success(array('url'=>wc_get_account_endpoint_url('moodboard'))); }

	public function render_project_overview() {
		if(!function_exists('WC')||!WC()->cart)return;$groups=array();foreach(WC()->cart->get_cart() as $cart_item){$product=isset($cart_item['data'])?$cart_item['data']:null;if(!$product)continue;$term=$this->project_category_term($product->get_id());$slug=$term?$term->slug:'other';$label=$term?$term->name:'Sản phẩm khác';if(!isset($groups[$slug]))$groups[$slug]=array('label'=>$label,'qty'=>0);$groups[$slug]['qty']+=intval($cart_item['quantity']);}if(!$groups)return;uasort($groups,function($a,$b){return strnatcasecmp($a['label'],$b['label']);});echo '<section class="cns-project-overview"><h2>Tổng quan dự án</h2><ul>';foreach($groups as $slug=>$group){echo '<li data-cat="'.esc_attr($slug).'"><span>'.esc_html($group['label']).'</span><strong>'.esc_html($group['qty']).'</strong></li>';}echo '</ul></section>';
	}


	public function project_quote_button() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) { return; } $items=array();
		foreach ( WC()->cart->get_cart() as $cart_item ) { $product=isset($cart_item['data'])?$cart_item['data']:null;if(!$product)continue;$pid=$product->get_id();$image=get_the_post_thumbnail_url($pid,'medium');if(!$image)$image=wc_placeholder_img_src('medium');$items[]=array('name'=>$product->get_name(),'image'=>$image,'qty'=>intval($cart_item['quantity']),'note'=>isset($cart_item['cns_product_note'])?$cart_item['cns_product_note']:'','materials'=>(!empty($cart_item['cns_selected_materials'])&&is_array($cart_item['cns_selected_materials']))?$cart_item['cns_selected_materials']:array()); }
		if(!$items)return;$note=WC()->session?WC()->session->get('cns_project_note',''):'';
		$uname='';$uemail='';if(is_user_logged_in()){$u=wp_get_current_user();$uname=trim($u->first_name.' '.$u->last_name);if(!$uname)$uname=$u->display_name;$uemail=$u->user_email;}
		echo '<a href="#" class="checkout-button button alt cns-project-quote" data-project="'.esc_attr(wp_json_encode($items)).'" data-note="'.esc_attr($note).'" data-uname="'.esc_attr($uname).'" data-uemail="'.esc_attr($uemail).'">Xuất yêu cầu báo giá</a>';
		echo '<script>(function(){var b=document.querySelector(".cns-project-quote");if(!b)return;b.addEventListener("click",function(e){e.preventDefault();var note=document.querySelector("#cns_project_note"),items=JSON.parse(b.dataset.project||"[]"),projectNote=note?note.value:b.dataset.note||"",uname=b.dataset.uname,uemail=b.dataset.uemail,esc=function(v){var d=document.createElement("div");d.textContent=v||"";return d.innerHTML},allMats={};var content=items.map(function(item,i){var mats=(item.materials||[]).map(function(m){var k=(m.name||"")+"-"+(m.code||"");allMats[k]=m;return `<li class="mat-pill">${m.image?`<img src="${esc(m.image)}">`:""}<div><small>${esc(m.type||"Vật liệu")}</small><br><strong>${esc(m.name)}</strong>${m.code?` <i>${esc(m.code)}</i>`:""}</div></li>`}).join("");return `<article class="item"><div class="item-head"><span class="num">${String(i+1).padStart(2,"0")}</span><img class="prod-img" src="${esc(item.image)}"><div class="prod-meta"><h2>${esc(item.name)}</h2><span class="qty">Số lượng: ${item.qty||1}</span></div></div>${mats?`<ul class="item-mats">${mats}</ul>`:""}${item.note?`<div class="item-note"><b>Ghi chú:</b> ${esc(item.note).replace(/\n/g,"<br>")}</div>`:""}</article>`}).join(""),noteBlock=projectNote?`<section class="note"><h3>Ghi chú dự án</h3><p>${esc(projectNote).replace(/\n/g,"<br>")}</p></section>`:"",moodKeys=Object.keys(allMats),moodboardBlock="";if(moodKeys.length){moodboardBlock=`<section class="moodboard"><h3>Moodboard vật liệu</h3><div class="mood-grid">${moodKeys.map(function(k){var m=allMats[k];return `<div class="mood-card">${m.image?`<img src="${esc(m.image)}">`:`<span class="ph"></span>`}<div><b>${esc(m.type)}</b><br>${esc(m.name)}${m.code?`<br><i>${esc(m.code)}</i>`:""}</div></div>`}).join("")}</div></section>`}var userBlock="";if(uname||uemail){userBlock=`<div class="user-info">${uname?`<b>${esc(uname)}</b><br>`:""}${uemail?`<span>${esc(uemail)}</span>`:""}</div>`}var w=window.open("","_blank");if(!w)return;w.document.write(`<!doctype html><html><head><title>Yêu cầu báo giá dự án COLECTIA</title><style>@page{size:A4;margin:0}*{box-sizing:border-box}body{margin:0;background:#e9e6df;font-family:"Helvetica Neue",Arial,Helvetica,sans-serif;color:#171717;line-height:1.4}.sheet{width:210mm;min-height:297mm;background:#fff;margin:auto;padding:16mm 20mm 15mm}.head{position:relative;padding-bottom:12mm;border-bottom:1px solid #d9d5cf;display:flex;justify-content:space-between;align-items:flex-end}.head-left{flex:1}.logo{display:block;width:126px;height:29px;object-fit:contain;object-position:left;margin-bottom:12px}.eyebrow{font-size:8px;letter-spacing:.24em;color:#777;text-transform:uppercase;margin:0 0 6px}.title{font-size:24px;letter-spacing:.1em;font-weight:500;margin:0}.head-right{text-align:right}.date{font-size:9px;color:#777;line-height:1.6;margin-bottom:14px}.user-info{font-size:10px;line-height:1.5;color:#171717;text-align:left;display:inline-block}.user-info b{font-weight:600}.item{padding:8mm 0;border-bottom:1px solid #e8e6e1;break-inside:avoid}.item-head{display:flex;align-items:center;gap:15px}.num{font-size:9px;letter-spacing:.12em;color:#888;min-width:14px}.prod-img{width:64px;height:64px;object-fit:cover;background:#f9f9f9}.prod-meta{flex:1}.prod-meta h2{font-size:16px;line-height:1.3;font-weight:500;margin:0 0 4px}.prod-meta .qty{font-size:10px;color:#555;padding:3px 0;display:inline-block}.item-mats{margin:10px 0 0 93px;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:10px}.item-mats .mat-pill{display:flex;align-items:center;gap:8px;font-size:9px;line-height:1.2;color:#333;background:#faf9f7;padding:5px 12px 5px 5px;border-radius:30px;border:1px solid #f1f1f1}.mat-pill img{width:24px;height:24px;border-radius:50%;object-fit:cover;border:1px solid #e1e1e1}.mat-pill small{font-size:7px;letter-spacing:.1em;text-transform:uppercase;color:#888}.mat-pill strong{font-size:9px;font-weight:500}.mat-pill i{font-style:normal;color:#777}.item-note{margin:10px 0 0 93px;font-size:10px;line-height:1.5;color:#555}.item-note b{font-size:8px;letter-spacing:.1em;text-transform:uppercase;color:#777;margin-right:4px}.moodboard{margin-top:14mm;padding-top:10mm;border-top:1px solid #d9d5cf;break-inside:avoid}.moodboard h3,.note h3{font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:#777;margin:0 0 12px}.mood-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.mood-card{font-size:9px;line-height:1.4}.mood-card img,.mood-card .ph{display:block;width:100%;aspect-ratio:1;object-fit:cover;background:#f9f9f9;margin-bottom:8px;border:1px solid #f1f1f1}.mood-card b{font-size:7px;letter-spacing:.1em;text-transform:uppercase;color:#888}.mood-card i{font-style:normal;color:#777}.note{margin-top:12mm;padding:8mm 10mm;background:#faf9f7;break-inside:avoid;border:1px solid #f1f1f1}.note p{font-size:10px;line-height:1.7;margin:0;color:#333}.foot{margin-top:14mm;border-top:1px solid #d9d5cf;padding-top:6mm;display:flex;justify-content:space-between;font-size:8px;line-height:1.6;letter-spacing:.02em;color:#777}@media print{body{background:#fff}.sheet{margin:0}}</style></head><body><main class="sheet"><header class="head"><div class="head-left"><img class="logo" src="https://colectia.vn/wp-content/uploads/2025/10/Logo.svg"><p class="eyebrow">Project selection</p><h1 class="title">YÊU CẦU BÁO GIÁ</h1></div><div class="head-right"><div class="date">Ngày xuất: <b>${new Date().toLocaleDateString("vi-VN")}</b></div>${userBlock}</div></header>${content}${moodboardBlock}${noteBlock}<footer class="foot"><span>COLECTIA VIETNAM<br>colectia.vn</span><span>Danh sách lựa chọn cho dự án của bạn.<br>Vui lòng liên hệ để nhận báo giá chính thức.</span></footer></main></body></html>`);w.document.close();w.onload=function(){w.focus();w.print()}})})();</script>';
	}


	private function type_vn( $type ) {
		$t   = mb_strtoupper( trim( (string) $type ) );
		$map = array(
			'FABRIC'         => 'Vải',
			'NATURAL STONE'  => 'Đá',
			'LEATHER'        => 'Da',
			'NUBUCK'         => 'Da',
			'COWHIDE'        => 'Da',
			'METAL'          => 'Kim loại',
			'WOOD'           => 'Gỗ',
			'LACQUER FINISH' => 'Gỗ',
		);
		return isset( $map[ $t ] ) ? $map[ $t ] : (string) $type;
	}

	private function find_material_post( $notion_id, $name ) {
		$q = get_posts( array(
			'post_type'   => self::CPT_MAT,
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_notion_page_id',
			'meta_value'  => $notion_id,
		) );
		if ( $q ) { return intval( $q[0] ); }
		$byt = get_page_by_title( $name, OBJECT, self::CPT_MAT );
		return $byt ? intval( $byt->ID ) : 0;
	}

	private function sync_material_posts( $pages ) {
		foreach ( $pages as $p ) {
			$props = isset( $p['properties'] ) ? $p['properties'] : array();
			$name  = $this->title_of( $props );
			if ( '' === $name ) { continue; }

			$type_p  = $this->prop( $props, 'MATERIAL TYPE' );
			$type    = isset( $type_p['select']['name'] ) ? $type_p['select']['name'] : '';
			$type    = $this->type_vn( $type );
			$color_p = $this->prop( $props, 'Màu sắc' );
			$color   = isset( $color_p['select']['name'] ) ? $color_p['select']['name'] : '';
			$code_p  = $this->prop( $props, 'CODE' );
			$code    = isset( $code_p['formula']['string'] ) ? $code_p['formula']['string'] : '';
			$fac_p   = $this->prop( $props, 'FACTORY CODE' );
			$factory = isset( $fac_p['rich_text'] ) ? trim( $this->plain_text( $fac_p['rich_text'] ) ) : '';
			// Bộ sưu tập vật liệu là relation tới database riêng để có ảnh đại diện và mô tả.
			$bsts = $this->relation_titles( $this->prop( $props, 'Bộ sưu tập vật liệu' ) );
			$bst  = $bsts ? implode( ', ', $bsts ) : '';
			// Giữ tương thích dữ liệu select cũ trong lúc chuyển đổi.
			if ( '' === $bst ) {
				$bst_p = $this->prop( $props, 'Bộ sưu tập vật liệu (cũ)' );
				$bst = isset( $bst_p['select']['name'] ) ? $bst_p['select']['name'] : '';
			}

			$post_id = $this->find_material_post( $p['id'], $name );
			$data    = array(
				'post_type'    => self::CPT_MAT,
				'post_status'  => 'publish',
				'post_title'   => $name,
				'post_content' => $this->get_page_content_html( $p['id'] ),
				'post_excerpt' => implode( ' • ', array_filter( array( $type, $color, $factory ) ) ),
			);
			if ( $post_id ) {
				$data['ID'] = $post_id;
				$res = wp_update_post( $data, true );
			} else {
				$res = wp_insert_post( $data, true );
			}
			if ( is_wp_error( $res ) || ! $res ) {
				$this->log[] = 'LỖI: không lưu được vật liệu "' . $name . '"';
				continue;
			}
			$post_id = intval( $res );

			update_post_meta( $post_id, '_notion_page_id', $p['id'] );
			update_post_meta( $post_id, '_notion_sync_ver', self::PLUGIN_VERSION );
			if ( isset( $p['last_edited_time'] ) ) { update_post_meta( $post_id, '_notion_last_edited', $p['last_edited_time'] ); }
			update_post_meta( $post_id, '_cns_mat_color', $color );
			update_post_meta( $post_id, '_cns_mat_code', $code );
			update_post_meta( $post_id, '_cns_mat_factory', $factory );
			if ( '' !== $type ) { wp_set_object_terms( $post_id, $type, self::TAX_MAT_TYPE ); }
			wp_set_object_terms( $post_id, '' !== $bst ? $bst : array(), self::TAX_MAT_COL );

			$cols = $this->relation_titles( $this->prop( $props, 'Collection' ) );
			if ( $cols ) { update_post_meta( $post_id, '_cns_mat_collections', $cols ); }

			$thumb = $this->prop( $props, 'Thumbnail' );
			$furl  = '';
			if ( ! empty( $thumb['files'][0] ) ) {
				$f    = $thumb['files'][0];
				$furl = isset( $f['file']['url'] ) ? $f['file']['url'] : ( isset( $f['external']['url'] ) ? $f['external']['url'] : '' );
			}
			if ( $furl && ! has_post_thumbnail( $post_id ) ) {
				$clean = strtok( $furl, '?' );
				$ext   = pathinfo( $clean, PATHINFO_EXTENSION );
				$ext   = $ext ? $ext : 'jpg';
				$aid   = $this->sideload( $furl, sanitize_title( $name ) . '.' . $ext, $post_id );
				if ( $aid ) { set_post_thumbnail( $post_id, $aid ); }
			}

			$this->log[] = '  → vật liệu "' . $name . '" (#' . $post_id . ')';
			$this->notion_request( 'PATCH', '/pages/' . $p['id'], array(
				'properties' => array(
					self::P_WPID    => array( 'number' => $post_id ),
					self::P_WPLINK  => array( 'url' => get_permalink( $post_id ) ),
					self::P_SYNCWEB => array( 'status' => array( 'name' => self::S_DONE ) ),
				),
			) );
		}
	}

	/* ---------------- 1.8.0: lấy vật liệu liên kết cho tab sản phẩm ---------------- */

	public function get_linked_materials( $prod_id ) {
		$list = get_post_meta( $prod_id, '_notion_materials', true );
		if ( ! is_array( $list ) || ! $list ) { return array(); }
		$out = array();
		foreach ( $list as $m ) {
			$nid  = isset( $m['nid'] ) ? $m['nid'] : '';
			$name = isset( $m['name'] ) ? $m['name'] : '';
			$post_id = $nid ? $this->find_material_post( $nid, $name ) : $this->find_material_post( 'x', $name );
			if ( $post_id ) {
				$m['url']   = get_permalink( $post_id );
				$thumb      = get_the_post_thumbnail_url( $post_id, 'medium' );
				if ( $thumb ) { $m['thumb'] = $thumb; }
				if ( empty( $m['code'] ) ) { $m['code'] = get_post_meta( $post_id, '_cns_mat_code', true ); }
				if ( empty( $m['color'] ) ) { $m['color'] = get_post_meta( $post_id, '_cns_mat_color', true ); }
				if ( empty( $m['type'] ) ) {
					$terms = wp_get_object_terms( $post_id, self::TAX_MAT_TYPE, array( 'fields' => 'names' ) );
					if ( ! is_wp_error( $terms ) && $terms ) { $m['type'] = $terms[0]; }
				}
			}
			$m['type']  = isset( $m['type'] ) ? $m['type'] : '';
			$m['color'] = isset( $m['color'] ) ? $m['color'] : '';
			$m['code']  = isset( $m['code'] ) ? $m['code'] : '';
			$m['thumb'] = isset( $m['thumb'] ) ? $m['thumb'] : '';
			$m['url']   = isset( $m['url'] ) ? $m['url'] : '';
			$out[] = $m;
		}
		return $out;
	}

	/* ---------------- 1.8.0: shortcode lưới vật liệu ---------------- */

	public function materials_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'type' => '', 'collection' => '', 'limit' => '-1', 'columns' => '6', 'group' => 'no', 'style' => 'wd' ), $atts, 'colectia_materials' );
		$args = array(
			'post_type'      => self::CPT_MAT,
			'post_status'    => 'publish',
			'posts_per_page' => intval( $atts['limit'] ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		$tax_q = array();
		if ( '' !== $atts['type'] ) {
			$names = array();
			foreach ( array_map( 'trim', explode( ',', $atts['type'] ) ) as $tn ) {
				if ( '' === $tn ) { continue; }
				$names[] = $tn;
				$vn = $this->type_vn( $tn );
				if ( $vn !== $tn ) { $names[] = $vn; }
			}
			$tax_q[] = array( 'taxonomy' => self::TAX_MAT_TYPE, 'field' => 'name', 'terms' => $names );
		}
		if ( '' !== $atts['collection'] ) {
			$tax_q[] = array( 'taxonomy' => self::TAX_MAT_COL, 'field' => 'name', 'terms' => array_map( 'trim', explode( ',', $atts['collection'] ) ) );
		}
		if ( $tax_q ) { $args['tax_query'] = $tax_q; }
		$posts = get_posts( $args );
		if ( ! $posts ) { return '<p class="cns-mat-empty">Chưa có vật liệu nào.</p>'; }
		$items = $this->mat_items_from_posts( $posts );
		return $this->render_grid( $items, ( 'yes' === $atts['group'] ), intval( $atts['columns'] ), $atts['style'] );
	}

	public function render_grid( $items, $group = false, $columns = 4, $style = 'wd' ) {
		if ( 'plain' === $style ) { return $this->render_grid_plain( $items, $group, $columns ); }
		return $this->render_grid_wd( $items, $group, $columns );
	}

	/* 1.9.1: lưới vật liệu dùng đúng markup/class của WoodMart */
	public function render_grid_wd( $items, $group = false, $columns = 6 ) {
		$columns = $columns > 0 ? $columns : 6;
		$md      = $columns >= 5 ? 4 : ( $columns > 2 ? 3 : $columns );
		$sm      = $columns >= 4 ? 2 : 1;
		$groups  = array();
		if ( $group ) {
			foreach ( $items as $m ) {
				$g = '' !== $m['type'] ? $m['type'] : 'KHÁC';
				if ( ! isset( $groups[ $g ] ) ) { $groups[ $g ] = array(); }
				$groups[ $g ][] = $m;
			}
		} else {
			$groups[''] = $items;
		}
		$gs   = '--wd-col-lg:' . $columns . ';--wd-col-md:' . $md . ';--wd-col-sm:' . $sm . ';--wd-gap-lg:10px;--wd-gap-md:6px;';
		$html = '<div class="cns-materials cns-materials-wd wd-products-element">';
		foreach ( $groups as $gname => $list ) {
			$html .= '<div class="cns-mat-group">';
			if ( '' !== $gname ) {
				$t  = get_term_by( 'name', $gname, self::TAX_MAT_TYPE );
				$lk = '';
				if ( $t ) { $l = get_term_link( $t ); if ( ! is_wp_error( $l ) ) { $lk = $l; } }
				$html .= '<div class="cns-mat-group-title">';
				$html .= $lk ? '<a href="' . esc_url( $lk ) . '">' . esc_html( $gname ) . '</a>' : esc_html( $gname );
				$html .= '</div>';
			}
			$html .= '<div class="products wd-products elements-grid wd-grid-g grid-columns-' . intval( $columns ) . '" data-source="shortcode" data-columns="' . intval( $columns ) . '" style="' . esc_attr( $gs ) . '">';
			$i = 0;
			foreach ( $list as $m ) {
				$i++;
				$url   = ! empty( $m['url'] ) ? $m['url'] : '';
				$media = ! empty( $m['thumb'] )
					? '<img loading="lazy" decoding="async" src="' . esc_url( $m['thumb'] ) . '" alt="' . esc_attr( $m['name'] ) . '" />'
					: '<span class="cns-mat-swatch" style="background:' . esc_attr( $this->color_hex( isset( $m['color'] ) ? $m['color'] : '' ) ) . ';"></span>';
				$html .= '<div class="wd-product wd-hover-base wd-hover-with-fade wd-col product-grid-item cns-mat-card" data-loop="' . $i . '">';
				$html .= '<div class="product-wrapper"><div class="product-element-top">';
				$html .= $url
					? '<a href="' . esc_url( $url ) . '" class="product-image-link" aria-label="' . esc_attr( $m['name'] ) . '">' . $media . '</a>'
					: '<span class="product-image-link">' . $media . '</span>';
				$html .= '</div><div class="product-element-bottom product-information">';
				$html .= '<h3 class="wd-entities-title">';
				$html .= $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $m['name'] ) . '</a>' : esc_html( $m['name'] );
				$html .= '</h3>';
				if ( ! empty( $m['type'] ) ) {
					$html .= '<div class="wd-product-cats">';
					$html .= ! empty( $m['tlink'] )
						? '<a href="' . esc_url( $m['tlink'] ) . '" rel="tag">' . esc_html( $m['type'] ) . '</a>'
						: '<span>' . esc_html( $m['type'] ) . '</span>';
					$html .= '</div>';
				}
				if ( ! empty( $m['code'] ) ) { $html .= '<div class="cns-mat-code">' . esc_html( $m['code'] ) . '</div>'; }
				$html .= '</div></div></div>';
			}
			$html .= '</div></div>';
		}
		$html .= '</div>';
		return $html;
	}

	public function render_grid_plain( $items, $group = false, $columns = 4 ) {
		$columns = $columns > 0 ? $columns : 4;
		$groups  = array();
		if ( $group ) {
			foreach ( $items as $m ) {
				$g = '' !== $m['type'] ? $m['type'] : 'KHÁC';
				if ( ! isset( $groups[ $g ] ) ) { $groups[ $g ] = array(); }
				$groups[ $g ][] = $m;
			}
		} else {
			$groups[''] = $items;
		}
		$style = 'grid-template-columns:repeat(' . $columns . ',minmax(0,1fr));';
		$html  = '<div class="cns-materials">';
		foreach ( $groups as $gname => $list ) {
			$html .= '<div class="cns-mat-group">';
			if ( '' !== $gname ) { $html .= '<div class="cns-mat-group-title">' . esc_html( $gname ) . '</div>'; }
			$html .= '<div class="cns-mat-grid" style="' . esc_attr( $style ) . '">';
			foreach ( $list as $m ) {
				$inner = '<span class="cns-mat-thumb">';
				if ( ! empty( $m['thumb'] ) ) {
					$inner .= '<img src="' . esc_url( $m['thumb'] ) . '" alt="' . esc_attr( $m['name'] ) . '" loading="lazy" />';
				} else {
					$inner .= '<span class="cns-mat-swatch" style="background:' . esc_attr( $this->color_hex( $m['color'] ) ) . ';"></span>';
				}
				$inner .= '</span><div class="cns-mat-name">' . esc_html( $m['name'] ) . '</div>';
				if ( ! empty( $m['code'] ) ) { $inner .= '<div class="cns-mat-code">' . esc_html( $m['code'] ) . '</div>'; }
				if ( ! empty( $m['url'] ) ) {
					$html .= '<a class="cns-mat-item" href="' . esc_url( $m['url'] ) . '">' . $inner . '</a>';
				} else {
					$html .= '<div class="cns-mat-item">' . $inner . '</div>';
				}
			}
			$html .= '</div></div>';
		}
		return $html . '</div>';
	}

	/* ---------------- 1.8.0: chuyển sản phẩm vật liệu cũ sang CPT ---------------- */

	public function handle_migrate_materials() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Không có quyền.' ); }
		check_admin_referer( 'cns_migrate_materials' );
		$this->log = array();
		$term = get_term_by( 'name', self::MAT_CAT, 'product_cat' );
		if ( ! $term ) {
			$this->log[] = 'Không tìm thấy danh mục sản phẩm "' . self::MAT_CAT . '".';
			$this->save_log();
			wp_safe_redirect( admin_url( 'options-general.php?page=cns&migrated=1' ) );
			exit;
		}
		$ids = get_posts( array(
			'post_type'   => 'product',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'tax_query'   => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $term->term_id ) ),
		) );
		$this->log[] = 'Tìm thấy ' . count( $ids ) . ' sản phẩm vật liệu cần chuyển.';
		foreach ( $ids as $pid ) {
			$po   = get_post( $pid );
			if ( ! $po ) { continue; }
			$nid  = get_post_meta( $pid, '_notion_page_id', true );
			$name = $po->post_title;
			$post_id = $this->find_material_post( $nid ? $nid : 'x', $name );
			$data = array(
				'post_type'    => self::CPT_MAT,
				'post_status'  => 'publish',
				'post_title'   => $name,
				'post_content' => $po->post_content,
				'post_excerpt' => $po->post_excerpt,
			);
			if ( $post_id ) { $data['ID'] = $post_id; $res = wp_update_post( $data, true ); }
			else { $res = wp_insert_post( $data, true ); }
			if ( is_wp_error( $res ) || ! $res ) { $this->log[] = 'LỖI khi chuyển "' . $name . '"'; continue; }
			$post_id = intval( $res );
			if ( $nid ) { update_post_meta( $post_id, '_notion_page_id', $nid ); }
			$thumb_id = get_post_thumbnail_id( $pid );
			if ( $thumb_id ) { set_post_thumbnail( $post_id, $thumb_id ); }
			$prod = wc_get_product( $pid );
			if ( $prod ) {
				foreach ( $prod->get_attributes() as $attr ) {
					$an = $attr->get_name();
					$av = implode( ', ', $attr->get_options() );
					if ( false !== mb_stripos( $an, 'loại' ) && '' !== $av ) { wp_set_object_terms( $post_id, $this->type_vn( $av ), self::TAX_MAT_TYPE ); }
					if ( false !== mb_stripos( $an, 'màu' ) && '' !== $av ) { update_post_meta( $post_id, '_cns_mat_color', $av ); }
					if ( false !== mb_stripos( $an, 'nhà máy' ) && '' !== $av ) { update_post_meta( $post_id, '_cns_mat_factory', $av ); }
				}
				if ( $prod->get_sku() ) { update_post_meta( $post_id, '_cns_mat_code', $prod->get_sku() ); }
			}
			wp_update_post( array( 'ID' => $pid, 'post_status' => 'draft' ) );
			$this->log[] = '  → "' . $name . '" → vật liệu #' . $post_id . ' (sản phẩm cũ #' . $pid . ' đã chuyển về nháp)';
		}
		flush_rewrite_rules();
		$this->save_log();
		wp_safe_redirect( admin_url( 'options-general.php?page=cns&migrated=1' ) );
		exit;
	}

	private function sync_materials( $props, $prod_id ) {
		$prop = $this->prop( $props, self::P_MATERIAL );
		$rels = isset( $prop['relation'] ) ? (array) $prop['relation'] : array();
		if ( ! $rels ) {
			delete_post_meta( $prod_id, '_notion_materials' );
			return;
		}
		$thumb_map = get_post_meta( $prod_id, '_notion_mat_thumbs', true );
		if ( ! is_array( $thumb_map ) ) { $thumb_map = array(); }

		$materials = array();
		$wp_material_ids = array();
		foreach ( $rels as $rel ) {
			if ( empty( $rel['id'] ) ) { continue; }
			$mid = $rel['id'];
			$local_ids = get_posts( array( 'post_type' => self::CPT_MAT, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_notion_page_id', 'meta_value' => $mid ) );
			if ( $local_ids ) { $wp_material_ids[] = intval( $local_ids[0] ); }
			$p   = $this->notion_request( 'GET', '/pages/' . $mid, null );
			if ( is_wp_error( $p ) || empty( $p['properties'] ) ) {
				$this->log[] = 'Cảnh báo: không đọc được vật liệu ' . $mid . ' (kiểm tra integration đã được share với database Material chưa).';
				continue;
			}
			$mp   = $p['properties'];
			$name = '';
			foreach ( $mp as $pp ) {
				if ( isset( $pp['type'] ) && 'title' === $pp['type'] ) { $name = $this->plain_text( $pp['title'] ); break; }
			}
			if ( '' === $name ) { continue; }

			$type    = isset( $mp['MATERIAL TYPE']['select']['name'] ) ? $mp['MATERIAL TYPE']['select']['name'] : '';
			$type    = $this->type_vn( $type );
			$color   = isset( $mp['Màu sắc']['select']['name'] ) ? $mp['Màu sắc']['select']['name'] : '';
			$code    = '';
			if ( isset( $mp['CODE']['formula']['string'] ) ) { $code = $mp['CODE']['formula']['string']; }
			if ( '' === $code && isset( $mp['FACTORY CODE']['rich_text'] ) ) { $code = $this->plain_text( $mp['FACTORY CODE']['rich_text'] ); }
			$link = isset( $mp['URL']['url'] ) ? $mp['URL']['url'] : '';
			$collections = $this->relation_titles( $this->prop( $mp, 'Bộ sưu tập vật liệu' ) );

			// Thumbnail: link file Notion hết hạn sau ~1 giờ nên phải tải về media library.
			$thumb_url = '';
			$raw = '';
			if ( isset( $mp['Thumbnail']['files'][0] ) ) {
				$f0  = $mp['Thumbnail']['files'][0];
				$raw = isset( $f0['file']['url'] ) ? $f0['file']['url'] : ( isset( $f0['external']['url'] ) ? $f0['external']['url'] : '' );
			}
			if ( $raw ) {
				$path = wp_parse_url( $raw, PHP_URL_PATH );
				$base = $path ? wp_basename( $path ) : ( $mid . '.jpg' );
				$key  = md5( $mid . '|' . $base );
				$aid  = isset( $thumb_map[ $key ] ) ? intval( $thumb_map[ $key ] ) : 0;
				if ( $aid && ! wp_get_attachment_url( $aid ) ) { $aid = 0; }
				if ( ! $aid && preg_match( '/\.(jpe?g|png|gif|webp|avif)$/i', $base ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/media.php';
					require_once ABSPATH . 'wp-admin/includes/image.php';
					$aid = $this->sideload( $raw, $base, $prod_id );
					if ( $aid ) { $thumb_map[ $key ] = $aid; }
				}
				if ( $aid ) { $thumb_url = wp_get_attachment_image_url( $aid, 'medium' ); }
			}

			$materials[] = array(
				'nid'   => $mid,
				'name'  => $name,
				'type'  => $type,
				'color' => $color,
				'code'  => $code,
				'url'   => $link,
				'thumb' => $thumb_url,
				'collection' => $collections ? implode( ', ', $collections ) : '',
			);
		}
		update_post_meta( $prod_id, '_notion_mat_thumbs', $thumb_map );
		if ( $materials ) {
			update_post_meta( $prod_id, '_notion_materials', $materials );
			update_post_meta( $prod_id, '_cns_product_material_ids', array_values( array_unique( $wp_material_ids ) ) );
			$this->log[] = '  → ' . count( $materials ) . ' vật liệu.';
		} else {
			delete_post_meta( $prod_id, '_notion_materials' );
			delete_post_meta( $prod_id, '_cns_product_material_ids' );
		}
	}

	// Gắn nội dung vật liệu vào tab "Vật liệu" có sẵn của theme; nếu chưa có thì tự tạo tab mới.
	public function material_tab( $tabs ) {
		global $product;
		// Từ nay chỉ dùng tab cns_materials; bỏ hẳn key tab WoodMart cũ.
		unset( $tabs['chon-vat-lieu'] );
		if ( ! $product ) { return $tabs; }
		$materials = $this->get_linked_materials( $product->get_id() );
		if ( ! is_array( $materials ) || ! $materials ) { unset( $tabs['cns_materials'] ); return $tabs; }
		$self = $this;
		$tabs['cns_materials'] = array(
			'title' => 'Vật liệu',
			'priority' => 25,
			'callback' => function() use ( $self ) { echo $self->render_materials(); }, // phpcs:ignore WordPress.Security.EscapeOutput
		);
		return $tabs;
	}

	public function add_product_material_box(){add_meta_box('cns_product_materials','Vật liệu liên kết với Notion',array($this,'render_product_material_box'),'product','normal','default');}
	private function material_admin_item($id){$t=wp_get_object_terms($id,self::TAX_MAT_TYPE,array('fields'=>'names'));$c=wp_get_object_terms($id,self::TAX_MAT_COL,array('fields'=>'names'));return array('nid'=>get_post_meta($id,'_notion_page_id',true),'name'=>get_the_title($id),'type'=>(!is_wp_error($t)&&$t)?$t[0]:'','collection'=>(!is_wp_error($c)&&$c)?implode(', ',$c):'','color'=>get_post_meta($id,'_cns_mat_color',true),'code'=>get_post_meta($id,'_cns_mat_code',true),'url'=>get_permalink($id),'thumb'=>get_the_post_thumbnail_url($id,'medium'));}
	public function render_product_material_box($post){
		$sel=array_values(array_unique(array_filter(array_map('absint',(array)get_post_meta($post->ID,'_cns_product_material_ids',true)))));
		if(!$sel){$cur=get_post_meta($post->ID,'_notion_materials',true);foreach((array)$cur as $m){if(!empty($m['nid'])){$ids=get_posts(array('post_type'=>self::CPT_MAT,'post_status'=>'any','numberposts'=>1,'fields'=>'ids','meta_key'=>'_notion_page_id','meta_value'=>$m['nid']));if($ids)$sel[]=intval($ids[0]);}}}
		wp_nonce_field('cns_product_materials','cns_product_materials_nonce');
		echo '<p><b>Đang chọn: '.count($sel).' vật liệu</b><br>Danh sách này được lưu bằng Material ID của WordPress để luôn giữ nguyên lựa chọn khi mở lại Product.</p><div style="max-height:420px;overflow:auto;border:1px solid #ddd;padding:8px;">';
		foreach(get_posts(array('post_type'=>self::CPT_MAT,'post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC')) as $mat){$id=$mat->ID;$t=wp_get_object_terms($id,self::TAX_MAT_TYPE,array('fields'=>'names'));$type=(!is_wp_error($t)&&$t)?$t[0]:'';$thumb=get_the_post_thumbnail_url($id,'thumbnail');$visual=$thumb?'<img src="'.esc_url($thumb).'" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:3px;display:block;" />':'<span style="width:44px;height:44px;background:#f1f1f1;border-radius:3px;display:block;"></span>';echo '<label style="display:flex;align-items:center;gap:9px;padding:7px 2px;border-bottom:1px solid #eee;cursor:pointer;"><input type="checkbox" name="cns_material_ids[]" value="'.esc_attr($id).'" '.checked(in_array($id,$sel,true),true,false).' /><span>'.$visual.'</span><span><b>'.esc_html(get_the_title($id)).'</b>'.($type?'<br><small style="color:#777;">'.esc_html($type).'</small>':'').'</span></label>';}
		echo '</div>';
	}

	public function save_product_material_box($post_id,$post,$update){if((defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||empty($_POST['cns_product_materials_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cns_product_materials_nonce'])),'cns_product_materials')||!current_user_can('edit_post',$post_id))return;$ids=array_values(array_unique(array_filter(array_map('absint',(array)(isset($_POST['cns_material_ids'])?wp_unslash($_POST['cns_material_ids']):array())))));$items=array();$rels=array();foreach($ids as $id){if(self::CPT_MAT!==get_post_type($id))continue;$item=$this->material_admin_item($id);$items[]=$item;if(!empty($item['nid']))$rels[]=array('id'=>$item['nid']);}update_post_meta($post_id,'_cns_product_material_ids',$ids);if($items)update_post_meta($post_id,'_notion_materials',$items);else delete_post_meta($post_id,'_notion_materials');$nid=get_post_meta($post_id,'_notion_page_id',true);if($nid)$this->notion_request('PATCH','/pages/'.$nid,array('properties'=>array(self::P_MATERIAL=>array('relation'=>$rels),self::P_SYNCWEB=>array('status'=>array('name'=>self::S_DONE)))));}

	public function material_tab_button_script() {
		if ( ! is_product() ) { return; }
		?>
		<script>document.addEventListener('click',function(e){var a=e.target.closest('a,button');if(!a)return;var t=(a.textContent||'').toLowerCase();if(t.indexOf('chọn vật liệu')===-1)return;var tab=document.querySelector('.wc-tabs a[href*="materials"],.wd-nav-tabs a[href*="materials"]');if(tab){e.preventDefault();tab.click();document.querySelector('.woocommerce-tabs,.wc-tabs-wrapper')?.scrollIntoView({behavior:'smooth',block:'start'});}});</script>
		<?php
	}

	public function render_materials() {
		global $product;
		if ( ! $product ) { return ''; }
		$materials = $this->get_linked_materials( $product->get_id() );
		if ( ! is_array( $materials ) || ! $materials ) { return ''; }
		$groups = array(); foreach ( $materials as $m ) { $type = ! empty( $m['type'] ) ? $m['type'] : 'Khác'; $groups[ $type ][] = $m; }
		$order = array( 'Da', 'Vải', 'Kim loại', 'Đá', 'Gỗ', 'Khác' ); uksort( $groups, function($a,$b) use($order){$ia=array_search($a,$order,true);$ib=array_search($b,$order,true);return(false===$ia?99:$ia)-(false===$ib?99:$ib);} );
		$product_image = wp_get_attachment_image_url( $product->get_image_id(), 'large' ); if ( ! $product_image ) { $product_image = wc_placeholder_img_src( 'large' ); }
		$html = '<div class="cns-material-selector" data-product-name="' . esc_attr($product->get_name()) . '" data-product-image="' . esc_url($product_image) . '" data-product-price="' . esc_attr(wp_strip_all_tags($product->get_price_html())) . '">';
		$html .= '<style>.cns-material-selector{max-width:1040px;padding:8px 0;font-family:inherit}.cns-selector-intro{font-size:14px;line-height:1.6;color:#5c5c5c;margin:0 0 28px}.cns-material-selector .cns-mat-group{margin:0 0 34px}.cns-material-selector .cns-mat-group-title{font-size:19px;line-height:1.3;letter-spacing:.01em;font-weight:600;margin:0 0 5px}.cns-material-selector .cns-mat-help{font-size:13px;color:#737373;margin:0 0 14px}.cns-material-selector .cns-mat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:14px}.cns-q-material{display:block;width:100%;padding:0;text-align:left;background:#fff;border:1px solid #dedede;cursor:pointer;transition:border .18s,transform .18s,box-shadow .18s}.cns-q-material:hover{border-color:#777;transform:translateY(-2px)}.cns-q-material.is-selected{border:2px solid #171717;box-shadow:0 0 0 3px #f1f1f1}.cns-q-material .cns-mat-thumb{display:block;aspect-ratio:1;overflow:hidden;background:#e8e8e8}.cns-q-material img,.cns-q-material .cns-mat-swatch{display:block;width:100%;height:100%;object-fit:cover}.cns-q-material .cns-mat-name{display:block;font-size:13px;line-height:1.42;font-weight:600;padding:10px 10px 2px}.cns-q-material .cns-mat-code{display:block;font-size:11px;letter-spacing:.04em;color:#777;padding:0 10px 10px}.cns-quote-action{display:none;margin:2px 0 10px;padding:14px 20px;background:#171717;border:1px solid #171717;color:#fff;font-size:13px;font-weight:600;letter-spacing:.04em;cursor:pointer}.cns-quote-action.is-visible{display:inline-flex;align-items:center;gap:8px}.cns-project-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}.cns-quote-action:hover{background:#fff;color:#171717}@media(max-width:600px){.cns-material-selector .cns-mat-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}}</style>';
		$html .= '<p class="cns-selector-intro">Chọn một vật liệu cho mỗi danh mục. Lựa chọn của bạn sẽ được đưa vào bản báo giá.</p>';
		foreach ( $groups as $type => $items ) { $html .= '<section class="cns-mat-group"><h3 class="cns-mat-group-title">' . esc_html($type) . '</h3><p class="cns-mat-help">Chọn 1 vật liệu</p><div class="cns-mat-grid">'; foreach ( $items as $m ) { $thumb=!empty($m['thumb'])?$m['thumb']:'';$name=!empty($m['name'])?$m['name']:'Vật liệu';$code=!empty($m['code'])?$m['code']:'';$visual=$thumb?'<img src="'.esc_url($thumb).'" alt="'.esc_attr($name).'" loading="lazy">':'<span class="cns-mat-swatch" style="background:'.esc_attr($this->color_hex(isset($m['color'])?$m['color']:'')) .';"></span>';$html.='<button type="button" class="cns-q-material" data-type="'.esc_attr($type).'" data-name="'.esc_attr($name).'" data-code="'.esc_attr($code).'" data-image="'.esc_url($thumb).'"><span class="cns-mat-thumb">'.$visual.'</span><span class="cns-mat-name">'.esc_html($name).'</span>'.($code?'<span class="cns-mat-code">'.esc_html($code).'</span>':'').'</button>'; } $html.='</div></section>'; }
		$html .= '<div class="cns-project-actions"><button type="button" class="cns-quote-action"><span>Xuất PDF báo giá</span><span>→</span></button></div>';
		$html .= '<script>(function(){var root=document.currentScript.parentElement,selected={};function esc(v){var d=document.createElement("div");d.textContent=v||"";return d.innerHTML}root.addEventListener("click",function(e){var card=e.target.closest(".cns-q-material");if(card&&root.contains(card)){var type=card.dataset.type;root.querySelectorAll(".cns-q-material").forEach(function(item){if(item.dataset.type===type){item.classList.remove("is-selected")}});card.classList.add("is-selected");selected[type]={type:type,name:card.dataset.name,code:card.dataset.code,image:card.dataset.image};var form=document.querySelector("form.cart"),input=form?form.querySelector("input[name=cns_selected_materials]"):null;if(form&&!input){input=document.createElement("input");input.type="hidden";input.name="cns_selected_materials";form.appendChild(input)}if(input)input.value=JSON.stringify(Object.keys(selected).map(function(k){return selected[k]}));root.querySelector(".cns-quote-action").classList.add("is-visible");return}var action=e.target.closest(".cns-quote-action");if(action&&root.contains(action)){var rows=Object.keys(selected).map(function(key){var m=selected[key],image=m.image?`<img src="${esc(m.image)}" style="width:60px;height:60px;object-fit:cover;border-radius:3px">`:"";return `<tr><td style="width:76px">${image}</td><td><strong>${esc(m.type)}</strong><br>${esc(m.name)}${m.code?`<br><small>${esc(m.code)}</small>`:""}</td></tr>`}).join("");if(!rows)return;var w=window.open("","_blank");if(!w)return;var logo="https://colectia.vn/wp-content/uploads/2025/10/Logo.svg";var doc=`<!doctype html><html><head><title>Báo giá - ${esc(root.dataset.productName)}</title><style>@page{size:A4;margin:0}*{box-sizing:border-box}body{margin:0;background:#f4f2ee;color:#20201e;font-family:"Helvetica Neue",Arial,Helvetica,sans-serif}.sheet{width:210mm;min-height:297mm;margin:auto;background:#fff;padding:22mm 20mm;display:flex;flex-direction:column}.top{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #d9d5cf;padding-bottom:15mm}.logo{width:142px;height:32px;object-fit:contain;object-position:left center}.eyebrow{font-size:10px;letter-spacing:.18em;color:#77736c;margin:0 0 8px;text-transform:uppercase}.quote-title{font-family:"Helvetica Neue",Arial,Helvetica,sans-serif;font-size:31px;letter-spacing:.06em;font-weight:400;margin:0}.date{text-align:right;font-size:11px;line-height:1.6;color:#6f6b65}.product{display:grid;grid-template-columns:1fr 1fr;gap:18mm;padding:16mm 0;border-bottom:1px solid #e5e2dc}.hero{width:100%;aspect-ratio:1;object-fit:cover;background:#eee}.product-copy{display:flex;flex-direction:column;justify-content:center}.product-copy h2{font-family:"Helvetica Neue",Arial,Helvetica,sans-serif;font-size:26px;line-height:1.16;font-weight:400;margin:0 0 18px}.price{font-size:13px;line-height:1.7;color:#56524c}.materials{padding-top:15mm}.materials h3{font-size:11px;letter-spacing:.18em;text-transform:uppercase;margin:0 0 12px;color:#77736c}.materials table{width:100%;border-collapse:collapse}.materials td{border-top:1px solid #e4e0da;padding:11px 0;vertical-align:middle;font-size:13px;line-height:1.5}.materials td:first-child{width:76px}.materials img{width:56px;height:56px;object-fit:cover}.materials strong{font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#77736c}.materials small{font-size:11px;color:#77736c}.footer{margin-top:auto;display:flex;justify-content:space-between;border-top:1px solid #d9d5cf;padding-top:7mm;font-size:9px;line-height:1.55;letter-spacing:.03em;color:#77736c}@media print{body{background:#fff}.sheet{margin:0;width:auto;min-height:auto}}</style></head><body><main class="sheet"><header class="top"><div><img class="logo" src="${logo}" alt="COLECTIA"><p class="eyebrow">Material Selection</p><h1 class="quote-title">BÁO GIÁ</h1></div><div class="date">Ngày xuất<br><b>${new Date().toLocaleDateString("vi-VN")}</b></div></header><section class="product"><img class="hero" src="${esc(root.dataset.productImage)}"><div class="product-copy"><p class="eyebrow">Sản phẩm được lựa chọn</p><h2>${esc(root.dataset.productName)}</h2></div></section><section class="materials"><h3>Vật liệu đã chọn</h3><table>${rows}</table></section><footer class="footer"><span>COLECTIA VIETNAM<br>colectia.vn</span><span>Báo giá được tạo từ lựa chọn vật liệu.<br>Vui lòng liên hệ để xác nhận giá và thời gian giao hàng.</span></footer></main></body></html>`;w.document.open();w.document.write(doc);w.document.close();w.onload=function(){w.focus();w.print()}}})})();</script></div>';
		return $html;
	}

	private function color_hex( $color ) {
		$map = array(
			'Trắng' => '#f2f2f2', 'Xanh lá' => '#4a7c59', 'Xanh lam' => '#3f5f8f', 'Đỏ' => '#a33b3b',
			'Hồng' => '#d99aa8', 'Vàng' => '#d9b44a', 'Cam' => '#cf7a3a', 'Nâu' => '#6f5137',
			'Xám' => '#9a9a9a', 'Đen' => '#2b2b2b', 'Tím' => '#7a5c8f',
		);
		return isset( $map[ $color ] ) ? $map[ $color ] : '#e4e0d9';
	}

	private function norm_name( $t ) {
		$t = function_exists( 'remove_accents' ) ? remove_accents( $t ) : $t;
		$t = strtolower( $t );
		return preg_replace( '/[^a-z0-9]+/', '', $t );
	}

	private function names_match( $a, $b ) {
		$na = $this->norm_name( $a );
		$nb = $this->norm_name( $b );
		if ( '' === $na || '' === $nb ) { return false; }
		if ( $na === $nb ) { return true; }
		if ( strlen( $na ) >= 6 && strlen( $nb ) >= 6 && ( false !== strpos( $na, $nb ) || false !== strpos( $nb, $na ) ) ) { return true; }
		$pct = 0;
		similar_text( $na, $nb, $pct );
		return $pct >= 85;
	}

	private function title_of( $props ) {
		foreach ( (array) $props as $p ) {
			if ( isset( $p['type'] ) && 'title' === $p['type'] ) { return $this->plain_text( $p['title'] ); }
		}
		return '';
	}

	private function resolve_db_id( $title, $opt_key ) {
		$id = get_option( $opt_key, '' );
		if ( $id ) { return $id; }
		$res = $this->notion_request( 'POST', '/search', array(
			'query'     => $title,
			'filter'    => array( 'value' => 'database', 'property' => 'object' ),
			'page_size' => 25,
		) );
		if ( is_wp_error( $res ) || empty( $res['results'] ) ) { return ''; }
		foreach ( $res['results'] as $r ) {
			$t = isset( $r['title'] ) ? trim( $this->plain_text( $r['title'] ) ) : '';
			if ( 0 === strcasecmp( $t, $title ) ) {
				$id = str_replace( '-', '', $r['id'] );
				update_option( $opt_key, $id, false );
				return $id;
			}
		}
		return '';
	}

	private function query_db_pages( $db_id, $filter = null ) {
		$pages  = array();
		$cursor = null;
		do {
			$body = array( 'page_size' => 100 );
			if ( $filter ) { $body['filter'] = $filter; }
			if ( $cursor ) { $body['start_cursor'] = $cursor; }
			$res = $this->notion_request( 'POST', '/databases/' . $db_id . '/query', $body );
			if ( is_wp_error( $res ) ) { return $res; }
			if ( ! empty( $res['results'] ) ) { $pages = array_merge( $pages, $res['results'] ); }
			$cursor = ! empty( $res['has_more'] ) ? $res['next_cursor'] : null;
		} while ( $cursor );
		return $pages;
	}

	private function edit_filter() {
		return array( 'property' => self::P_SYNCWEB, 'status' => array( 'equals' => self::S_EDIT ) );
	}

	/* ---------- Bộ sưu tập (product_brand) ↔ database Collection ---------- */

	private function find_or_create_brand( $name, $notion_page_id = '' ) {
		if ( ! taxonomy_exists( self::BRAND_TAX ) || '' === trim( $name ) ) { return 0; }
		if ( $notion_page_id ) {
			$found = get_terms( array(
				'taxonomy'   => self::BRAND_TAX,
				'hide_empty' => false,
				'meta_key'   => '_notion_page_id',
				'meta_value' => $notion_page_id,
			) );
			if ( ! is_wp_error( $found ) && $found ) { return intval( $found[0]->term_id ); }
		}
		$terms = get_terms( array( 'taxonomy' => self::BRAND_TAX, 'hide_empty' => false ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( $this->names_match( $t->name, $name ) ) { return intval( $t->term_id ); }
			}
		}
		$new = wp_insert_term( $name, self::BRAND_TAX );
		if ( is_wp_error( $new ) ) { return 0; }
		$this->log[] = 'Tạo bộ sưu tập mới: ' . $name;
		return intval( $new['term_id'] );
	}

	private function sync_collection_db() {
		$db = $this->resolve_db_id( 'Collection', self::OPT_DB_COL );
		if ( ! $db ) {
			$this->log[] = 'Bỏ qua Collection: chưa tìm thấy database (hãy share integration với database Collection).';
			return;
		}
		$pages = $this->query_db_pages( $db, $this->edit_filter() );
		if ( is_wp_error( $pages ) ) {
			$this->log[] = 'LỖI Collection: ' . $pages->get_error_message();
			return;
		}
		$this->log[] = 'Collection: ' . count( $pages ) . ' mục ở trạng thái "' . self::S_EDIT . '".';
		foreach ( $pages as $p ) {
			$props = isset( $p['properties'] ) ? $p['properties'] : array();
			$name  = $this->title_of( $props );
			if ( '' === $name ) { continue; }
			$term_id = 0;
			$wpt     = $this->prop( $props, 'WP Term ID' );
			if ( ! empty( $wpt['number'] ) ) {
				$t = get_term( intval( $wpt['number'] ), self::BRAND_TAX );
				if ( $t && ! is_wp_error( $t ) ) { $term_id = intval( $t->term_id ); }
			}
			if ( ! $term_id ) { $term_id = $this->find_or_create_brand( $name, $p['id'] ); }
			if ( ! $term_id ) {
				$this->log[] = 'Cảnh báo: không map được bộ sưu tập "' . $name . '"';
				continue;
			}
			$intro_p = $this->prop( $props, 'Giới thiệu' );
			$intro   = isset( $intro_p['rich_text'] ) ? trim( $this->plain_text( $intro_p['rich_text'] ) ) : '';
			if ( 'No content' === $intro ) { $intro = ''; }
			if ( $intro ) { wp_update_term( $term_id, self::BRAND_TAX, array( 'description' => $intro ) ); }
			update_term_meta( $term_id, '_notion_page_id', $p['id'] );
			$term = get_term( $term_id, self::BRAND_TAX );
			$link = get_term_link( $term_id, self::BRAND_TAX );
			$this->notion_request( 'PATCH', '/pages/' . $p['id'], array(
				'properties' => array(
					'WP Term ID'    => array( 'number' => $term_id ),
					'WP Link'       => array( 'url' => is_wp_error( $link ) ? null : $link ),
					self::P_SYNCWEB => array( 'status' => array( 'name' => self::S_DONE ) ),
				),
			) );
			$this->log[] = 'Bộ sưu tập: ' . $name . ' → ' . ( $term && ! is_wp_error( $term ) ? $term->name : '?' ) . ' (#' . $term_id . ')';
		}
	}

	/* ---------- Danh mục vật liệu ↔ database Material ---------- */

	private function find_material_product( $page_id, $wpid, $name, $cat_id ) {
		$ex = get_posts( array(
			'post_type' => 'product', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
			'meta_key'  => '_notion_page_id', 'meta_value' => $page_id,
		) );
		if ( $ex ) { return wc_get_product( $ex[0] ); }
		if ( $wpid ) {
			$p = wc_get_product( intval( $wpid ) );
			if ( $p ) { return $p; }
		}
		if ( $cat_id ) {
			$ids = get_posts( array(
				'post_type' => 'product', 'post_status' => 'any', 'numberposts' => 300, 'fields' => 'ids',
				'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cat_id ) ),
			) );
			foreach ( $ids as $id ) {
				if ( get_post_meta( $id, '_notion_page_id', true ) ) { continue; }
				if ( $this->names_match( get_the_title( $id ), $name ) ) { return wc_get_product( $id ); }
			}
		}
		return null;
	}

	private function sync_material_collections_db() {
		$db = $this->resolve_db_id( 'Bộ sưu tập vật liệu', self::OPT_DB_MAT_COL );
		if ( ! $db ) { $this->log[] = 'Bỏ qua Bộ sưu tập vật liệu: chưa share integration với database.'; return; }
		$pages = $this->query_db_pages( $db, $this->edit_filter() );
		if ( is_wp_error( $pages ) ) { $this->log[] = 'LỖI Bộ sưu tập vật liệu: ' . $pages->get_error_message(); return; }
		$this->log[] = 'Bộ sưu tập vật liệu: ' . count( $pages ) . ' mục ở trạng thái "' . self::S_EDIT . '".';
		foreach ( $pages as $p ) {
			$props = isset( $p['properties'] ) ? $p['properties'] : array();
			$name = $this->title_of( $props );
			if ( '' === $name ) { continue; }
			$desc_p = $this->prop( $props, 'Mô tả' );
			$desc = isset( $desc_p['rich_text'] ) ? $this->plain_text( $desc_p['rich_text'] ) : '';
			$term = get_term_by( 'name', $name, self::TAX_MAT_COL );
			if ( $term ) { wp_update_term( $term->term_id, self::TAX_MAT_COL, array( 'description' => $desc ) ); }
			else { $made = wp_insert_term( $name, self::TAX_MAT_COL, array( 'description' => $desc ) ); $term = is_wp_error( $made ) ? false : get_term( $made['term_id'], self::TAX_MAT_COL ); }
			if ( ! $term || is_wp_error( $term ) ) { $this->log[] = 'LỖI: không lưu được bộ sưu tập "' . $name . '"'; continue; }
			$thumb = $this->prop( $props, 'Thumbnail' ); $url = '';
			if ( ! empty( $thumb['files'][0] ) ) { $f = $thumb['files'][0]; $url = isset( $f['file']['url'] ) ? $f['file']['url'] : ( isset( $f['external']['url'] ) ? $f['external']['url'] : '' ); }
			if ( $url ) { $aid = $this->sideload( $url, sanitize_title( $name ) . '-collection.jpg', 0 ); if ( $aid ) { update_term_meta( $term->term_id, '_cns_mat_collection_thumb', wp_get_attachment_url( $aid ) ); } }
			$link = get_term_link( $term );
			$this->notion_request( 'PATCH', '/pages/' . $p['id'], array( 'properties' => array(
				'WP Link' => array( 'url' => is_wp_error( $link ) ? null : $link ),
				self::P_SYNCWEB => array( 'status' => array( 'name' => self::S_DONE ) ),
			) ) );
			$this->log[] = '  → bộ sưu tập "' . $name . '"';
		}
	}

	private function sync_material_db() {
		$db = $this->resolve_db_id( 'Material', self::OPT_DB_MAT );
		if ( ! $db ) {
			$this->log[] = 'Bỏ qua Material: chưa tìm thấy database (hãy share integration với database Material).';
			return;
		}
		$pages = $this->query_db_pages( $db, $this->edit_filter() );
		if ( is_wp_error( $pages ) ) {
			$this->log[] = 'LỖI Material: ' . $pages->get_error_message();
			return;
		}
		$this->log[] = 'Material: ' . count( $pages ) . ' mục ở trạng thái "' . self::S_EDIT . '".';
		// 1.8.0: vật liệu lưu vào post type riêng, không còn là sản phẩm WooCommerce.
		$this->sync_material_posts( $pages );
		return;
		$cat_id = $this->find_or_create_cat( self::MAT_CAT );
		foreach ( $pages as $p ) {
			$props = isset( $p['properties'] ) ? $p['properties'] : array();
			$name  = $this->title_of( $props );
			if ( '' === $name ) { continue; }
			$wpid_p  = $this->prop( $props, self::P_WPID );
			$wpid    = ! empty( $wpid_p['number'] ) ? intval( $wpid_p['number'] ) : 0;
			$product = $this->find_material_product( $p['id'], $wpid, $name, $cat_id );
			$is_new  = false;
			if ( ! $product ) {
				$product = new WC_Product_Simple();
				$is_new  = true;
			}
			$product->set_name( $name );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'visible' );
			$product->set_sold_individually( false );
			$product->set_description( $this->get_page_content_html( $p['id'] ) );
			$type_p  = $this->prop( $props, 'MATERIAL TYPE' );
			$type    = isset( $type_p['select']['name'] ) ? $type_p['select']['name'] : '';
			$color_p = $this->prop( $props, 'Màu sắc' );
			$color   = isset( $color_p['select']['name'] ) ? $color_p['select']['name'] : '';
			$code_p  = $this->prop( $props, 'CODE' );
			$code    = isset( $code_p['formula']['string'] ) ? $code_p['formula']['string'] : '';
			$fac_p   = $this->prop( $props, 'FACTORY CODE' );
			$factory = isset( $fac_p['rich_text'] ) ? trim( $this->plain_text( $fac_p['rich_text'] ) ) : '';
			$sku     = $code ? $code : $factory;
			if ( $sku && ( ! $product->get_sku() || $product->get_sku() !== $sku ) ) {
				if ( ! wc_get_product_id_by_sku( $sku ) ) { $product->set_sku( $sku ); }
			}
			$short = array_filter( array( $type, $color, $factory ? 'Factory code: ' . $factory : '' ) );
			if ( $short ) { $product->set_short_description( implode( ' • ', $short ) ); }
			$attrs = array();
			foreach ( array( 'Loại vật liệu' => $type, 'Màu sắc' => $color, 'Mã nhà máy' => $factory ) as $an => $av ) {
				if ( '' === $av ) { continue; }
				$a = new WC_Product_Attribute();
				$a->set_name( $an );
				$a->set_options( array( $av ) );
				$a->set_visible( true );
				$a->set_position( count( $attrs ) );
				$attrs[] = $a;
			}
			$product->set_attributes( $attrs );
			if ( $cat_id ) { $product->set_category_ids( array( $cat_id ) ); }
			$prod_id = $product->save();
			if ( ! $prod_id ) {
				$this->log[] = 'LỖI: không lưu được vật liệu "' . $name . '"';
				continue;
			}
			update_post_meta( $prod_id, '_notion_page_id', $p['id'] );
			update_post_meta( $prod_id, '_notion_sync_ver', self::PLUGIN_VERSION );
			if ( isset( $p['last_edited_time'] ) ) { update_post_meta( $prod_id, '_notion_last_edited', $p['last_edited_time'] ); }
			$cols = $this->relation_titles( $this->prop( $props, 'Collection' ) );
			if ( $cols ) {
				$bids = array();
				foreach ( $cols as $cn ) {
					$bid = $this->find_or_create_brand( $cn );
					if ( $bid ) { $bids[] = $bid; }
				}
				if ( $bids ) { wp_set_object_terms( $prod_id, $bids, self::BRAND_TAX ); }
			}
			$thumb = $this->prop( $props, 'Thumbnail' );
			$furl  = '';
			if ( ! empty( $thumb['files'][0] ) ) {
				$f    = $thumb['files'][0];
				$furl = isset( $f['file']['url'] ) ? $f['file']['url'] : ( isset( $f['external']['url'] ) ? $f['external']['url'] : '' );
			}
			if ( $furl && ! $product->get_image_id() ) {
				$clean = strtok( $furl, '?' );
				$ext   = pathinfo( $clean, PATHINFO_EXTENSION );
				$ext   = $ext ? $ext : 'jpg';
				$aid   = $this->sideload( $furl, sanitize_title( $name ) . '.' . $ext, $prod_id );
				if ( $aid ) { set_post_thumbnail( $prod_id, $aid ); }
			}
			$this->notion_request( 'PATCH', '/pages/' . $p['id'], array(
				'properties' => array(
					self::P_WPID    => array( 'number' => $prod_id ),
					self::P_WPLINK  => array( 'url' => get_permalink( $prod_id ) ),
					self::P_SYNCWEB => array( 'status' => array( 'name' => self::S_DONE ) ),
				),
			) );
			$this->log[] = ( $is_new ? 'Tạo vật liệu: ' : 'Cập nhật vật liệu: ' ) . $name . ' (#' . $prod_id . ')';
		}
	}

	/* ================= Layout Elementor theo mẫu Camaleonda ================= */

	private function acf_fields() {
		return array(
			'chọn_vật_liệu'        => 'field_690adbdf605a4',
			'kich_thuoc_-_cau_tạo' => 'field_68e0967902953',
			'bao_quan'             => 'field_68e096ac05c98',
			'3d_model'             => 'field_68df30a8e0183',
			'2dcad'                => 'field_68df3044e0182',
			'vat_lieu_su_dung'     => 'field_68f51bd27cfc1',
			'map_vật_liệu'         => 'field_68df30c1e0184',
		);
	}

	private function set_acf( $post_id, $name, $value ) {
		$map = $this->acf_fields();
		if ( ! isset( $map[ $name ] ) ) { return; }
		update_post_meta( $post_id, $name, $value );
		update_post_meta( $post_id, '_' . $name, $map[ $name ] );
	}

	private function el_id() {
		return substr( md5( uniqid( '', true ) ), 0, 7 );
	}

	private function el_container( $children, $extra = array(), $inner = false ) {
		$settings = array_merge(
			array(
				'flex_direction' => 'column',
				'boxed_width'    => array( 'unit' => 'px', 'size' => self::EL_WIDTH, 'sizes' => array() ),
				'scroll_y'       => '-80',
			),
			$extra
		);
		return array(
			'id'       => $this->el_id(),
			'elType'   => 'container',
			'settings' => $settings,
			'elements' => $children,
			'isInner'  => (bool) $inner,
		);
	}

	private function el_text( $html ) {
		return array(
			'id'         => $this->el_id(),
			'elType'     => 'widget',
			'settings'   => array( 'text' => $html, 'scroll_y' => '-80' ),
			'elements'   => array(),
			'widgetType' => 'wd_text_block',
		);
	}

	private function el_image( $att, $custom = null ) {
		$settings = array(
			'image'    => array(
				'url'    => $att['url'],
				'id'     => (string) $att['id'],
				'size'   => '',
				'alt'    => '',
				'source' => 'library',
			),
			'scroll_y' => '-80',
		);
		$type = 'image';
		if ( $custom ) {
			$type                                = 'wd_image_or_svg';
			$settings['image_size']              = 'custom';
			$settings['image_custom_dimension']  = $custom;
		}
		return array(
			'id'         => $this->el_id(),
			'elType'     => 'widget',
			'settings'   => $settings,
			'elements'   => array(),
			'widgetType' => $type,
		);
	}

	private function el_row_image_text( $img, $text ) {
		$left  = $this->el_container(
			array( $img ),
			array(
				'content_width'  => 'full',
				'width'          => array( 'unit' => '%', 'size' => '40' ),
				'_flex_size'     => 'none',
				'_element_width' => 'initial',
				'boxed_width'    => null,
			),
			true
		);
		unset( $left['settings']['boxed_width'] );
		$right = $this->el_container(
			array( $text ),
			array(
				'content_width'        => 'full',
				'width'                => array( 'unit' => '%', 'size' => '60' ),
				'flex_justify_content' => 'center',
				'boxed_width'          => null,
			),
			true
		);
		unset( $right['settings']['boxed_width'] );
		return $this->el_container(
			array( $left, $right ),
			array(
				'flex_direction' => 'row',
				'flex_gap'       => array( 'unit' => 'px', 'size' => '20', 'column' => '20', 'row' => '20' ),
			)
		);
	}

	// Tách nội dung Notion thành các khối văn bản / ảnh xen kẽ.
	private function notion_sections( $blocks ) {
		$out = array();
		$buf = '';
		foreach ( (array) $blocks as $b ) {
			$type = isset( $b['type'] ) ? $b['type'] : '';
			if ( 'image' === $type ) {
				if ( '' !== trim( wp_strip_all_tags( $buf ) ) ) { $out[] = array( 'type' => 'text', 'html' => $buf ); }
				$buf = '';
				$src = '';
				if ( isset( $b['image']['file']['url'] ) ) { $src = $b['image']['file']['url']; }
				if ( ! $src && isset( $b['image']['external']['url'] ) ) { $src = $b['image']['external']['url']; }
				if ( $src ) { $out[] = array( 'type' => 'image', 'url' => $src, 'key' => $b['id'] ); }
				continue;
			}
			$buf .= $this->blocks_to_html( array( $b ) );
		}
		if ( '' !== trim( wp_strip_all_tags( $buf ) ) ) { $out[] = array( 'type' => 'text', 'html' => $buf ); }
		return $out;
	}

	// Ảnh Notion → Media Library (có cache theo block id để không tải lại).
	private function el_attachment( $section, $prod_id ) {
		$map = get_post_meta( $prod_id, '_notion_el_media', true );
		if ( ! is_array( $map ) ) { $map = array(); }
		$key = $section['key'];
		$aid = isset( $map[ $key ] ) ? intval( $map[ $key ] ) : 0;
		if ( $aid && ! get_post( $aid ) ) { $aid = 0; }
		if ( ! $aid ) {
			$clean = strtok( $section['url'], '?' );
			$ext   = pathinfo( $clean, PATHINFO_EXTENSION );
			$ext   = $ext ? $ext : 'jpg';
			$aid   = $this->sideload( $section['url'], 'notion-' . substr( md5( $key ), 0, 8 ) . '.' . $ext, $prod_id );
			if ( ! $aid ) { return null; }
			$map[ $key ] = $aid;
			update_post_meta( $prod_id, '_notion_el_media', $map );
		}
		$meta = wp_get_attachment_metadata( $aid );
		return array(
			'id'  => $aid,
			'url' => wp_get_attachment_url( $aid ),
			'w'   => isset( $meta['width'] ) ? intval( $meta['width'] ) : 0,
			'h'   => isset( $meta['height'] ) ? intval( $meta['height'] ) : 0,
		);
	}

	// HTML danh sách vật liệu để đổ vào trường ACF "chọn_vật_liệu".
	private function materials_acf_html( $prod_id ) {
		$materials = get_post_meta( $prod_id, '_notion_materials', true );
		if ( ! is_array( $materials ) || ! $materials ) { return ''; }
		$html = '<div class="cns-materials">';
		foreach ( $materials as $m ) {
			$name  = isset( $m['name'] ) ? $m['name'] : '';
			$code  = isset( $m['code'] ) ? $m['code'] : '';
			$type  = isset( $m['type'] ) ? $m['type'] : '';
			$thumb = ! empty( $m['thumb'] ) ? $m['thumb'] : '';
			$color = isset( $m['color'] ) ? $this->color_hex( $m['color'] ) : '#ddd';
			$swatch = $thumb
				? '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" />'
				: '<span class="cns-mat-color" style="background:' . esc_attr( $color ) . '"></span>';
			$html .= '<div class="cns-mat-item">' . $swatch
				. '<div class="cns-mat-meta"><strong>' . esc_html( $name ) . '</strong>'
				. ( $type ? '<em>' . esc_html( $type ) . '</em>' : '' )
				. ( $code ? '<span>' . esc_html( $code ) . '</span>' : '' )
				. '</div></div>';
		}
		$html .= '</div>';
		return $html;
	}

	private function apply_layout( $prod_id, $page_id, $props ) {
		$sections = $this->notion_sections( $this->fetch_block_children( $page_id ) );
		$data     = array();
		$total    = count( $sections );
		for ( $i = 0; $i < $total; $i++ ) {
			$sec = $sections[ $i ];
			if ( 'text' === $sec['type'] ) {
				$data[] = $this->el_container( array( $this->el_text( $sec['html'] ) ) );
				continue;
			}
			$att = $this->el_attachment( $sec, $prod_id );
			if ( ! $att ) { continue; }
			$portrait = ( $att['h'] > 0 && $att['w'] > 0 && $att['h'] > $att['w'] * 1.15 );
			if ( $portrait && isset( $sections[ $i + 1 ] ) && 'text' === $sections[ $i + 1 ]['type'] ) {
				$img    = $this->el_image( $att, array( 'width' => '400', 'height' => '600' ) );
				$data[] = $this->el_row_image_text( $img, $this->el_text( $sections[ $i + 1 ]['html'] ) );
				$i++;
				continue;
			}
			$data[] = $this->el_container( array( $this->el_image( $att ) ) );
		}

		if ( $data ) {
			update_post_meta( $prod_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
			update_post_meta( $prod_id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $prod_id, '_elementor_template_type', 'product-post' );
			update_post_meta( $prod_id, '_elementor_version', self::EL_VERSION );
			update_post_meta( $prod_id, '_wp_page_template', 'default' );
			delete_post_meta( $prod_id, '_elementor_css' );
			delete_post_meta( $prod_id, '_elementor_element_cache' );
		}

		// Cấu tạo & thông số kĩ thuật.
		$fact_p = $this->prop( $props, self::P_DESC );
		$fact   = isset( $fact_p['rich_text'] ) ? trim( $this->plain_text( $fact_p['rich_text'] ) ) : '';
		$specs  = array();
		$dims   = array();
		foreach ( array( 'Dài' => self::P_LENGTH, 'Rộng' => self::P_WIDTH, 'Cao' => self::P_HEIGHT ) as $label => $pname ) {
			$v = $this->prop( $props, $pname );
			if ( ! empty( $v['number'] ) ) { $dims[] = $label . ' ' . $v['number'] . ' cm'; }
		}
		$w = $this->prop( $props, self::P_WEIGHT );
		if ( $fact ) { $specs[] = $fact; }
		if ( $dims ) { $specs[] = "Kích thước:\r\n\r\n- " . implode( "\r\n- ", $dims ); }
		if ( ! empty( $w['number'] ) ) { $specs[] = 'Trọng lượng: ' . $w['number'] . ' kg'; }
		if ( $specs ) { $this->set_acf( $prod_id, 'kich_thuoc_-_cau_tạo', implode( "\r\n\r\n", $specs ) ); }

		// Tab chọn vật liệu.
		$mat_html = $this->materials_acf_html( $prod_id );
		if ( $mat_html ) { $this->set_acf( $prod_id, 'chọn_vật_liệu', $mat_html ); }

		// Model 3D.
		$m3d = $this->prop( $props, self::P_3D );
		if ( ! empty( $m3d['url'] ) ) { $this->set_acf( $prod_id, '3d_model', $m3d['url'] ); }
	}

	private function save_log() {
		update_option( self::OPT_LOG, array_slice( $this->log, -100 ), false );
	}
}

Colectia_Notion_Sync::init();
register_activation_hook( __FILE__, array( 'Colectia_Notion_Sync', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Colectia_Notion_Sync', 'deactivate' ) );
