<?php
/**
 * Unit tests for FC2HtmlParser.
 *
 * @package NExT_FC2Blog2WP
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers FC2HtmlParser
 */
class FC2HtmlParserTest extends TestCase {

	/**
	 * @var FC2HtmlParser
	 */
	private $parser;

	protected function setUp(): void {
		parent::setUp();
		$this->parser = new FC2HtmlParser();
	}

	public function test_extract_title_from_entry_title_heading() {
		$html = '<html><body><h1 class="entryTitle">テスト記事のタイトル</h1></body></html>';
		$this->assertSame( 'テスト記事のタイトル', $this->parser->extractTitle( $html ) );
	}

	public function test_extract_title_returns_empty_when_missing() {
		$html = '<html><body><p>no title here</p></body></html>';
		$this->assertSame( '', $this->parser->extractTitle( $html ) );
	}

	public function test_extract_body_returns_inner_html() {
		$html = '<html><body><div class="l-entryBody"><p>本文テキスト</p></div></body></html>';
		$body = $this->parser->extractBody( $html );
		$this->assertStringContainsString( '<p>', $body );
		$this->assertStringContainsString( '本文テキスト', $body );
	}

	public function test_extract_tags_collects_anchor_text() {
		$html = '<html><body><ul class="entryTag_list">'
			. '<li><a href="#">猫</a></li>'
			. '<li><a href="#">旅行</a></li>'
			. '</ul></body></html>';
		$this->assertSame( array( '猫', '旅行' ), $this->parser->extractTags( $html ) );
	}

	public function test_extract_date_from_split_spans() {
		$html = '<html><body><div class="entryDate">'
			. '<span class="entryDate_y">2026</span>'
			. '<span class="entryDate_m">3</span>'
			. '<span class="entryDate_d">1</span>'
			. '</div></body></html>';
		$this->assertSame( '2026-03-01 00:00:00', $this->parser->extractDate( $html ) );
	}

	public function test_extract_monthly_archive_urls_resolves_relative() {
		$html = '<html><body><a href="blog-date-202603.html">2026/03</a></body></html>';
		$urls = $this->parser->extractMonthlyArchiveUrls( $html, 'https://example.blog.fc2.com/' );
		$this->assertSame(
			array( 'https://example.blog.fc2.com/blog-date-202603.html' ),
			$urls
		);
	}

	public function test_extract_post_urls_filters_and_resolves() {
		$html = '<html><body>'
			. '<a href="blog-entry-12.html">post 12</a>'
			. '<a href="blog-entry-34.html?foo=bar">post 34</a>'
			. '<a href="blog-category-1.html">not a post</a>'
			. '</body></html>';
		$urls = $this->parser->extractPostUrls( $html, 'https://example.blog.fc2.com/' );
		$this->assertSame(
			array(
				'https://example.blog.fc2.com/blog-entry-12.html',
				'https://example.blog.fc2.com/blog-entry-34.html',
			),
			$urls
		);
	}

	public function test_extract_post_urls_deduplicates() {
		$html = '<html><body>'
			. '<a href="blog-entry-12.html">a</a>'
			. '<a href="blog-entry-12.html#comments">b</a>'
			. '</body></html>';
		$urls = $this->parser->extractPostUrls( $html, 'https://example.blog.fc2.com/' );
		$this->assertSame(
			array( 'https://example.blog.fc2.com/blog-entry-12.html' ),
			$urls
		);
	}
}
