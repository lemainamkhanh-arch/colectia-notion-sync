<?php
/**
 * Template dùng chung cho trang Vật liệu (single, archive, taxonomy).
 * COLECTIA Notion Sync 1.9.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

if ( class_exists( 'Colectia_Notion_Sync' ) ) {
	Colectia_Notion_Sync::init()->render_material_page();
}

get_footer();
