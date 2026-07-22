import { defineConfig } from 'vite';

// Real, working build config — replaces the previous package.json's gulp
// declaration, which had no gulpfile.js anywhere in the repo and no
// build/watch npm scripts, so it never actually ran from this snapshot.
//
// Output goes to assets/build/ with content hashes + a manifest.json,
// consumed by inc/enqueue.php via wp_enqueue_style/wp_enqueue_script — the
// real fix for the old inline-PHP-include pattern (CSS/JS wrapped in .php
// files and include()'d directly into every page's <head>, bypassing HTTP
// caching entirely).
export default defineConfig({
	base: '/wp-content/themes/bday_ng_remastered/assets/build/',
	// Copied verbatim into outDir on every build (unhashed — referenced by
	// fixed path from PHP, e.g. assets/build/images/bd-logo.png). Anything
	// that needs cache-busting/hashing belongs in an entry above instead,
	// not here.
	publicDir: 'assets/src/public',
	build: {
		outDir: 'assets/build',
		emptyOutDir: true,
		manifest: true,
		rollupOptions: {
			input: {
				main: 'assets/src/scss/main.scss',
				script: 'assets/src/js/script.js',
			},
			output: {
				// Default ES module output (Rollup's 'iife' format doesn't
				// support multiple entry points, which this config has).
				// script.js reads the global $/jQuery that wp_enqueue_script('jquery')
				// sets up (see inc/enqueue.php) — module scope doesn't stop it from
				// reading that global, and inc/enqueue.php adds type="module" to
				// this bundle's <script> tag via the script_loader_tag filter.
				entryFileNames: 'js/[name].[hash].js',
				chunkFileNames: 'js/[name].[hash].js',
				assetFileNames: (assetInfo) => {
					if (assetInfo.name && assetInfo.name.endsWith('.css')) {
						return 'css/[name].[hash][extname]';
					}
					return 'assets/[name].[hash][extname]';
				},
			},
		},
	},
});
