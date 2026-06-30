<?php
/**
 * Unit tests for the pure logic of NExT_FC2Blog2WP
 * (URL handling, block conversion, image extraction).
 *
 * @package NExT_FC2Blog2WP
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers NExT_FC2Blog2WP
 */
class BlockConverterTest extends TestCase {

	/**
	 * @var NExT_FC2Blog2WP
	 */
	private $importer;

	protected function setUp(): void {
		parent::setUp();
		$this->importer = new NExT_FC2Blog2WP();
	}

	public function test_get_archive_index_url_appends_trailing_slash() {
		$this->assertSame(
			'https://example.blog.fc2.com/',
			$this->importer->getArchiveIndexUrl( 'https://example.blog.fc2.com' )
		);
	}

	public function test_get_archive_index_url_keeps_existing_slash() {
		$this->assertSame(
			'https://example.blog.fc2.com/',
			$this->importer->getArchiveIndexUrl( 'https://example.blog.fc2.com/' )
		);
	}

	public function test_get_archive_index_url_rejects_non_fc2() {
		$this->assertFalse( $this->importer->getArchiveIndexUrl( 'https://example.com/' ) );
	}

	public function test_convert_to_blocks_empty_input() {
		$this->assertSame( '', $this->importer->convertToBlocks( '   ' ) );
	}

	public function test_convert_to_blocks_paragraph() {
		$blocks = $this->importer->convertToBlocks( '<p>Hello world</p>' );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $blocks );
		$this->assertStringContainsString( 'Hello world', $blocks );
	}

	public function test_convert_to_blocks_heading_level() {
		$blocks = $this->importer->convertToBlocks( '<h3>Section</h3>' );
		$this->assertStringContainsString( '<!-- wp:heading {"level":3} -->', $blocks );
	}

	public function test_convert_to_blocks_unordered_list() {
		$blocks = $this->importer->convertToBlocks( '<ul><li>a</li><li>b</li></ul>' );
		$this->assertStringContainsString( '<!-- wp:list -->', $blocks );
	}

	public function test_convert_to_blocks_image() {
		$blocks = $this->importer->convertToBlocks( '<p><img src="https://blog-imgs-1.fc2.com/a/b/c.jpg" alt="cap"/></p>' );
		$this->assertStringContainsString( '<!-- wp:image -->', $blocks );
		$this->assertStringContainsString( 'https://blog-imgs-1.fc2.com/a/b/c.jpg', $blocks );
	}

	public function test_get_images_url_collects_fc2_images_only() {
		$content = '<p><img src="https://blog-imgs-1.fc2.com/a/b/c.jpg"></p>'
			. '<p><img src="https://example.com/not-fc2.png"></p>'
			. '<p><img src="data:image/png;base64,AAAA"></p>';
		$images = $this->importer->getImagesUrl( $content );
		$this->assertSame(
			array( array( 'src' => 'https://blog-imgs-1.fc2.com/a/b/c.jpg' ) ),
			$images
		);
	}

	public function test_get_images_url_deduplicates() {
		$content = '<img src="https://blog-imgs-1.fc2.com/x.jpg">'
			. '<img src="https://blog-imgs-1.fc2.com/x.jpg">';
		$images = $this->importer->getImagesUrl( $content );
		$this->assertCount( 1, $images );
	}
}
