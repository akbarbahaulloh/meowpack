<?php
/**
 * Frontend Enhancer — Post Meta Bar, Table of Contents, Related Posts.
 *
 * @package MeowPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MeowPack_Frontend_Enhancer {

	public function __construct() {
		// Hook late to ensure we run after standard formatting.
		add_filter( 'the_content', array( $this, 'enhance_content' ), 90 );
		add_shortcode( 'meow_toc', array( $this, 'shortcode_toc' ) );
	}

	/**
	 * Main entrance point for enhancing post content.
	 */
	public function enhance_content( $content ) {
		if ( ! is_single() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();

		// 1. Table of Contents
		$toc_mode = MeowPack_Database::get_setting( 'show_toc', 'auto' );
		$toc_html = '';
		if ( 'hidden' !== $toc_mode ) {
			$toc_data = $this->generate_toc( $content );
			if ( $toc_data['has_toc'] ) {
				$content  = $toc_data['content']; // content with added IDs.
				$toc_html = $this->build_toc_html( $toc_data['items'] );
			}
		}

		// Inject Auto ToC
		if ( 'auto' === $toc_mode && $toc_html ) {
			// Prepend before first H2
			$content = preg_replace( '/(<h2.*?>)/i', $toc_html . '$1', $content, 1 );
			// If no H2 was found, just prepend it at top
			if ( strpos( $content, 'meowpack-toc' ) === false ) {
				$content = $toc_html . $content;
			}
		}

		// 2. Post Meta Bar (Views + Reading Time)
		$meta_mode = MeowPack_Database::get_setting( 'show_post_meta_bar', 'top' );
		if ( 'hidden' !== $meta_mode ) {
			$meta_html = $this->build_post_meta_html( $content, $post_id );
			if ( $meta_html ) {
				if ( 'top' === $meta_mode ) {
					$content = $meta_html . $content;
				} else {
					$content = $content . $meta_html;
				}
			}
		}

		// 3. Related Posts
		$related_mode = MeowPack_Database::get_setting( 'enable_related_posts', '1' );
		if ( '1' === $related_mode ) {
			$content .= $this->build_related_posts_html( $post_id );
		}

		return $content;
	}

	/**
	 * Build the Post Meta Bar (Views + Estimated Reading Time).
	 */
	private function build_post_meta_html( $content, $post_id ) {
		$post_type = get_post_type( $post_id );
		
		$views_allowed = explode( ',', MeowPack_Database::get_setting( 'show_views_on', 'post,page' ) );
		$read_allowed  = explode( ',', MeowPack_Database::get_setting( 'show_reading_time_on', 'post,page' ) );

		$show_views = in_array( $post_type, $views_allowed, true ) && '1' === MeowPack_Database::get_setting( 'enable_view_counter', '1' );
		$show_read  = in_array( $post_type, $read_allowed, true ) && '1' === MeowPack_Database::get_setting( 'enable_reading_time', '1' );

		if ( ! $show_views && ! $show_read ) {
			return '';
		}

		$html = '<div class="meowpack-post-meta" style="display:flex; gap:16px; padding:12px 16px; background:rgba(0,0,0,0.03); border-radius:8px; margin:20px 0; font-size:0.9em; font-weight:500;">';

		if ( $show_views ) {
			// Use the Top-10 dynamic document.write approach to bypass cache completely
			$ajax_url = admin_url( 'admin-ajax.php' );
			$script_tag = sprintf( '<script type="text/javascript" data-cfasync="false" src="%s?action=meowpack_get_views&post_id=%d"></script>', $ajax_url, $post_id );
			$html .= '<span title="Berdasarkan lalu lintas pengunjung">' . $script_tag . '</span>';
		}

		if ( $show_read ) {
			$word_count = str_word_count( wp_strip_all_tags( $content ) );
			$wpm = 200;
			$minutes = max( 1, ceil( $word_count / $wpm ) );
			
			$format = MeowPack_Database::get_setting( 'reading_time_format_text', '⏱️ Estimasi Baca: {minutes} Menit' );
			$reading_txt = str_replace( '{minutes}', $minutes, $format );
			
			$html .= '<span title="Berdasarkan 200 kata per menit">' . esc_html( $reading_txt ) . '</span>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Parse content for H2 and H3 tags and assign random IDs if missing.
	 *
	 * @return array { has_toc: bool, content: string, items: array }
	 */
	private function generate_toc( $content ) {
		$items = array();
		
		// Match H2 and H3.
		if ( preg_match_all( '/<(h[23])([^>]*)>(.*?)<\/\1>/i', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$tag   = strtolower( $match[1] );
				$attrs = $match[2];
				$text  = wp_strip_all_tags( $match[3] );
				
				// Ensure it has an ID, if not, generate one and inject it into content.
				if ( preg_match( '/id=[\'"]([^\'"]+)[\'"]/i', $attrs, $id_match ) ) {
					$id = $id_match[1];
				} else {
					$id = sanitize_title( $text );
					if ( empty( $id ) ) $id = 'heading-' . wp_rand( 1000, 9999 );
					
					// Replace tag in content with injected ID.
					$new_tag = "<{$tag} id=\"{$id}\"{$attrs}>{$match[3]}</{$tag}>";
					// Only replace first occurrence.
					$pos = strpos( $content, $match[0] );
					if ( $pos !== false ) {
						$content = substr_replace( $content, $new_tag, $pos, strlen( $match[0] ) );
					}
				}

				$items[] = array(
					'level' => ( 'h2' === $tag ) ? 2 : 3,
					'id'    => $id,
					'text'  => $text,
				);
			}
		}

		return array(
			'has_toc' => count( $items ) >= 2, // minimum 2 items to show TOC
			'content' => $content,
			'items'   => $items,
		);
	}

	/**
	 * Render the TOC HTML box.
	 */
	private function build_toc_html( $items ) {
		if ( empty( $items ) ) return '';

		// Enqueue public styles.
		wp_enqueue_style( 'meowpack-public', MEOWPACK_URL . 'public/assets/meowpack-public.css', array(), MEOWPACK_VERSION );

		$html = '<div class="meowpack-toc">';
		$html .= '<h3 class="meowpack-toc__title">📑 ' . esc_html__( 'Daftar Isi', 'meowpack' ) . '</h3>';
		$html .= '<ul class="meowpack-toc__list">';
		
		foreach ( $items as $item ) {
			$class = ( $item['level'] === 3 ) ? 'meowpack-toc__item meowpack-toc__item--h3' : 'meowpack-toc__item';
			$html .= '<li class="' . $class . '">';
			$html .= '<a href="#' . esc_attr( $item['id'] ) . '" class="meowpack-toc__link">' . esc_html( $item['text'] ) . '</a>';
			$html .= '</li>';
		}
		
		$html .= '</ul></div>';
		return $html;
	}

	/**
	 * Build Related Posts HTML block.
	 */
	private function build_related_posts_html( $post_id ) {
		$categories = get_the_category( $post_id );
		if ( empty( $categories ) ) {
			return '';
		}

		$cat_ids = array_map( function( $cat ) { return $cat->term_id; }, $categories );

		$args = array(
			'category__in'   => $cat_ids,
			'post__not_in'   => array( $post_id ),
			'posts_per_page' => 5,
			'ignore_sticky_posts' => 1,
			'no_found_rows'  => true,
		);

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return '';
		}

		$html = '<div class="meowpack-related-posts" style="margin-top:40px; padding-top:20px; border-top:1px solid #eee; clear:both;">';
		$html .= '<h3 style="margin:0 0 20px 0; padding:0; font-size:1.5rem; font-weight:bold; color:#333;">' . esc_html__( 'Tulisan Terkait', 'meowpack' ) . '</h3>';
		$html .= '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:15px; margin:0; padding:0;">';

		while ( $query->have_posts() ) {
			$query->the_post();
			
			$thumb = has_post_thumbnail() ? get_the_post_thumbnail_url( get_the_ID(), 'medium' ) : '';

			$html .= '<a href="' . get_permalink() . '" style="display:flex; flex-direction:column; text-decoration:none; color:inherit; border-radius:10px; overflow:hidden; border:1px solid #edf2f7; background:#fff; box-shadow:0 2px 5px rgba(0,0,0,0.04); transition:all 0.2s; height:100%;">';
			if ( $thumb ) {
				$html .= '<div style="height:150px; background-size:cover; background-position:center; background-image:url(\'' . esc_url( $thumb ) . '\');"></div>';
			}
			$html .= '<div style="padding:15px; flex-grow:1; display:flex; flex-direction:column; justify-content:center;">';
			$html .= '<h4 style="margin:0; font-size:1.05rem; line-height:1.5; color:#2d3748; font-weight:600;">' . get_the_title() . '</h4>';
			$html .= '<div style="font-size:0.85rem; color:#718096; margin-top:8px;">' . get_the_date() . '</div>';
			$html .= '</div>';
			$html .= '</a>';
		}

		$html .= '</div></div>';
		wp_reset_postdata();

		return $html;
	}

	/**
	 * Shortcode callback for [meow_toc]
	 */
	public function shortcode_toc() {
		// Only output if manual is selected, otherwise auto already handled it.
		// For simplicity, if shortcode is used, just fetch content and generate.
		// Note: running the filter twice inside shortcode is slow, so we rely on the global post content.
		$post = get_post();
		if ( ! $post ) return '';

		$toc_data = $this->generate_toc( $post->post_content );
		if ( $toc_data['has_toc'] ) {
			return $this->build_toc_html( $toc_data['items'] );
		}
		return '';
	}
}
