<?php
/**
 * WordPress integration tests for NExT_FC2Blog2WP.
 *
 * These exercise the WordPress-dependent code paths (post creation,
 * taxonomy assignment, comments) against a real WP test database.
 *
 * @package NExT_FC2Blog2WP
 */

/**
 * @covers NExT_FC2Blog2WP
 */
class ImportTest extends WP_UnitTestCase {

	/**
	 * @var NExT_FC2Blog2WP
	 */
	private $importer;

	public function set_up() {
		parent::set_up();
		$this->importer = new NExT_FC2Blog2WP();
	}

	public function test_create_post_inserts_post_with_taxonomies_and_meta() {
		$data = array(
			'title'        => 'Integration Title',
			'content'      => '<!-- wp:paragraph --><p>Body text</p><!-- /wp:paragraph -->',
			'date'         => '2026-03-01 00:00:00',
			'category'     => 'TravelCat',
			'tags'         => array( 'alpha', 'beta' ),
			'original_url' => 'https://example.blog.fc2.com/blog-entry-99.html',
		);

		$post_id = $this->importer->createPost( $data );
		$this->assertNotFalse( $post_id );

		$post = get_post( (int) $post_id );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( 'Integration Title', $post->post_title );
		$this->assertSame( 'fc2-entry-99', $post->post_name );
		$this->assertStringContainsString( 'Body text', $post->post_content );
		$this->assertSame( '2026-03-01 00:00:00', $post->post_date );

		$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$this->assertContains( 'TravelCat', $categories );

		$tags = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
		$this->assertContains( 'alpha', $tags );
		$this->assertContains( 'beta', $tags );

		$this->assertSame(
			'https://example.blog.fc2.com/blog-entry-99.html',
			get_post_meta( $post->ID, 'original_url', true )
		);
	}

	public function test_create_comments_adds_only_non_empty_comments() {
		$post_id = self::factory()->post->create();

		$this->importer->createComments(
			(string) $post_id,
			array(
				array(
					'author' => 'Commenter',
					'date'   => '2026-03-02 10:00:00',
					'text'   => 'Nice post',
				),
				array(
					'author' => 'Empty',
					'text'   => '',
				),
			)
		);

		$comments = get_comments( array( 'post_id' => $post_id ) );
		$this->assertCount( 1, $comments );
		$this->assertSame( 'Nice post', $comments[0]->comment_content );
		$this->assertSame( '1', (string) $comments[0]->comment_approved );
	}
}
