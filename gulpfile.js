/*
  Ultimate Member - Wall Lib dependencies
*/

const { src, dest, parallel } = require( 'gulp' );
const sass        = require( 'gulp-sass' )( require( 'sass' ) );
const uglify      = require( 'gulp-uglify' );
const cleanCSS    = require( 'gulp-clean-css' );
const rename      = require( 'gulp-rename' );

function defaultTask( done ) {
	src(['src/assets/js/*.js','!src/assets/js/*.min.js'])
		.pipe( uglify() )
		.pipe( rename({ suffix: '.min' }) )
		.pipe( dest( 'src/assets/js/' ) );

	// full CSS files
	src(['src/assets/css/*.sass'])
		.pipe( sass().on( 'error', sass.logError ) )
		.pipe( dest( 'src/assets/css/' ) );
	// min CSS files
	src(['src/assets/css/*.sass'])
		.pipe( sass().on( 'error', sass.logError ) )
		.pipe( cleanCSS() )
		.pipe( rename( { suffix: '.min' } ) )
		.pipe( dest( 'src/assets/css/' ) );

	done();
}
exports.default = defaultTask;
