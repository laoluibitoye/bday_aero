<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box( 'bday-co-authors', 'Co-Authors', 'bday_co_authors_metabox', 'post', 'side' );
	}
);

/**
 * Anyone who can actually be credited as a WordPress author (post_author
 * itself only ever offers the same three roles via wp_dropdown_users'
 * default capability check) — same eligible set, so a searched-for staff
 * co-author is never someone the native Author field couldn't have picked
 * anyway.
 *
 * @return array{ID:int,display_name:string}[]
 */
function bday_co_author_candidates(): array {
	return get_users(
		array(
			'role__in' => array( 'administrator', 'editor', 'author', 'contributor' ),
			'orderby'  => 'display_name',
			'order'    => 'ASC',
			'fields'   => array( 'ID', 'display_name' ),
		)
	);
}

/**
 * Normalizes one stored _bday_co_authors entry into a display-ready
 * shape. Editor-requested rework (2026-09-08): most bylined writers
 * aren't staff with an account here, so a co-author is now either a real
 * WP user (searched for) or a freeform guest name + optional link — the
 * same two fields the old, separate "Custom Author Byline" field
 * offered, just per co-author instead of a single site-wide override.
 *
 * Transparently upgrades the pre-rework format (_bday_co_authors was a
 * plain array of user IDs) so every already-published post keeps
 * rendering correctly with no migration step — a bare integer/numeric
 * string is read as {type: user, id: that}.
 *
 * @param mixed $entry
 * @return array{type:string,id:?int,name:string,url:string}|null null if
 *   this entry doesn't resolve to anything renderable (e.g. a user id
 *   that's since been deleted).
 */
function bday_co_author_normalize( $entry ): ?array {
	if ( is_numeric( $entry ) ) {
		$entry = array( 'type' => 'user', 'id' => (int) $entry );
	}
	if ( ! is_array( $entry ) || empty( $entry['type'] ) ) {
		return null;
	}

	if ( 'user' === $entry['type'] ) {
		$user = get_userdata( (int) ( $entry['id'] ?? 0 ) );
		if ( ! $user ) {
			return null;
		}
		return array(
			'type' => 'user',
			'id'   => (int) $user->ID,
			'name' => $user->display_name,
			'url'  => get_author_posts_url( $user->ID ),
		);
	}

	if ( 'guest' === $entry['type'] && ! empty( $entry['name'] ) ) {
		return array(
			'type' => 'guest',
			'id'   => null,
			'name' => (string) $entry['name'],
			'url'  => (string) ( $entry['url'] ?? '' ),
		);
	}

	return null;
}

/** @return array{type:string,id:?int,name:string,url:string}[] */
function bday_get_post_co_authors( int $post_id ): array {
	$raw = get_post_meta( $post_id, '_bday_co_authors', true );
	$raw = is_array( $raw ) ? $raw : array();
	return array_values( array_filter( array_map( 'bday_co_author_normalize', $raw ) ) );
}

/**
 * A single, optional name+link that replaces the primary "By {author}"
 * byline outright — added for parity with the retired Custom Author
 * Byline plugin's "override giving yourself credit for this post"
 * behavior (one override per post, not a list; that's what
 * _bday_co_authors above is for). Stored separately from co-authors
 * since the two are genuinely different questions: "who else wrote
 * this" vs. "who actually wrote this, instead of whoever's WP account
 * published it."
 *
 * @return array{name:string,url:string}
 */
function bday_get_post_author_override( int $post_id ): array {
	$raw = get_post_meta( $post_id, '_bday_author_override', true );
	$raw = is_array( $raw ) ? $raw : array();
	return array(
		'name' => (string) ( $raw['name'] ?? '' ),
		'url'  => (string) ( $raw['url'] ?? '' ),
	);
}

function bday_co_authors_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_co_authors', 'bday_co_authors_nonce' );

	$primary_id = (int) $post->post_author;
	$current    = bday_get_post_co_authors( $post->ID );

	$candidates = array();
	foreach ( bday_co_author_candidates() as $user ) {
		if ( (int) $user->ID === $primary_id ) {
			continue; // already the primary byline, offering it again as a co-author is redundant
		}
		$candidates[] = array( 'id' => (int) $user->ID, 'name' => $user->display_name );
	}

	$override = bday_get_post_author_override( $post->ID );
	?>
	<p class="description" style="margin-top:0;"><strong>Override the byline</strong> — replaces the "By <?php echo esc_html( get_the_author_meta( 'display_name', $primary_id ) ); ?>" credit entirely. Use this when the real writer isn't the WordPress account that published this post and shouldn't have one created for them. Leave blank to show the normal author below instead.</p>
	<p>
		<label for="bday-author-override-name" class="screen-reader-text">Override author name</label>
		<input type="text" id="bday-author-override-name" name="bday_author_override_name" value="<?php echo esc_attr( $override['name'] ); ?>" class="widefat" placeholder="Author name" />
	</p>
	<p>
		<label for="bday-author-override-url" class="screen-reader-text">Override author link</label>
		<input type="url" id="bday-author-override-url" name="bday_author_override_url" value="<?php echo esc_attr( $override['url'] ); ?>" class="widefat" placeholder="Author's link (optional)" />
	</p>
	<hr style="margin:14px 0;" />

	<p class="description">Credit additional writers here — search for a staff name, or just type a guest writer's name (most bylines aren't staff with an account here). Ignored for whichever byline the override above already replaced, and shown alongside it otherwise.</p>

	<div id="bday-co-authors-list"></div>
	<input type="hidden" id="bday-co-authors-data" name="bday_co_authors_json" value="<?php echo esc_attr( wp_json_encode( $current ) ); ?>" />

	<div style="position:relative;margin-top:8px;">
		<input type="text" id="bday-co-authors-search" class="widefat" autocomplete="off" placeholder="Search staff or type a guest name…" />
		<div id="bday-co-authors-suggestions" style="display:none;position:absolute;left:0;right:0;z-index:10;background:#fff;border:1px solid #ccd0d4;box-shadow:0 2px 4px rgba(0,0,0,.1);max-height:200px;overflow-y:auto;"></div>
	</div>

	<script>
	(function () {
		var candidates = <?php echo wp_json_encode( $candidates ); ?>;
		var listEl   = document.getElementById( 'bday-co-authors-list' );
		var dataEl   = document.getElementById( 'bday-co-authors-data' );
		var searchEl = document.getElementById( 'bday-co-authors-search' );
		var suggEl   = document.getElementById( 'bday-co-authors-suggestions' );
		var entries;
		try {
			entries = JSON.parse( dataEl.value || '[]' );
		} catch ( e ) {
			entries = [];
		}

		function sync() {
			dataEl.value = JSON.stringify( entries );
		}

		function alreadyAdded( type, idOrName ) {
			return entries.some( function ( e ) {
				return 'user' === type
					? ( 'user' === e.type && e.id === idOrName )
					: ( 'guest' === e.type && e.name.toLowerCase() === String( idOrName ).toLowerCase() );
			} );
		}

		function renderRow( entry, index ) {
			var row = document.createElement( 'div' );
			row.style.cssText = 'display:flex;align-items:center;gap:6px;margin-bottom:6px;padding:6px;background:#f6f7f7;border-radius:3px;';

			var label = document.createElement( 'span' );
			label.style.cssText = 'flex:1;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
			label.textContent = entry.name + ( 'user' === entry.type ? ' (staff)' : '' );
			row.appendChild( label );

			if ( 'guest' === entry.type ) {
				var urlInput = document.createElement( 'input' );
				urlInput.type = 'url';
				urlInput.placeholder = "Author's link (optional)";
				urlInput.value = entry.url || '';
				urlInput.style.cssText = 'flex:1;font-size:12px;min-width:0;';
				urlInput.addEventListener( 'input', function () {
					entries[ index ].url = urlInput.value;
					sync();
				} );
				row.appendChild( urlInput );
			}

			var removeBtn = document.createElement( 'button' );
			removeBtn.type = 'button';
			removeBtn.className = 'button-link';
			removeBtn.setAttribute( 'aria-label', 'Remove ' + entry.name );
			removeBtn.textContent = '×';
			removeBtn.style.cssText = 'color:#b32d2e;font-size:16px;line-height:1;padding:0 4px;flex:none;';
			removeBtn.addEventListener( 'click', function () {
				entries.splice( index, 1 );
				render();
			} );
			row.appendChild( removeBtn );

			return row;
		}

		function render() {
			listEl.innerHTML = '';
			entries.forEach( function ( entry, index ) {
				listEl.appendChild( renderRow( entry, index ) );
			} );
			sync();
		}

		function addUser( candidate ) {
			if ( alreadyAdded( 'user', candidate.id ) ) {
				return;
			}
			entries.push( { type: 'user', id: candidate.id, name: candidate.name, url: '' } );
			render();
		}

		function addGuest( name ) {
			name = name.trim();
			if ( '' === name || alreadyAdded( 'guest', name ) ) {
				return;
			}
			entries.push( { type: 'guest', id: null, name: name, url: '' } );
			render();
		}

		function showSuggestions( query ) {
			var q = query.trim().toLowerCase();
			suggEl.innerHTML = '';
			if ( '' === q ) {
				suggEl.style.display = 'none';
				return;
			}

			candidates
				.filter( function ( c ) { return c.name.toLowerCase().indexOf( q ) !== -1 && ! alreadyAdded( 'user', c.id ); } )
				.slice( 0, 8 )
				.forEach( function ( c ) {
					var item = document.createElement( 'div' );
					item.textContent = c.name;
					item.style.cssText = 'padding:6px 8px;cursor:pointer;font-size:12px;';
					item.addEventListener( 'mouseenter', function () { item.style.background = '#f0f0f1'; } );
					item.addEventListener( 'mouseleave', function () { item.style.background = ''; } );
					// mousedown (not click) fires before the input's blur, so the
					// suggestion is still in the DOM to be clicked.
					item.addEventListener( 'mousedown', function ( e ) {
						e.preventDefault();
						addUser( c );
						searchEl.value = '';
						suggEl.style.display = 'none';
					} );
					suggEl.appendChild( item );
				} );

			var guestItem = document.createElement( 'div' );
			guestItem.textContent = 'Add "' + query.trim() + '" as a guest writer';
			guestItem.style.cssText = 'padding:6px 8px;cursor:pointer;font-size:12px;font-style:italic;color:#555;border-top:1px solid #ddd;';
			guestItem.addEventListener( 'mousedown', function ( e ) {
				e.preventDefault();
				addGuest( query );
				searchEl.value = '';
				suggEl.style.display = 'none';
			} );
			suggEl.appendChild( guestItem );

			suggEl.style.display = 'block';
		}

		searchEl.addEventListener( 'input', function () {
			showSuggestions( searchEl.value );
		} );
		searchEl.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' !== e.key ) {
				return;
			}
			e.preventDefault();
			var q     = searchEl.value.trim().toLowerCase();
			var exact = candidates.find( function ( c ) { return c.name.toLowerCase() === q; } );
			if ( exact ) {
				addUser( exact );
			} else if ( q ) {
				addGuest( searchEl.value );
			}
			searchEl.value = '';
			suggEl.style.display = 'none';
		} );
		searchEl.addEventListener( 'blur', function () {
			// Deferred so a suggestion's own mousedown handler above still
			// gets to run before the list disappears.
			window.setTimeout( function () { suggEl.style.display = 'none'; }, 150 );
		} );

		render();
	})();
	</script>
	<?php
}

add_action(
	'save_post_post',
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['bday_co_authors_nonce'] ) || ! wp_verify_nonce( $_POST['bday_co_authors_nonce'], 'bday_co_authors' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST['bday_co_authors_json'] ) ? json_decode( wp_unslash( $_POST['bday_co_authors_json'] ), true ) : array();
		$raw = is_array( $raw ) ? $raw : array();

		$sanitized = array();
		foreach ( $raw as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['type'] ) ) {
				continue;
			}
			if ( 'user' === $entry['type'] && ! empty( $entry['id'] ) ) {
				$sanitized[] = array( 'type' => 'user', 'id' => (int) $entry['id'] );
			} elseif ( 'guest' === $entry['type'] && ! empty( $entry['name'] ) ) {
				$sanitized[] = array(
					'type' => 'guest',
					'name' => sanitize_text_field( (string) $entry['name'] ),
					'url'  => ! empty( $entry['url'] ) ? esc_url_raw( (string) $entry['url'] ) : '',
				);
			}
		}
		update_post_meta( $post_id, '_bday_co_authors', $sanitized );

		$override_name = isset( $_POST['bday_author_override_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bday_author_override_name'] ) ) : '';
		if ( '' === $override_name ) {
			delete_post_meta( $post_id, '_bday_author_override' );
		} else {
			update_post_meta(
				$post_id,
				'_bday_author_override',
				array(
					'name' => $override_name,
					'url'  => isset( $_POST['bday_author_override_url'] ) && '' !== $_POST['bday_author_override_url']
						? esc_url_raw( wp_unslash( $_POST['bday_author_override_url'] ) )
						: '',
				)
			);
		}
	}
);
