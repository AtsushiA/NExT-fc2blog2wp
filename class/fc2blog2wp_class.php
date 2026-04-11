<?php
/**
 * FC2Blog2WP core class
 *
 * @license GPL-2.0-or-later
 */

class FC2Blog2WP {

	/**
	 * HTML Parser instance
	 * @var FC2HtmlParser
	 */
	private $parser;

	/**
	 * Blog ID extracted from blog URL (e.g. "recordeurasia")
	 * @var string
	 */
	private $blogId;

	/**
	 * Temporary directory path
	 * @var string
	 */
	private $tempDir;

	/**
	 * Progress file path
	 * @var string
	 */
	private $progressFile;

	/**
	 * Constructor
	 */
	public function __construct() {
		require_once dirname( __FILE__ ) . '/fc2_html_parser.php';
		$this->parser       = new FC2HtmlParser();
		$this->blogId       = '';
		$this->tempDir      = '';
		$this->progressFile = '';
	}

	/**
	 * Extract blog ID from URL and initialize temp directory
	 *
	 * @param string $blogUrl e.g. https://example.blog.fc2.com/
	 * @return bool
	 */
	public function initTempDir( $blogUrl ) {
		// Extract blog ID from fc2.com URL
		// Patterns: https://example.blog.fc2.com/ or https://blog.fc2.com/example/
		if ( preg_match( '/\/\/([^.]+)\.blog\.fc2\.com/', $blogUrl, $m ) ) {
			$this->blogId = $m[1];
		} elseif ( preg_match( '/blog\.fc2\.com\/([^\/]+)/', $blogUrl, $m ) ) {
			$this->blogId = $m[1];
		} else {
			return false;
		}

		$wp_content_dir   = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		$this->tempDir    = $wp_content_dir . '/fc2blog2wp/' . $this->blogId;

		if ( ! file_exists( $this->tempDir ) ) {
			if ( ! wp_mkdir_p( $this->tempDir ) ) {
				@mkdir( $this->tempDir, 0755, true );
			}
		}

		$this->progressFile = $this->tempDir . '/progress.json';

		return file_exists( $this->tempDir );
	}

	/**
	 * Get temp directory path
	 * @return string
	 */
	public function getTempDir() {
		return $this->tempDir;
	}

	/**
	 * Load progress data
	 * @return array
	 */
	public function loadProgress() {
		if ( ! file_exists( $this->progressFile ) ) {
			return [
				'completed_posts' => [],
				'total_posts'     => 0,
				'started_at'      => date( 'Y-m-d H:i:s' ),
			];
		}

		$json = file_get_contents( $this->progressFile );
		$data = json_decode( $json, true );

		return $data ? $data : [ 'completed_posts' => [], 'total_posts' => 0 ];
	}

	/**
	 * Save progress data
	 * @param array $progress
	 */
	public function saveProgress( $progress ) {
		$progress['last_updated'] = date( 'Y-m-d H:i:s' );
		file_put_contents( $this->progressFile, json_encode( $progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), LOCK_EX );
	}

	/**
	 * Reset progress
	 */
	public function resetProgress() {
		if ( file_exists( $this->progressFile ) ) {
			@unlink( $this->progressFile );
		}
	}

	/**
	 * Check if a post URL has already been completed
	 * @param string $postUrl
	 * @param array $progress
	 * @return bool
	 */
	public function isPostCompleted( $postUrl, $progress ) {
		return in_array( $postUrl, $progress['completed_posts'] );
	}

	/**
	 * Mark a post URL as completed
	 * @param string $postUrl
	 * @param array &$progress
	 */
	public function markPostCompleted( $postUrl, &$progress ) {
		if ( ! in_array( $postUrl, $progress['completed_posts'] ) ) {
			$progress['completed_posts'][] = $postUrl;
			$this->saveProgress( $progress );
		}
	}

	/**
	 * Get archive index URL (= the blog top page)
	 * @param string $blogUrl
	 * @return string|false
	 */
	public function getArchiveIndexUrl( $blogUrl ) {
		if ( strpos( $blogUrl, 'fc2.com' ) === false ) {
			return false;
		}

		// Normalize: ensure trailing slash
		if ( ! preg_match( '/\/$/', $blogUrl ) ) {
			$blogUrl .= '/';
		}

		return $blogUrl;
	}

	/**
	 * Get all monthly archive URLs from the blog top page sidebar
	 *
	 * @param string $indexUrl
	 * @return array
	 */
	public function getArchivesUrl( $indexUrl ) {
		$archivesUrl = [ $indexUrl ];

		$html = $this->parser->fetchHtml( $indexUrl );
		if ( $html === false ) {
			return $archivesUrl;
		}

		$monthlyUrls = $this->parser->extractMonthlyArchiveUrls( $html, $indexUrl );

		foreach ( $monthlyUrls as $url ) {
			if ( ! in_array( $url, $archivesUrl ) ) {
				$archivesUrl[] = $url;
			}
		}

		return $archivesUrl;
	}

	/**
	 * Get all post URLs from archive pages
	 * Handles FC2 autopager pagination (?page=N&more)
	 *
	 * @param array $archivesUrl
	 * @return array
	 */
	public function getPostsUrl( $archivesUrl ) {
		$postsUrl = [];
		$seen     = [];

		foreach ( $archivesUrl as $archiveUrl ) {
			// Fetch pages with pagination until no new posts found
			for ( $page = 1; $page <= 20; $page++ ) {
				$url = $page === 1 ? $archiveUrl : $archiveUrl . '?page=' . $page . '&more';

				$html = $this->parser->fetchHtml( $url );
				if ( $html === false ) {
					break;
				}

				$newUrls = $this->parser->extractPostUrls( $html, $archiveUrl );

				$added = 0;
				foreach ( $newUrls as $postUrl ) {
					if ( ! isset( $seen[ $postUrl ] ) ) {
						$postsUrl[]          = $postUrl;
						$seen[ $postUrl ]    = true;
						$added++;
					}
				}

				// Stop paginating if no new posts found on this page
				if ( $added === 0 ) {
					break;
				}
			}
		}

		return array_values( $postsUrl );
	}

	/**
	 * Fetch and parse a single post page
	 *
	 * @param string $postUrl
	 * @return array|false Post data array or false on failure
	 */
	public function getPostData( $postUrl ) {
		$html = $this->parser->fetchHtml( $postUrl );
		if ( $html === false ) {
			return false;
		}

		$title    = $this->parser->extractTitle( $html );
		$body     = $this->parser->extractBody( $html );
		$date     = $this->parser->extractDate( $html );
		$category = $this->parser->extractCategory( $html );
		$tags     = $this->parser->extractTags( $html );
		$comments = $this->parser->extractComments( $html );

		if ( empty( $title ) && empty( $body ) ) {
			return false;
		}

		return [
			'title'        => $title,
			'content'      => $this->convertToBlocks( $body ),
			'raw_content'  => $body,
			'date'         => $date,
			'category'     => $category,
			'tags'         => $tags,
			'comments'     => $comments,
			'original_url' => $postUrl,
		];
	}

	/**
	 * Convert HTML content to Gutenberg blocks
	 *
	 * @param string $html
	 * @return string Block markup
	 */
	public function convertToBlocks( $html ) {
		if ( empty( trim( $html ) ) ) {
			return '';
		}

		$dom = new DOMDocument();
		@$dom->loadHTML(
			'<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return '<!-- wp:html -->' . $html . '<!-- /wp:html -->';
		}

		return trim( $this->processChildNodes( $dom, $body ) );
	}

	/**
	 * Process child nodes, grouping consecutive inline elements into paragraph blocks.
	 *
	 * @param DOMDocument $dom
	 * @param DOMNode $parentNode
	 * @return string
	 */
	private function processChildNodes( $dom, $parentNode ) {
		$blocks       = '';
		$inline_nodes = array();

		$block_tags = array( 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'blockquote', 'table', 'figure', 'pre', 'hr' );

		foreach ( $parentNode->childNodes as $node ) {
			if ( $node->nodeType === XML_TEXT_NODE ) {
				if ( '' !== trim( $node->textContent ) ) {
					$inline_nodes[] = $node;
				}
				continue;
			}

			if ( $node->nodeType !== XML_ELEMENT_NODE ) {
				continue;
			}

			$tag = strtolower( $node->nodeName );

			// <br> acts as a paragraph separator within inline content.
			if ( 'br' === $tag ) {
				if ( ! empty( $inline_nodes ) ) {
					$inline_nodes[] = $node;
				}
				continue;
			}

			// <a><img/></a> — link wrapping a single image: treat as image block.
			if ( 'a' === $tag ) {
				$imgs = $node->getElementsByTagName( 'img' );
				if ( $imgs->length === 1 && '' === trim( $node->textContent ) ) {
					if ( ! empty( $inline_nodes ) ) {
						$blocks      .= $this->inlinesToParagraphs( $dom, $inline_nodes );
						$inline_nodes = array();
					}
					$blocks .= $this->imgNodeToBlock( $imgs->item( 0 ) );
					continue;
				}
				// Linked text — treat as inline.
				$inline_nodes[] = $node;
				continue;
			}

			if ( 'img' === $tag || in_array( $tag, $block_tags, true ) ) {
				if ( ! empty( $inline_nodes ) ) {
					$blocks      .= $this->inlinesToParagraphs( $dom, $inline_nodes );
					$inline_nodes = array();
				}
				$blocks .= $this->nodeToBlock( $dom, $node );
				continue;
			}

			// All other tags (span, strong, em, b, i, etc.) — treat as inline.
			$inline_nodes[] = $node;
		}

		if ( ! empty( $inline_nodes ) ) {
			$blocks .= $this->inlinesToParagraphs( $dom, $inline_nodes );
		}

		return $blocks;
	}

	/**
	 * Convert a list of inline nodes (split by <br>) into paragraph blocks.
	 *
	 * @param DOMDocument $dom
	 * @param array $nodes
	 * @return string
	 */
	private function inlinesToParagraphs( $dom, $nodes ) {
		$groups  = array();
		$current = array();

		foreach ( $nodes as $node ) {
			if ( $node->nodeType === XML_ELEMENT_NODE && 'br' === strtolower( $node->nodeName ) ) {
				if ( ! empty( $current ) ) {
					$groups[] = $current;
					$current  = array();
				}
			} else {
				$current[] = $node;
			}
		}
		if ( ! empty( $current ) ) {
			$groups[] = $current;
		}

		$result = '';
		foreach ( $groups as $group ) {
			$inner = '';
			foreach ( $group as $node ) {
				$inner .= $dom->saveHTML( $node );
			}
			$inner = trim( $inner );
			if ( '' !== $inner ) {
				$result .= '<!-- wp:paragraph --><p>' . $inner . '</p><!-- /wp:paragraph -->' . "\n";
			}
		}

		return $result;
	}

	/**
	 * Convert a single block-level DOM node to a Gutenberg block
	 *
	 * @param DOMDocument $dom
	 * @param DOMNode $node
	 * @return string
	 */
	private function nodeToBlock( $dom, $node ) {
		if ( $node->nodeType !== XML_ELEMENT_NODE ) {
			return '';
		}

		$tag   = strtolower( $node->nodeName );
		$outer = $dom->saveHTML( $node );
		$inner = '';
		foreach ( $node->childNodes as $child ) {
			$inner .= $dom->saveHTML( $child );
		}

		switch ( $tag ) {
			case 'div':
				return $this->processChildNodes( $dom, $node );

			case 'p':
				// Check if paragraph contains only an image.
				$img_nodes = $node->getElementsByTagName( 'img' );
				if ( $img_nodes->length === 1 && '' === trim( $node->textContent ) ) {
					return $this->imgNodeToBlock( $img_nodes->item( 0 ) );
				}
				if ( '' === trim( $inner ) ) {
					return '';
				}
				return '<!-- wp:paragraph -->' . $outer . '<!-- /wp:paragraph -->' . "\n";

			case 'img':
				return $this->imgNodeToBlock( $node );

			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$level = (int) substr( $tag, 1 );
				return '<!-- wp:heading {"level":' . $level . '} -->' . $outer . '<!-- /wp:heading -->' . "\n";

			case 'ul':
				return '<!-- wp:list -->' . $outer . '<!-- /wp:list -->' . "\n";

			case 'ol':
				return '<!-- wp:list {"ordered":true} -->' . $outer . '<!-- /wp:list -->' . "\n";

			case 'blockquote':
				return '<!-- wp:quote --><blockquote class="wp-block-quote">' . $inner . '</blockquote><!-- /wp:quote -->' . "\n";

			case 'hr':
				return '<!-- wp:separator --><hr class="wp-block-separator"/><!-- /wp:separator -->' . "\n";

			default:
				$trimmed = trim( $outer );
				if ( '' === $trimmed ) {
					return '';
				}
				return '<!-- wp:html -->' . $outer . '<!-- /wp:html -->' . "\n";
		}
	}

	/**
	 * Convert an img DOM node to a wp:image block
	 *
	 * @param DOMElement $img_node
	 * @return string
	 */
	private function imgNodeToBlock( $img_node ) {
		$src = $img_node->getAttribute( 'src' );
		$alt = $img_node->getAttribute( 'alt' );

		if ( empty( $src ) ) {
			return '';
		}

		return '<!-- wp:image -->' . "\n" .
			'<figure class="wp-block-image"><img src="' . esc_attr( $src ) . '" alt="' . esc_attr( $alt ) . '"/></figure>' . "\n" .
			'<!-- /wp:image -->' . "\n";
	}

	/**
	 * Create a WordPress post from post data
	 *
	 * @param array $postData
	 * @return string|false Post ID or false on failure
	 */
	public function createPost( $postData ) {
		// Extract entry number from URL for slug
		$slug = '';
		if ( preg_match( '/blog-entry-(\d+)\.html/', $postData['original_url'], $m ) ) {
			$slug = 'fc2-entry-' . $m[1];
		}

		$post_args = array(
			'post_title'   => $postData['title'],
			'post_content' => $postData['content'],
			'post_type'    => 'post',
			'post_status'  => isset( $postData['status'] ) ? $postData['status'] : 'publish',
			'post_date'    => $postData['date'],
		);

		if ( $slug ) {
			$post_args['post_name'] = $slug;
		}

		if ( ! empty( $postData['excerpt'] ) ) {
			$post_args['post_excerpt'] = $postData['excerpt'];
		}

		$post_id = wp_insert_post( $post_args );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		// Set category.
		if ( ! empty( $postData['category'] ) ) {
			$category = get_term_by( 'name', $postData['category'], 'category' );
			if ( $category ) {
				$cat_id = $category->term_id;
			} else {
				$result = wp_insert_term( $postData['category'], 'category' );
				$cat_id = ! is_wp_error( $result ) ? $result['term_id'] : 0;
			}
			if ( $cat_id ) {
				wp_set_post_categories( $post_id, array( $cat_id ) );
			}
		}

		// Set tags.
		if ( ! empty( $postData['tags'] ) ) {
			wp_set_post_tags( $post_id, $postData['tags'] );
		}

		// Save original URL as custom field.
		update_post_meta( $post_id, 'original_url', $postData['original_url'] );

		return (string) $post_id;
	}

	/**
	 * Extract FC2 image URLs from post content
	 * Matches src attributes containing blog-imgs-*.fc2.com
	 *
	 * @param string $postContent Raw HTML content
	 * @return array Array of image data [['src' => url], ...]
	 */
	public function getImagesUrl( $postContent ) {
		$imagesUrl = [];
		$seen      = [];

		$dom = new DOMDocument();
		@$dom->loadHTML(
			mb_convert_encoding( $postContent, 'HTML-ENTITIES', 'UTF-8' ),
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		$xpath  = new DOMXPath( $dom );
		$images = $xpath->query( '//img' );

		foreach ( $images as $img ) {
			$src = $img->getAttribute( 'src' );

			if ( empty( $src ) || strpos( $src, 'data:' ) === 0 ) {
				continue;
			}

			// Only import FC2 CDN images
			if ( strpos( $src, 'fc2.com' ) === false ) {
				continue;
			}

			if ( isset( $seen[ $src ] ) ) {
				continue;
			}

			$seen[ $src ] = true;
			$imagesUrl[]  = [ 'src' => $src ];
		}

		return $imagesUrl;
	}

	/**
	 * Import images to WordPress media library
	 *
	 * @param string $postId
	 * @param array $imagesUrl
	 * @return array Map of old_url => ['url' => new_url, 'id' => attachment_id]
	 */
	public function importImage( $postId, $imagesUrl ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$imported = array();

		foreach ( $imagesUrl as $image ) {
			$src = isset( $image['src'] ) ? $image['src'] : '';
			if ( empty( $src ) ) {
				continue;
			}

			$tmp = download_url( $src );
			if ( is_wp_error( $tmp ) ) {
				continue;
			}

			$file_array = array(
				'name'     => basename( wp_parse_url( $src, PHP_URL_PATH ) ),
				'tmp_name' => $tmp,
			);

			$attachment_id = media_handle_sideload( $file_array, (int) $postId );
			wp_delete_file( $tmp );

			if ( is_wp_error( $attachment_id ) ) {
				continue;
			}

			$new_url = wp_get_attachment_url( $attachment_id );

			if ( $new_url ) {
				$imported[ $src ] = array(
					'url' => $new_url,
					'id'  => (int) $attachment_id,
				);
			}
		}

		return $imported;
	}

	/**
	 * Replace old image URLs with new WordPress media URLs in post content
	 * Also converts img tags to proper wp:image blocks
	 *
	 * @param string $postId
	 * @param array $importedImages [old_url => ['url' => new_url, 'id' => attachment_id]]
	 */
	public function searchReplace( $postId, $importedImages ) {
		if ( empty( $importedImages ) ) {
			return;
		}

		$current_content = get_post_field( 'post_content', (int) $postId );

		if ( false === $current_content || '' === $current_content ) {
			return;
		}

		$new_content = $current_content;

		foreach ( $importedImages as $old_url => $image_data ) {
			$new_url       = $image_data['url'];
			$attachment_id = $image_data['id'];

			$replacement = '<!-- wp:image {"id":' . $attachment_id . '} -->' . "\n" .
				'<figure class="wp-block-image"><img src="' . $new_url . '" alt="" class="wp-image-' . $attachment_id . '"/></figure>' . "\n" .
				'<!-- /wp:image -->';

			// Replace wp:image block with old src.
			$new_content = preg_replace(
				'/<!\-\- wp:image \-\->\s*<figure[^>]*><img[^>]*src=["\']' . preg_quote( $old_url, '/' ) . '["\'][^>]*><\/figure>\s*<!\-\- \/wp:image \-\->/is',
				$replacement,
				$new_content
			);

			// Also replace any remaining bare img tags or plain URLs.
			$new_content = str_replace( $old_url, $new_url, $new_content );
		}

		if ( $current_content !== $new_content ) {
			wp_update_post(
				array(
					'ID'           => (int) $postId,
					'post_content' => $new_content,
				)
			);
		}
	}

	/**
	 * Create comments for a post
	 *
	 * @param string $postId
	 * @param array $commentsData
	 */
	public function createComments( $postId, $commentsData ) {
		foreach ( $commentsData as $comment ) {
			$author = isset( $comment['author'] ) ? $comment['author'] : '';
			$date   = isset( $comment['date'] ) ? $comment['date'] : gmdate( 'Y-m-d H:i:s' );
			$text   = isset( $comment['text'] ) ? $comment['text'] : '';

			if ( empty( $text ) ) {
				continue;
			}

			wp_insert_comment(
				array(
					'comment_post_ID'  => (int) $postId,
					'comment_content'  => $text,
					'comment_author'   => $author,
					'comment_date'     => $date,
					'comment_approved' => 1,
				)
			);
		}
	}
}
